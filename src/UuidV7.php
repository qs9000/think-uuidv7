<?php
declare(strict_types=1);

namespace qs9000\thinkuuidv7;

use DateTimeImmutable;
use JsonSerializable;
use qs9000\thinkuuidv7\Exception\UuidV7Exception;

/**
 * UUIDv7 Value Object
 */
class UuidV7 implements JsonSerializable
{
    private string $uuid;
    private int $timestampMs;
    private ?int $shardId;

    public function __construct(string $uuid, int $timestampMs, ?int $shardId = null)
    {
        if (empty($uuid)) {
            throw new UuidV7Exception('UUID cannot be empty');
        }
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
            throw new UuidV7Exception('Invalid UUID format: ' . $uuid);
        }
        if ($timestampMs < 0) {
            throw new UuidV7Exception('Timestamp must be non-negative');
        }
        if ($shardId !== null && ($shardId < 0 || $shardId > 255)) {
            throw new UuidV7Exception('Shard ID must be between 0 and 255');
        }

        $this->uuid = strtolower($uuid);
        $this->timestampMs = $timestampMs;
        $this->shardId = $shardId;
    }

    /**
     * Create UuidV7 from UUID string with auto-extracted timestamp and shardId
     * This is the preferred factory method when you only have the UUID string
     *
     * @throws UuidV7Exception If UUID format is invalid or version/variant bits are incorrect
     */
    public static function fromUuid(string $uuid): self
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
            throw new UuidV7Exception('Invalid UUID format: ' . $uuid);
        }

        $hex = str_replace('-', '', $uuid);

        // Validate version (must be 7)
        $version = hexdec($hex[8]);
        if ($version !== 7) {
            throw new UuidV7Exception('UUID version must be 7, got: ' . $version);
        }

        // Validate variant (must be 8, 9, a, or b)
        $variant = hexdec($hex[12]);
        if ($variant < 8 || $variant > 11) {
            throw new UuidV7Exception('UUID variant must be 8-b, got: ' . $variant);
        }

        // Extract timestamp
        $g1 = hexdec(substr($hex, 0, 8));
        $g2 = hexdec(substr($hex, 8, 4)) & 0x0FFF;
        $timestampMs = ($g1 << 16) | $g2;

        // Extract shardId
        $g2Full = hexdec(substr($hex, 8, 4));
        $rand_a = $g2Full & 0x0FFF;
        $shardId = ($rand_a >> 4) & 0xFF;

        return new self($uuid, $timestampMs, $shardId);
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getTimestampMs(): int
    {
        return $this->timestampMs;
    }

    public function getDatetime(): DateTimeImmutable
    {
        $seconds = (int) floor($this->timestampMs / 1000);
        $millis = $this->timestampMs % 1000;

        $datetime = DateTimeImmutable::createFromFormat('U.u', sprintf('%d.%03d', $seconds, $millis));
        return $datetime ?: new DateTimeImmutable('@' . $seconds);
    }

    public function getShardId(): ?int
    {
        return $this->shardId;
    }

    /**
     * Convert UUID to binary format (16 bytes)
     * Use BINARY(16) in database instead of CHAR(36) to save ~55% space
     *
     * Example: '0191a51a-b2c3-7d89-0123-456789abcdef' -> "\x01\x91\xa5\x1a\xb2\xc3\x7d\x89\x01\x23\x45\x67\x89\xab\xcd\xef"
     */
    public function toBinary(): string
    {
        $hex = str_replace('-', '', $this->uuid);
        return hex2bin($hex);
    }

    /**
     * Create UuidV7 from binary format
     *
     * @param string $binary 16-byte binary string
     * @param int|null $timestampMs Optional timestamp (for faster parsing without re-extraction)
     * @param int|null $shardId Optional shard ID
     */
    public static function fromBinary(string $binary, ?int $timestampMs = null, ?int $shardId = null): self
    {
        if (strlen($binary) !== 16) {
            throw new UuidV7Exception('Binary UUID must be exactly 16 bytes');
        }

        $hex = bin2hex($binary);

        // Format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
        $uuid = sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),    // g1: 8 chars
            substr($hex, 8, 4),    // g2: 4 chars (version + rand_a)
            substr($hex, 12, 4),   // g3: 4 chars (variant + rand_b high)
            substr($hex, 16, 4),   // g4: 4 chars (rand_b low + rand_c high)
            substr($hex, 20, 12)   // g5: 12 chars (rand_c)
        );

        // If timestamp not provided, extract from UUID
        if ($timestampMs === null) {
            $g1 = hexdec(substr($hex, 0, 8));
            $g2 = hexdec(substr($hex, 8, 4)) & 0x0FFF;
            $timestampMs = ($g1 << 16) | $g2;
        }

        // If shardId not provided, extract from UUID
        if ($shardId === null) {
            $g2 = hexdec(substr($hex, 8, 4));
            $rand_a = $g2 & 0x0FFF;
            $shardId = ($rand_a >> 4) & 0xFF;
        }

        return new self($uuid, $timestampMs, $shardId);
    }

    /**
     * Get binary length constant for database schema
     */
    public static function BINARY_LENGTH(): int
    {
        return 16;
    }

    public function __toString(): string
    {
        return $this->uuid;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize(): mixed
    {
        return $this->uuid;
    }

    public function compareTo(UuidV7 $other): int
    {
        return $this->timestampMs <=> $other->timestampMs;
    }

    public function isBefore(UuidV7 $other): bool
    {
        return $this->timestampMs < $other->timestampMs;
    }

    public function isAfter(UuidV7 $other): bool
    {
        return $this->timestampMs > $other->timestampMs;
    }
}
