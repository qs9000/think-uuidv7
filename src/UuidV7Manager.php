<?php

declare(strict_types=1);

namespace qs9000\thinkuuidv7;

use DateTimeImmutable;
use think\Manager;
use think\facade\Config;

/**
 * UUIDv7 Manager
 */
class UuidV7Manager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->getConfig('driver', 'file');
    }

    protected function getConfig(string $name, mixed $default = null): mixed
    {
        return Config::get('uuidv7.' . $name, $default);
    }

    protected function createDriver(string $name): UuidV7Generator
    {
        $shardId = $this->getConfig('shard_id', 0);
        $keyPrefix = $this->getConfig('key_prefix', 'uuidv7:seq');

        // $name 即 cache store 名称 (file/redis 等)
        $sequenceGenerator = new SequenceGenerator($keyPrefix, $shardId, $name);

        return new UuidV7Generator($shardId, $sequenceGenerator);
    }

    public function driver(?string $name = null): UuidV7Generator
    {
        return parent::driver($name);
    }

    public function generate(?string $driver = null): string
    {
        return $this->driver($driver)->generate();
    }

    public function make(?string $driver = null): UuidV7
    {
        return $this->driver($driver)->make();
    }

    public function makeBatch(int $count, ?string $driver = null): array
    {
        return $this->driver($driver)->makeBatch($count);
    }

    public function parse(string $uuid, ?string $driver = null): ?UuidV7
    {
        return $this->driver($driver)->parse($uuid);
    }

    public function validate(string $uuid, ?string $driver = null): bool
    {
        return $this->driver($driver)->validate($uuid);
    }

    public function timestamp(string $uuid, ?string $driver = null): int
    {
        return $this->driver($driver)->timestamp($uuid);
    }

    public function datetime(string $uuid, ?string $driver = null): DateTimeImmutable
    {
        return $this->driver($driver)->datetime($uuid);
    }

    public function toBinary(string $uuid, ?string $driver = null): string
    {
        return $this->driver($driver)->toBinary($uuid);
    }

    public function fromBinary(string $binary, ?int $timestampMs = null, ?int $shardId = null): UuidV7
    {
        return UuidV7::fromBinary($binary, $timestampMs, $shardId);
    }

    public function getShardId(): int
    {
        return $this->getConfig('shard_id', 0);
    }

    public function setShardId(int $shardId): self
    {
        $shardId = $shardId & 0xFF;
        Config::set(['shard_id' => $shardId], 'uuidv7');

        // Clear driver cache so next driver() call uses new shard_id
        if (property_exists($this, 'drivers')) {
            $this->drivers = [];
        }

        return $this;
    }

    /**
     * Create a generator instance with the specified shard ID
     * This does not affect the global configuration
     */
    public function withShardId(int $shardId): UuidV7Generator
    {
        $shardId = $shardId & 0xFF;
        $keyPrefix = $this->getConfig('key_prefix', 'uuidv7:seq');
        $driverName = $this->getDefaultDriver();
        $sequenceGenerator = new SequenceGenerator($keyPrefix, $shardId, $driverName);

        return new UuidV7Generator($shardId, $sequenceGenerator);
    }
}
