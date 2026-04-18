<?php
declare(strict_types=1);

namespace qs9000\thinkuuidv7;

use DateTimeImmutable;
use qs9000\thinkuuidv7\Exception\UuidV7Exception;

/**
 * UUIDv7 Generator
 */
class UuidV7Generator implements UuidV7Interface
{
    private int $shardId = 0;
    private ?SequenceGenerator $redisGenerator = null;
    private ?int $lastTimestamp = null;
    private int $localSequence = 0;
    private const MAX_LOCAL_SEQUENCE = 4095;

    /** @var string Cached random bytes for batch generation */
    private string $randomCache = '';
    private int $randomCacheOffset = 0;
    private const RANDOM_CACHE_SIZE = 256;

    public function __construct(int $shardId = 0, ?SequenceGenerator $redisGenerator = null)
    {
        $this->shardId = $shardId & 0xFF;
        $this->redisGenerator = $redisGenerator;
    }

    public function setShardId(int $shardId): self
    {
        $this->shardId = $shardId & 0xFF;
        return $this;
    }

    public function generate(?string $driver = null): string
    {
        return $this->make()->getUuid();
    }

    public function make(?string $driver = null): UuidV7
    {
        $timestamp = $this->getCurrentTimestampMs();
        $sequence = 0;
        $retryCount = 0;
        
        while (true) {
            if ($this->lastTimestamp === $timestamp) {
                $this->localSequence++;
                
                if ($this->localSequence > self::MAX_LOCAL_SEQUENCE) {
                    $this->waitForNextMillisecond();
                    $timestamp = $this->getCurrentTimestampMs();
                    $this->localSequence = 0;
                    continue;
                }
            } else {
                $this->localSequence = 0;
            }
            
            $this->lastTimestamp = $timestamp;
            $sequence = $this->getSequence($timestamp);
            
            // Handle Redis overflow
            if ($sequence === -1) {
                $this->waitForNextMillisecond();
                $timestamp = $this->getCurrentTimestampMs();
                $this->localSequence = 0;
                $retryCount++;
                
                // Prevent infinite loop
                if ($retryCount > 100) {
                    throw new UuidV7Exception('UUIDv7 generation overflow: too many requests per millisecond');
                }
                continue;
            }
            
            break;
        }
        
        return $this->buildUuidV7($timestamp, $sequence);
    }

    public function makeBatch(int $count, ?string $driver = null): array
    {
        if ($count <= 0) {
            return [];
        }

        $uuids = [];
        $currentTimestamp = null;
        $redisSequenceBase = 0;
        $lastSequence = 0;
        $retryCount = 0;

        for ($i = 0; $i < $count; $i++) {
            // Redis overflow retry loop
            while (true) {
                $timestamp = $this->getCurrentTimestampMs();

                // Check if timestamp changed
                if ($currentTimestamp !== $timestamp) {
                    $currentTimestamp = $timestamp;
                    $this->localSequence = 0;
                    $retryCount = 0;  // Reset retry count on new timestamp
                    $lastSequence = $this->getSequence($timestamp);

                    // Handle Redis overflow
                    if ($lastSequence === -1) {
                        $this->waitForNextMillisecond();
                        $currentTimestamp = $this->getCurrentTimestampMs();
                        $lastSequence = $this->getSequence($currentTimestamp);
                        $this->localSequence = 0;

                        if ($lastSequence === -1) {
                            $retryCount++;
                            if ($retryCount > 100) {
                                throw new UuidV7Exception('UUIDv7 batch generation overflow: too many requests per millisecond');
                            }
                            continue;  // Retry with new timestamp
                        }
                    }
                } else {
                    $this->localSequence++;

                    // Local sequence overflow - get new sequence from Redis
                    if ($this->localSequence > self::MAX_LOCAL_SEQUENCE) {
                        if ($this->redisGenerator !== null) {
                            $lastSequence = $this->redisGenerator->getSequence($currentTimestamp);
                            if ($lastSequence === -1) {
                                $this->waitForNextMillisecond();
                                $currentTimestamp = $this->getCurrentTimestampMs();
                                $lastSequence = $this->getSequence($currentTimestamp);
                                $this->localSequence = 0;

                                if ($lastSequence === -1) {
                                    $retryCount++;
                                    if ($retryCount > 100) {
                                        throw new UuidV7Exception('UUIDv7 batch generation overflow: too many requests per millisecond');
                                    }
                                    continue;  // Retry with new timestamp
                                }
                            }
                        } else {
                            $this->waitForNextMillisecond();
                            $currentTimestamp = $this->getCurrentTimestampMs();
                            $lastSequence = 0;
                            $this->localSequence = 0;
                        }
                    } else {
                        // Use Redis sequence base + local sequence offset
                        $lastSequence = $redisSequenceBase + $this->localSequence;
                    }
                }

                $this->lastTimestamp = $currentTimestamp;
                $redisSequenceBase = $lastSequence;
                break;  // Success, exit retry loop
            }

            $uuids[] = $this->buildUuidV7($currentTimestamp, $lastSequence)->getUuid();
        }

        return $uuids;
    }

    public function parse(string $uuid, ?string $driver = null): ?UuidV7
    {
        if (!$this->validate($uuid)) {
            return null;
        }

        return new UuidV7($uuid, $this->extractTimestamp($uuid), $this->extractShardId($uuid));
    }

    public function validate(string $uuid, ?string $driver = null): bool
    {
        // Check format
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
            return false;
        }

        // Extract and check version (position 8, first char of second group)
        $hex = str_replace('-', '', $uuid);
        $version = hexdec($hex[8]);
        if ($version !== 7) {
            return false;
        }

        // Check variant (position 12, first char of third group)
        // Variant bits should be in high 2 bits, valid values are [89ab] (8-11 decimal)
        $variant = hexdec($hex[12]);
        if ($variant < 8 || $variant > 11) {
            return false;
        }

        return true;
    }

    public function timestamp(string $uuid, ?string $driver = null): int
    {
        if (!$this->validate($uuid)) {
            throw new UuidV7Exception('Invalid UUIDv7 format');
        }

        return $this->extractTimestamp($uuid);
    }

    public function datetime(string $uuid, ?string $driver = null): DateTimeImmutable
    {
        $ts = $this->timestamp($uuid);
        $seconds = (int) floor($ts / 1000);
        $millis = $ts % 1000;
        
        return DateTimeImmutable::createFromFormat('U.u', sprintf('%d.%03d', $seconds, $millis));
    }

    protected function getCurrentTimestampMs(): int
    {
        // Use microtime for cross-platform compatibility
        return (int) floor(microtime(true) * 1000);
    }

    protected function getSequence(int $timestamp): int
    {
        if ($this->redisGenerator !== null) {
            return $this->redisGenerator->getSequence($timestamp);
        }
        
        return $this->localSequence;
    }

    protected function buildUuidV7(int $timestampMs, int $sequence): UuidV7
    {
        // UUIDv7 per RFC 9562:
        // xxxxxxxx-xxxx-7xxx-yxxx-xxxxxxxxxxxx
        //
        // 128 bits structure:
        // - timestamp: 48 bits (milliseconds since Unix epoch)
        // - version: 4 bits (value 7) in first nibble of g2
        // - rand_a: 12 bits in g2 (shard_id[8] + seq_high[4])
        // - variant: 2 bits (value 0b10) in first nibble of g3
        // - rand_b: 12 bits in g3 + g4 (seq_low[8] + random[14])
        // - rand_c: 48 bits in g4 + g5

        // Get random bytes
        $randomBytes = $this->getRandomBytes(10);

        // Group 1: timestamp high 32 bits (bits 47-16)
        $g1 = str_pad(sprintf('%x', ($timestampMs >> 16) & 0xFFFFFFFF), 8, '0', STR_PAD_LEFT);

        // Group 2: version(4) + rand_a(12)
        // rand_a = shard_id(8 high bits) + seq(4 low bits)
        $rand_a = (($this->shardId & 0xFF) << 4) | (($sequence >> 8) & 0x0F);
        $g2 = str_pad(sprintf('%x', (7 << 12) | $rand_a), 4, '0', STR_PAD_LEFT);

        // Group 3: variant(2) + rand_b high 12 bits
        // variant = 0x8xxx (binary 10xx = 8, 9, a, b)
        // rand_b = seq_low(8 bits) + random(6 bits from randomBytes)
        $rand_b = (($sequence & 0xFF) << 6) | (((ord($randomBytes[0]) << 8) | ord($randomBytes[1])) & 0x3F);
        $g3 = str_pad(sprintf('%x', 0x8000 | ($rand_b >> 2)), 4, '0', STR_PAD_LEFT);
        
        // Group 4: rand_b low 2 bits + rand_c high
        $rand_b_low_2 = $rand_b & 0x03;
        $rand_c_high = ((ord($randomBytes[1]) >> 6) | ((ord($randomBytes[2]) << 2) & 0xFC)) & 0xFF;
        $g4 = str_pad(sprintf('%x', ($rand_b_low_2 << 8) | $rand_c_high), 4, '0', STR_PAD_LEFT);
        
        // Group 5: rand_c 48 bits = 12 hex chars
        $rand_c = ((ord($randomBytes[3]) << 40) | (ord($randomBytes[4]) << 32) | 
                   (ord($randomBytes[5]) << 24) | (ord($randomBytes[6]) << 16) | 
                   (ord($randomBytes[7]) << 8) | ord($randomBytes[8])) & 0xFFFFFFFFFFFF;
        $g5 = str_pad(sprintf('%x', $rand_c), 12, '0', STR_PAD_LEFT);
        
        $uuid = sprintf('%s-%s-%s-%s-%s', $g1, $g2, $g3, $g4, $g5);

        return new UuidV7($uuid, $timestampMs, $this->shardId);
    }

    /**
     * Get random bytes from cache or generate new ones
     */
    protected function getRandomBytes(int $count): string
    {
        // Refill cache if needed
        if ($this->randomCacheOffset + $count > strlen($this->randomCache)) {
            $this->randomCache = random_bytes(self::RANDOM_CACHE_SIZE);
            $this->randomCacheOffset = 0;
        }

        $bytes = substr($this->randomCache, $this->randomCacheOffset, $count);
        $this->randomCacheOffset += $count;

        return $bytes;
    }

    /**
     * Flush the random cache
     */
    protected function flushRandomCache(): void
    {
        $this->randomCache = '';
        $this->randomCacheOffset = 0;
    }

    /**
     * Extract shard ID from UUID
     * Shard ID is stored in the high 8 bits of rand_a (g2[4:12])
     */
    protected function extractShardId(string $uuid): int
    {
        $hex = str_replace('-', '', $uuid);
        $g2 = hexdec(substr($hex, 8, 4));
        // rand_a is in the lower 12 bits of g2 (mask 0x0FFF)
        // shard_id occupies the high 8 bits of rand_a
        $rand_a = $g2 & 0x0FFF;

        return ($rand_a >> 4) & 0xFF;
    }

    /**
     * Extract sequence from UUID
     * Sequence occupies: high 4 bits in rand_a + low 8 bits in rand_b
     * Note: rand_b is stored as (rand_b >> 2) in g3, need to shift back
     */
    protected function extractSequence(string $uuid): int
    {
        $hex = str_replace('-', '', $uuid);

        // rand_a from g2 (low 12 bits, mask 0x0FFF)
        $g2 = hexdec(substr($hex, 8, 4));
        $rand_a = $g2 & 0x0FFF;
        $seq_high = $rand_a & 0x0F;  // 4 bits from rand_a low

        // rand_b from g3: stored as (rand_b >> 2), need to shift back
        $g3 = hexdec(substr($hex, 13, 4));
        $rand_b_shifted = $g3 & 0x0FFF;  // rand_b >> 2
        $rand_b = $rand_b_shifted << 2;  // restore rand_b
        $seq_low = ($rand_b >> 6) & 0xFF;  // 8 bits from rand_b high

        return ($seq_high << 8) | $seq_low;
    }

    protected function extractTimestamp(string $uuid): int
    {
        // UUIDv7 timestamp extraction
        // g1 = high 32 bits, g2 low 12 bits = timestamp low bits
        $hex = str_replace('-', '', $uuid);
        $g1 = hexdec(substr($hex, 0, 8));
        $g2 = hexdec(substr($hex, 8, 4)) & 0x0FFF; // mask out version nibble

        return ($g1 << 16) | $g2;
    }

    protected function waitForNextMillisecond(): void
    {
        $current = $this->getCurrentTimestampMs();
        $lastTs = $this->lastTimestamp ?? ($current - 1);

        if ($lastTs >= $current) {
            // 等待直到下一个毫秒（最多 2ms，避免时钟回拨时长时间阻塞）
            $sleepUs = min(2000, ($lastTs - $current + 1) * 1000);
            usleep((int) $sleepUs);
        }
    }
}
