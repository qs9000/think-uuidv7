<?php
declare(strict_types=1);

namespace qs9000\thinkuuidv7;

use think\Facade;

/**
 * UUIDv7 Facade
 *
 * @method static string generate(?string $driver = null)
 * @method static UuidV7 make(?string $driver = null)
 * @method static array makeBatch(int $count, ?string $driver = null)
 * @method static UuidV7|null parse(string $uuid, ?string $driver = null)
 * @method static bool validate(string $uuid, ?string $driver = null)
 * @method static int timestamp(string $uuid, ?string $driver = null)
 * @method static \DateTimeImmutable datetime(string $uuid, ?string $driver = null)
 * @method static string toBinary(string $uuid, ?string $driver = null)
 * @method static UuidV7 fromBinary(string $binary, ?int $timestampMs = null, ?int $shardId = null)
 */
class UuidV7Facade extends Facade
{
    protected static function getFacadeClass(): string
    {
        return 'uuidv7';
    }
}
