<?php
declare(strict_types=1);

namespace qs9000\thinkuuidv7\tests;

use PHPUnit\Framework\TestCase;
use qs9000\thinkuuidv7\Exception\UuidV7Exception;
use qs9000\thinkuuidv7\UuidV7;
use qs9000\thinkuuidv7\UuidV7Generator;

class UuidV7BinaryTest extends TestCase
{
    public function testToBinaryProduces16Bytes(): void
    {
        $generator = new UuidV7Generator(42);
        $uuidObj = $generator->make();
        $binary = $uuidObj->toBinary();

        $this->assertSame(16, strlen($binary), 'Binary representation must be 16 bytes');
    }

    public function testToBinaryIsReversible(): void
    {
        $generator = new UuidV7Generator(255);
        $uuidObj = $generator->make();

        $binary = $uuidObj->toBinary();
        $restored = UuidV7::fromBinary($binary);

        // UUID string should match after round-trip
        $this->assertSame($uuidObj->getUuid(), $restored->getUuid());

        // Binary representation should be identical
        $this->assertSame($binary, $restored->toBinary());
    }

    public function testFromBinaryWithProvidedTimestamp(): void
    {
        $expectedTimestamp = 1713456789000;
        $generator = new UuidV7Generator(42);
        $uuidObj = $generator->make();
        $binary = $uuidObj->toBinary();

        // Override timestamp with our expected value
        $uuid = UuidV7::fromBinary($binary, $expectedTimestamp);

        // Timestamp should match provided value
        $this->assertSame($expectedTimestamp, $uuid->getTimestampMs());

        // UUID string should remain unchanged
        $this->assertSame($uuidObj->getUuid(), $uuid->getUuid());
    }

    public function testFromBinaryWithProvidedShardId(): void
    {
        $expectedShardId = 99;
        $generator = new UuidV7Generator($expectedShardId);
        $uuidObj = $generator->make();
        $binary = $uuidObj->toBinary();

        $uuid = UuidV7::fromBinary($binary, null, $expectedShardId);

        // ShardId should match provided value
        $this->assertSame($expectedShardId, $uuid->getShardId());

        // UUID string should remain unchanged
        $this->assertSame($uuidObj->getUuid(), $uuid->getUuid());
    }

    public function testFromBinaryWithoutArgumentsExtractsFromUuid(): void
    {
        $generator = new UuidV7Generator(42);
        $original = $generator->make();
        $binary = $original->toBinary();

        $uuid = UuidV7::fromBinary($binary);

        // UUID string should match
        $this->assertSame($original->getUuid(), $uuid->getUuid());

        // Binary representation should be identical
        $this->assertSame($binary, $uuid->toBinary());
    }

    public function testFromBinaryThrowsOnInvalidLength(): void
    {
        $this->expectException(UuidV7Exception::class);
        $this->expectExceptionMessage('Binary UUID must be exactly 16 bytes');

        UuidV7::fromBinary('short');
    }

    public function testFromBinaryThrowsOnTooLong(): void
    {
        $this->expectException(UuidV7Exception::class);
        $this->expectExceptionMessage('Binary UUID must be exactly 16 bytes');

        UuidV7::fromBinary('12345678901234567'); // 17 bytes
    }

    public function testBinaryLengthConstant(): void
    {
        $this->assertSame(16, UuidV7::BINARY_LENGTH());
    }

    public function testBinaryComparison(): void
    {
        $generator = new UuidV7Generator(42);
        $uuidObj1 = $generator->make();
        $uuidObj2 = new UuidV7($uuidObj1->getUuid(), $uuidObj1->getTimestampMs(), $uuidObj1->getShardId());

        // Same UUID should produce same binary
        $this->assertSame($uuidObj1->toBinary(), $uuidObj2->toBinary());
    }

    public function testBinaryRoundTripPreservesOrder(): void
    {
        $generator = new UuidV7Generator(42);
        $binaryData = [];

        // Generate UUIDs and convert to binary
        for ($i = 0; $i < 100; $i++) {
            $uuidObj = $generator->make();
            $binaryData[] = $uuidObj->toBinary();
        }

        // Binary round-trip should preserve UUID strings
        $restored = array_map(fn($b) => UuidV7::fromBinary($b), $binaryData);

        for ($i = 0; $i < 100; $i++) {
            $this->assertSame(
                substr($binaryData[$i], 0, 16),
                substr($restored[$i]->toBinary(), 0, 16),
                "Binary data should be preserved at index $i"
            );
        }
    }

    public function testBinaryHexRepresentation(): void
    {
        $generator = new UuidV7Generator(42);
        $uuidObj = $generator->make();
        $binary = $uuidObj->toBinary();
        $hex = bin2hex($binary);

        // Should be exactly 32 hex characters
        $this->assertSame(32, strlen($hex));

        // Should match UUID without dashes
        $this->assertSame(str_replace('-', '', $uuidObj->getUuid()), $hex);
    }
}
