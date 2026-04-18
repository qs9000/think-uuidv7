<?php
declare(strict_types=1);

namespace qs9000\thinkuuidv7;

use DateTimeImmutable;

/**
 * UUIDv7 Interface
 */
interface UuidV7Interface
{
    public function generate(?string $driver = null): string;

    public function make(?string $driver = null): UuidV7;

    public function makeBatch(int $count, ?string $driver = null): array;

    public function parse(string $uuid, ?string $driver = null): ?UuidV7;

    public function validate(string $uuid, ?string $driver = null): bool;

    public function timestamp(string $uuid, ?string $driver = null): int;

    public function datetime(string $uuid, ?string $driver = null): DateTimeImmutable;

    public function toBinary(string $uuid, ?string $driver = null): string;

    public function fromBinary(string $binary, ?int $timestampMs = null, ?int $shardId = null): UuidV7;
}
