<?php

declare(strict_types=1);

namespace qs9000\thinkuuidv7;

use think\facade\Cache;
use think\cache\driver\Redis;

/**
 * Sequence Generator
 *
 * Uses Redis (via cache store) for distributed sequence generation.
 */
class SequenceGenerator
{
    private string $keyPrefix;
    private int $shardId;
    private string $cacheStore;
    private int $localSequence = 0;
    private ?int $lastTimestamp = null;

    private const MAX_SEQUENCE = 0xFFFFFFFFFF; // 40 bits

    public function __construct(string $keyPrefix = 'uuidv7:seq', int $shardId = 0, string $cacheStore = 'file')
    {
        $this->keyPrefix = $keyPrefix;
        $this->shardId = $shardId & 0xFF;
        $this->cacheStore = $cacheStore;
    }

    /**
     * Get next sequence number for given timestamp
     *
     * @param int $timestampMs Current timestamp in milliseconds
     * @return int Sequence number, or -1 if overflow
     */
    public function getSequence(int $timestampMs): int
    {
        // Local fast path for same millisecond
        if ($this->lastTimestamp === $timestampMs) {
            $this->localSequence++;

            // Local sequence overflow, return -1 to signal caller
            if ($this->localSequence > self::MAX_SEQUENCE) {
                return -1;
            }

            return $this->localSequence;
        }

        // Different millisecond, reset local sequence and get from Redis
        $this->lastTimestamp = $timestampMs;
        $this->localSequence = 0;

        try {
            $redis = $this->getRedisHandler($this->cacheStore);
            if (!$redis) {
                return $this->localSequence;
            }

            $result = $redis->eval(
                self::REDIS_SCRIPT,
                1,
                $this->keyPrefix,
                $timestampMs,
                self::MAX_SEQUENCE
            );

            return (int) $result;
        } catch (\Throwable) {
            // Redis error, fall back to local sequence
            return $this->localSequence;
        }
    }

    /**
     * Lua script for atomic sequence increment (optimized with MGET)
     */
    private const REDIS_SCRIPT = <<<'LUA'
local ts_key = KEYS[1] .. ':ts'
local seq_key = KEYS[1] .. ':seq'
local timestamp = tonumber(ARGV[1])
local max_seq = tonumber(ARGV[2])

-- Use MGET to reduce network round trips
local values = redis.call('MGET', ts_key, seq_key)
local last_ts = tonumber(values[1]) or 0
local last_seq = tonumber(values[2]) or 0

if timestamp > last_ts then
    -- New millisecond, reset sequence
    redis.call('SET', ts_key, timestamp)
    redis.call('SET', seq_key, 1)
    return 1
elseif timestamp == last_ts then
    -- Same millisecond, increment sequence
    local new_seq = last_seq + 1
    if new_seq > max_seq then
        -- Overflow, caller should wait for next millisecond
        return -1
    end
    redis.call('SET', seq_key, new_seq)
    return new_seq
else
    -- Clock went backwards (shouldn't happen), reset
    redis.call('SET', ts_key, timestamp)
    redis.call('SET', seq_key, 1)
    return 1
end
LUA;

    /**
     * Get Redis handler from cache
     */
    protected function getRedisHandler(?string $storeName = null): ?Redis
    {
        try {
            $cache = Cache::store($storeName);

            if (method_exists($cache, 'handler')) {
                $handler = $cache->handler();
                if ($handler instanceof Redis) {
                    return $handler;
                }
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get shard ID
     */
    public function getShardId(): int
    {
        return $this->shardId;
    }

    /**
     * Reset sequence (for testing)
     */
    public function reset(): void
    {
        $this->localSequence = 0;
        $this->lastTimestamp = null;

        try {
            $redis = $this->getRedisHandler($this->cacheStore);
            if ($redis) {
                $redis->del("{$this->keyPrefix}:ts");
                $redis->del("{$this->keyPrefix}:seq");
            }
        } catch (\Throwable) {
            // Ignore
        }
    }

    /**
     * Check Redis connection
     */
    public function isAvailable(): bool
    {
        try {
            $redis = $this->getRedisHandler($this->cacheStore);
            return $redis && $redis->ping() !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
