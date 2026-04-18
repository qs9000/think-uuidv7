<?php
declare(strict_types=1);

namespace qs9000\thinkuuidv7\tests;

use PHPUnit\Framework\TestCase;
use qs9000\thinkuuidv7\Exception\UuidV7Exception;
use qs9000\thinkuuidv7\UuidV7;
use qs9000\thinkuuidv7\UuidV7Generator;

class UuidV7GeneratorTest extends TestCase
{
    private UuidV7Generator $generator;

    protected function setUp(): void
    {
        $this->generator = new UuidV7Generator(42);
    }

    // ==================== UUIDv7 Structure Tests ====================

    public function testGeneratedUuidHasCorrectFormat(): void
    {
        $uuid = $this->generator->generate();
        
        // UUIDv7 format: xxxxxxxx-xxxx-7xxx-yxxx-xxxxxxxxxxxx
        // Groups: 8-4-4-4-12 hex chars
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $uuid
        );
    }

    public function testGeneratedUuidHasVersion7(): void
    {
        $uuid = $this->generator->generate();
        $hex = str_replace('-', '', $uuid);
        
        // Version is in position 8 (first nibble of g2)
        $version = hexdec($hex[8]);
        
        $this->assertSame(7, $version, 'UUID version must be 7');
    }

    public function testGeneratedUuidHasCorrectVariant(): void
    {
        $uuid = $this->generator->generate();
        $hex = str_replace('-', '', $uuid);
        
        // Variant is in position 12 (first nibble of g3), valid values are 8-b
        $variant = hexdec($hex[12]);
        
        $this->assertGreaterThanOrEqual(8, $variant);
        $this->assertLessThanOrEqual(11, $variant, 'UUID variant must be 8, 9, a, or b');
    }

    // ==================== Timestamp Tests ====================

    public function testTimestampIsExtractedCorrectly(): void
    {
        $uuid = $this->generator->generate();
        $extractedTimestamp = $this->generator->timestamp($uuid);
        
        // Timestamp should be within a reasonable range (after year 2020)
        $this->assertGreaterThan(1577836800000, $extractedTimestamp);
        
        // Timestamp should not be in the distant future (more than 1 hour ahead)
        $now = (int) floor(microtime(true) * 1000);
        $this->assertLessThan($now + 3600000, $extractedTimestamp);
    }

    public function testDatetimeIsValidDateTimeImmutable(): void
    {
        $uuid = $this->generator->generate();
        $datetime = $this->generator->datetime($uuid);
        
        $this->assertInstanceOf(\DateTimeImmutable::class, $datetime);
    }

    public function testDatetimeConsistency(): void
    {
        $uuid = $this->generator->generate();
        $timestamp = $this->generator->timestamp($uuid);
        $datetime = $this->generator->datetime($uuid);
        
        $seconds = (int) floor($timestamp / 1000);
        $this->assertSame($seconds, $datetime->getTimestamp());
    }

    // ==================== Uniqueness Tests ====================

    public function testGenerateProducesUniqueUuids(): void
    {
        $uuids = [];
        for ($i = 0; $i < 100; $i++) {
            $uuids[] = $this->generator->generate();
        }
        
        $uniqueUuids = array_unique($uuids);
        $this->assertCount(100, $uniqueUuids, 'All generated UUIDs should be unique');
    }

    public function testMakeBatchProducesUniqueUuids(): void
    {
        $uuids = $this->generator->makeBatch(100);
        
        $uniqueUuids = array_unique($uuids);
        $this->assertCount(100, $uniqueUuids, 'All batch UUIDs should be unique');
    }

    public function testBatchCountMatches(): void
    {
        $counts = [1, 5, 10, 50, 100];
        
        foreach ($counts as $count) {
            $uuids = $this->generator->makeBatch($count);
            $this->assertCount($count, $uuids, "Batch count should be $count");
        }
    }

    public function testBatchReturnsEmptyForZeroCount(): void
    {
        $uuids = $this->generator->makeBatch(0);
        
        $this->assertSame([], $uuids);
    }

    public function testBatchReturnsEmptyForNegativeCount(): void
    {
        $uuids = $this->generator->makeBatch(-1);
        
        $this->assertSame([], $uuids);
    }

    // ==================== Chronological Order Tests ====================

    public function testBatchUuidsAreChronologicallyOrdered(): void
    {
        $uuids = $this->generator->makeBatch(50);
        
        for ($i = 1; $i < count($uuids); $i++) {
            $prevTimestamp = $this->generator->timestamp($uuids[$i - 1]);
            $currTimestamp = $this->generator->timestamp($uuids[$i]);
            
            // UUIDs should be in non-decreasing timestamp order
            // (strictly increasing or equal within same millisecond)
            $this->assertGreaterThanOrEqual(
                $prevTimestamp,
                $currTimestamp,
                "UUID[$i] timestamp should be >= UUID[" . ($i - 1) . "] timestamp"
            );
        }
    }

    public function testMultipleBatchesMaintainOrder(): void
    {
        $batch1 = $this->generator->makeBatch(10);
        $batch2 = $this->generator->makeBatch(10);
        
        // All batch2 UUIDs should be >= last batch1 UUID (or strictly > if different timestamp)
        $lastBatch1Timestamp = $this->generator->timestamp(end($batch1));
        $firstBatch2Timestamp = $this->generator->timestamp($batch2[0]);
        
        $this->assertGreaterThanOrEqual($lastBatch1Timestamp, $firstBatch2Timestamp);
    }

    // ==================== Validation Tests ====================

    public function testValidateReturnsTrueForValidUuid(): void
    {
        $uuid = $this->generator->generate();
        
        $this->assertTrue($this->generator->validate($uuid));
    }

    public function testValidateReturnsFalseForInvalidFormat(): void
    {
        $invalidUuids = [
            'not-a-uuid',
            '12345',
            '',
            'gggggggg-gggg-gggg-gggg-gggggggggggg', // g is not valid hex
            '0191a51a-b2c3-7d89-0123-456789abcde',  // too short
            '0191a51a-b2c3-7d89-0123-456789abcdefg', // too long
        ];
        
        foreach ($invalidUuids as $uuid) {
            $this->assertFalse(
                $this->generator->validate($uuid),
                "Should return false for: $uuid"
            );
        }
    }

    public function testValidateReturnsFalseForWrongVersion(): void
    {
        // UUIDv1 format: xxxxxxxx-xxxx-1xxx-yxxx-xxxxxxxxxxxx
        $uuidv1 = '0191a51a-b2c1-7d89-0123-456789abcdef'; // position 8 should be 1 for v1
        
        // This is technically v7 with wrong version nibble
        $this->assertFalse($this->generator->validate($uuidv1));
    }

    public function testValidateReturnsFalseForWrongVariant(): void
    {
        // UUID with variant in position 12 = 0 (invalid)
        // Format: xxxxxxxx-xxxx-7xxx-0xxx-xxxxxxxxxxxx
        $uuid = '0191a51a-b2c3-7000-0123-456789abcdef';
        
        $this->assertFalse($this->generator->validate($uuid));
    }

    // ==================== Parse Tests ====================

    public function testParseReturnsUuidV7Object(): void
    {
        $uuid = $this->generator->generate();
        $parsed = $this->generator->parse($uuid);
        
        $this->assertInstanceOf(UuidV7::class, $parsed);
        $this->assertSame($uuid, $parsed->getUuid());
    }

    public function testParseReturnsNullForInvalidUuid(): void
    {
        $parsed = $this->generator->parse('invalid-uuid');
        
        $this->assertNull($parsed);
    }

    public function testParsePreservesTimestamp(): void
    {
        $uuid = $this->generator->generate();
        $timestamp = $this->generator->timestamp($uuid);
        $parsed = $this->generator->parse($uuid);
        
        $this->assertSame($timestamp, $parsed->getTimestampMs());
    }

    // ==================== Timestamp Extraction Tests ====================

    public function testTimestampThrowsForInvalidUuid(): void
    {
        $this->expectException(UuidV7Exception::class);
        $this->expectExceptionMessage('Invalid UUIDv7 format');
        
        $this->generator->timestamp('invalid-uuid');
    }

    public function testDatetimeThrowsForInvalidUuid(): void
    {
        $this->expectException(UuidV7Exception::class);
        $this->expectExceptionMessage('Invalid UUIDv7 format');
        
        $this->generator->datetime('invalid-uuid');
    }

    // ==================== Shard ID Tests ====================

    public function testCustomShardIdIsEncoded(): void
    {
        $generator = new UuidV7Generator(123);
        $uuid = $generator->generate();
        $parsed = $generator->parse($uuid);
        
        $this->assertSame(123, $parsed->getShardId());
    }

    public function testShardIdRangeIsValid(): void
    {
        $testShardIds = [0, 1, 127, 128, 200, 255];
        
        foreach ($testShardIds as $shardId) {
            $generator = new UuidV7Generator($shardId);
            $uuid = $generator->generate();
            $parsed = $generator->parse($uuid);
            
            $this->assertSame($shardId, $parsed->getShardId());
        }
    }

    public function testSetShardIdUpdatesGenerator(): void
    {
        $this->generator->setShardId(99);
        $uuid = $this->generator->generate();
        $parsed = $this->generator->parse($uuid);
        
        $this->assertSame(99, $parsed->getShardId());
    }

    public function testShardIdOverflowIsMasked(): void
    {
        $generator = new UuidV7Generator(300); // 300 & 0xFF = 44
        $uuid = $generator->generate();
        $parsed = $generator->parse($uuid);
        
        $this->assertSame(44, $parsed->getShardId());
    }

    // ==================== Make Object Tests ====================

    public function testMakeReturnsUuidV7Object(): void
    {
        $uuidV7 = $this->generator->make();
        
        $this->assertInstanceOf(UuidV7::class, $uuidV7);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $uuidV7->getUuid()
        );
    }

    public function testMakeReturnsValidUuidV7(): void
    {
        // Test that make() returns a valid UuidV7 object
        $uuidV7 = $this->generator->make();
        
        // Verify the UUID is valid
        $this->assertTrue($this->generator->validate($uuidV7->getUuid()));
        
        // Verify timestamp is valid (non-negative)
        $timestamp = $this->generator->timestamp($uuidV7->getUuid());
        $this->assertGreaterThanOrEqual(0, $timestamp);
    }

    // ==================== Edge Cases ====================

    public function testVeryLargeBatchSize(): void
    {
        $uuids = $this->generator->makeBatch(1000);
        
        $this->assertCount(1000, $uuids);
        $uniqueUuids = array_unique($uuids);
        $this->assertCount(1000, $uniqueUuids);
    }

    public function testSameTimestampMultipleUuids(): void
    {
        // Generate multiple UUIDs quickly to try to hit same millisecond
        $uuids = $this->generator->makeBatch(1000);
        
        // All UUIDs should be valid and unique
        foreach ($uuids as $uuid) {
            $this->assertTrue($this->generator->validate($uuid));
        }
        
        $uniqueUuids = array_unique($uuids);
        $this->assertCount(1000, $uniqueUuids);
    }

    // ==================== Driver Parameter Tests ====================

    public function testGenerateAcceptsDriverParameter(): void
    {
        // Without Redis generator, driver param should be ignored
        $uuid = $this->generator->generate('redis');
        
        $this->assertTrue(
            $this->generator->validate($uuid),
            "Generated UUID should be valid: $uuid"
        );
    }

    public function testMakeAcceptsDriverParameter(): void
    {
        $uuidV7 = $this->generator->make('redis');
        
        $this->assertInstanceOf(UuidV7::class, $uuidV7);
    }

    public function testMakeBatchAcceptsDriverParameter(): void
    {
        $uuids = $this->generator->makeBatch(10, 'redis');
        
        $this->assertCount(10, $uuids);
    }

    public function testValidateAcceptsDriverParameter(): void
    {
        $uuid = $this->generator->generate();
        
        $this->assertTrue($this->generator->validate($uuid, 'redis'));
    }

    public function testTimestampAcceptsDriverParameter(): void
    {
        $uuid = $this->generator->generate();
        $timestamp = $this->generator->timestamp($uuid, 'redis');
        
        $this->assertIsInt($timestamp);
        $this->assertGreaterThan(0, $timestamp);
    }

    public function testDatetimeAcceptsDriverParameter(): void
    {
        $uuid = $this->generator->generate();
        $datetime = $this->generator->datetime($uuid, 'redis');

        $this->assertInstanceOf(\DateTimeImmutable::class, $datetime);
    }

    // ==================== Sequence Number Boundary Tests ====================

    public function testSequenceNumberIsEncodedInUuid(): void
    {
        // Generate multiple UUIDs and verify they have different rand_a/rand_b parts
        $uuids = $this->generator->makeBatch(10);
        $sequences = [];

        foreach ($uuids as $uuid) {
            $hex = str_replace('-', '', $uuid);
            // Extract rand_a from g2 (lower 12 bits of g2)
            $g2 = hexdec(substr($hex, 8, 4));
            $rand_a = $g2 & 0x0FFF;

            // Extract rand_b high bits from g3
            $g3 = hexdec(substr($hex, 13, 4));
            $rand_b_shifted = $g3 & 0x0FFF;
            $rand_b = $rand_b_shifted << 2;

            // Combine to get sequence (high 4 bits from rand_a, low 8 bits from rand_b)
            $seq_high = $rand_a & 0x0F;
            $seq_low = ($rand_b >> 6) & 0xFF;
            $sequences[] = ($seq_high << 8) | $seq_low;
        }

        // Sequences should be unique within same timestamp batch
        $uniqueSequences = array_unique($sequences);
        $this->assertGreaterThan(1, count($uniqueSequences), 'Should have multiple sequences');
    }

    public function testShardIdEncodedInHighBitsOfRandA(): void
    {
        // Test that shard ID is encoded in high 8 bits of rand_a
        $shardIds = [0, 1, 127, 128, 255];

        foreach ($shardIds as $shardId) {
            $gen = new UuidV7Generator($shardId);
            $uuid = $gen->generate();
            $parsed = $gen->parse($uuid);

            $this->assertSame(
                $shardId,
                $parsed->getShardId(),
                "Shard ID $shardId should be preserved"
            );
        }
    }

    public function testUuidVersion7NibbleIsCorrect(): void
    {
        $uuid = $this->generator->generate();
        $hex = str_replace('-', '', $uuid);

        // Position 8 (0-indexed) is the version nibble
        // Should always be 7 (binary 0111)
        $versionNibble = hexdec($hex[8]);

        $this->assertSame(7, $versionNibble, 'Version nibble must be 7');
    }

    public function testUuidVariantBitsAreCorrect(): void
    {
        $uuid = $this->generator->generate();
        $hex = str_replace('-', '', $uuid);

        // Position 12 (0-indexed) is the variant nibble
        // Variant bits (binary 10xx) = decimal 8, 9, 10, or 11 (8-b)
        $variantNibble = hexdec($hex[12]);

        $this->assertGreaterThanOrEqual(8, $variantNibble);
        $this->assertLessThanOrEqual(11, $variantNibble);
        $this->assertSame(
            $variantNibble & 0xC, // Top 2 bits should be 10
            0x8,
            'Variant top 2 bits must be 10'
        );
    }

    public function testTimestampBitsOccupiedCorrectPositions(): void
    {
        $uuid = $this->generator->generate();
        $hex = str_replace('-', '', $uuid);

        // g1 (positions 0-7) = high 32 bits of timestamp
        // g2 (positions 8-11) + version nibble = low 16 bits of timestamp

        // Verify g1 is non-zero (timestamp high bits)
        $g1 = substr($hex, 0, 8);
        $g1Value = hexdec($g1);

        // g1 should represent recent timestamp (year 2020+)
        $this->assertGreaterThan(0x5F, $g1Value, 'Timestamp high bits should indicate year >= 2020');

        // Verify version nibble in g2
        $versionNibble = hexdec($hex[8]);
        $this->assertSame(7, $versionNibble & 0xF, 'Version should be 7');
    }

    public function testRandomBitsAreNonZero(): void
    {
        $uuid = $this->generator->generate();
        $hex = str_replace('-', '', $uuid);

        // g3 (positions 12-15): variant(4) + rand_b_high(12)
        $g3 = hexdec(substr($hex, 13, 4));
        // g4 (positions 16-19): rand_b_low(8) + rand_c_high(8)
        $g4 = hexdec(substr($hex, 16, 4));
        // g5 (positions 20-31): rand_c (full 48 bits)
        $g5 = hexdec(substr($hex, 20, 12));

        // At least one of the random parts should be non-zero
        $randomSum = $g3 + $g4 + $g5;
        $this->assertGreaterThan(0, $randomSum, 'Random bits should have entropy');
    }
}
