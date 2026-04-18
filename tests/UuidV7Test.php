<?php
declare(strict_types=1);

namespace qs9000\thinkuuidv7\tests;

use PHPUnit\Framework\TestCase;
use qs9000\thinkuuidv7\UuidV7;

class UuidV7Test extends TestCase
{
    public function testConstructorWithValidUuid(): void
    {
        $uuid = new UuidV7('0191a51a-b2c3-7d89-0123-456789abcdef', 1713456789000, 255);
        
        $this->assertSame('0191a51a-b2c3-7d89-0123-456789abcdef', $uuid->getUuid());
        $this->assertSame(1713456789000, $uuid->getTimestampMs());
        $this->assertSame(255, $uuid->getShardId());
    }

    public function testConstructorNormalizesToLowercase(): void
    {
        $uuid = new UuidV7('0191A51A-B2C3-7D89-0123-456789ABCDEF', 1713456789000, 0);
        
        $this->assertSame('0191a51a-b2c3-7d89-0123-456789abcdef', $uuid->getUuid());
    }

    public function testConstructorWithNullShardId(): void
    {
        $uuid = new UuidV7('0191a51a-b2c3-7d89-0123-456789abcdef', 1713456789000);
        
        $this->assertNull($uuid->getShardId());
    }

    public function testConstructorThrowsOnEmptyUuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('UUID cannot be empty');
        
        new UuidV7('', 1713456789000);
    }

    public function testConstructorThrowsOnInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid UUID format');
        
        new UuidV7('not-a-valid-uuid', 1713456789000);
    }

    public function testConstructorThrowsOnNegativeTimestamp(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Timestamp must be non-negative');
        
        new UuidV7('0191a51a-b2c3-7d89-0123-456789abcdef', -1);
    }

    public function testConstructorThrowsOnInvalidShardId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Shard ID must be between 0 and 255');
        
        new UuidV7('0191a51a-b2c3-7d89-0123-456789abcdef', 1713456789000, 256);
    }

    public function testGetDatetime(): void
    {
        $timestampMs = 1713456789000;
        $uuid = new UuidV7('0191a51a-b2c3-7d89-0123-456789abcdef', $timestampMs);
        
        $datetime = $uuid->getDatetime();
        
        $this->assertInstanceOf(\DateTimeImmutable::class, $datetime);
        $this->assertSame((int) floor($timestampMs / 1000), $datetime->getTimestamp());
    }

    public function testToString(): void
    {
        $uuid = new UuidV7('0191a51a-b2c3-7d89-0123-456789abcdef', 1713456789000);
        
        $this->assertSame('0191a51a-b2c3-7d89-0123-456789abcdef', (string) $uuid);
    }

    public function testJsonSerialize(): void
    {
        $uuid = new UuidV7('0191a51a-b2c3-7d89-0123-456789abcdef', 1713456789000);
        
        // JsonSerializable returns the UUID string, json_encode wraps it in quotes
        $this->assertSame('"0191a51a-b2c3-7d89-0123-456789abcdef"', json_encode($uuid));
    }

    public function testCompareToWhenSame(): void
    {
        $uuid1 = new UuidV7('0191a51a-b2c3-7d89-0123-456789abcdef', 1713456789000);
        $uuid2 = new UuidV7('0191a51a-b2c4-7d89-0123-456789abcdef', 1713456789000);
        
        $this->assertSame(0, $uuid1->compareTo($uuid2));
    }

    public function testCompareToWhenBefore(): void
    {
        $uuid1 = new UuidV7('0191a51a-b2c3-7d89-0123-456789abcdef', 1713456789000);
        $uuid2 = new UuidV7('0191a51a-b2c4-7d89-0123-456789abcdef', 1713456789001);
        
        $this->assertSame(-1, $uuid1->compareTo($uuid2));
    }

    public function testCompareToWhenAfter(): void
    {
        $uuid1 = new UuidV7('0191a51a-b2c4-7d89-0123-456789abcdef', 1713456789001);
        $uuid2 = new UuidV7('0191a51a-b2c3-7d89-0123-456789abcdef', 1713456789000);
        
        $this->assertSame(1, $uuid1->compareTo($uuid2));
    }

    public function testIsBefore(): void
    {
        $uuid1 = new UuidV7('0191a51a-b2c3-7d89-0123-456789abcdef', 1713456789000);
        $uuid2 = new UuidV7('0191a51a-b2c4-7d89-0123-456789abcdef', 1713456789001);
        
        $this->assertTrue($uuid1->isBefore($uuid2));
        $this->assertFalse($uuid2->isBefore($uuid1));
    }

    public function testIsAfter(): void
    {
        $uuid1 = new UuidV7('0191a51a-b2c4-7d89-0123-456789abcdef', 1713456789001);
        $uuid2 = new UuidV7('0191a51a-b2c3-7d89-0123-456789abcdef', 1713456789000);

        $this->assertTrue($uuid1->isAfter($uuid2));
        $this->assertFalse($uuid2->isAfter($uuid1));
    }

    // ==================== fromUuid Tests ====================

    public function testFromUuidWithValidUuid(): void
    {
        // Valid UUIDv7: version=7 (position 8), variant=8 (position 12)
        $uuid = UuidV7::fromUuid('019d9f72-7000-8008-019d-3f54e5075d2d');

        $this->assertSame('019d9f72-7000-8008-019d-3f54e5075d2d', $uuid->getUuid());
    }

    public function testFromUuidWithLowercase(): void
    {
        $uuid = UuidV7::fromUuid('019d9f72-7000-8008-019d-3f54e5075d2d');

        $this->assertSame('019d9f72-7000-8008-019d-3f54e5075d2d', $uuid->getUuid());
    }

    public function testFromUuidWithUppercase(): void
    {
        $uuid = UuidV7::fromUuid('019D9F72-7000-8008-019D-3F54E5075D2D');

        $this->assertSame('019d9f72-7000-8008-019d-3f54e5075d2d', $uuid->getUuid());
    }

    public function testFromUuidThrowsOnInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid UUID format');

        UuidV7::fromUuid('not-a-uuid');
    }

    public function testFromUuidThrowsOnWrongVersion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('UUID version must be 7');

        // Version 1 UUID: '1' at position 8 instead of '7'
        UuidV7::fromUuid('019d9f72-1000-8008-019d-3f54e5075d2d');
    }

    public function testFromUuidThrowsOnWrongVariant(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('UUID variant must be 8-b');

        // Variant 0: '0' at position 12 instead of '8-b'
        UuidV7::fromUuid('019d9f72-7000-0008-019d-3f54e5075d2d');
    }
}
