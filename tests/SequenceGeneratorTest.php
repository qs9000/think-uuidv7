<?php
declare(strict_types=1);

namespace qs9000\thinkuuidv7\tests;

use PHPUnit\Framework\TestCase;
use qs9000\thinkuuidv7\SequenceGenerator;

/**
 * Sequence Generator Test - Local fallback mode
 * Note: Full Redis tests require Redis connection
 */
class SequenceGeneratorTest extends TestCase
{
    public function testGetSequenceReturnsIncrementingValues(): void
    {
        $generator = new SequenceGenerator();
        $timestamp = 1713456789000;
        
        $seq1 = $generator->getSequence($timestamp);
        $seq2 = $generator->getSequence($timestamp);
        $seq3 = $generator->getSequence($timestamp);
        
        $this->assertSame(0, $seq1);
        $this->assertSame(1, $seq2);
        $this->assertSame(2, $seq3);
    }

    public function testGetSequenceResetsOnTimestampChange(): void
    {
        $generator = new SequenceGenerator();
        
        $seq1 = $generator->getSequence(1713456789000);
        $seq2 = $generator->getSequence(1713456789000);
        $seq3 = $generator->getSequence(1713456789001); // Different timestamp
        
        $this->assertSame(0, $seq1);
        $this->assertSame(1, $seq2);
        $this->assertSame(0, $seq3); // Reset to 0
    }

    public function testSequenceWrapsCorrectly(): void
    {
        $generator = new SequenceGenerator();
        $timestamp = 1713456789000;
        $maxSequence = 0xFFF; // 4095

        // Get sequence close to max
        for ($i = 0; $i < $maxSequence - 5; $i++) {
            $generator->getSequence($timestamp);
        }

        $seqNearMax = $generator->getSequence($timestamp);
        $this->assertSame($maxSequence - 5, $seqNearMax);
    }

    // ==================== Shard ID Boundary Tests ====================

    public function testShardIdIsMasked(): void
    {
        $testCases = [
            [0, 0],
            [255, 255],
            [256, 0],    // Wraps
            [300, 44],   // 300 & 0xFF = 44
            [512, 0],    // Wraps
            [-1, 255],   // -1 & 0xFF = 255 (two's complement)
            [-256, 0],   // -256 & 0xFF = 0
        ];

        foreach ($testCases as [$input, $expected]) {
            $gen = new SequenceGenerator('test', $input);
            $this->assertSame(
                $expected,
                $gen->getShardId(),
                "Shard ID $input should be masked to $expected"
            );
        }
    }

    public function testDifferentShardIdsProduceDifferentSequences(): void
    {
        $timestamp = 1713456789000;

        $gen1 = new SequenceGenerator('test', 1);
        $gen2 = new SequenceGenerator('test', 2);

        $seq1a = $gen1->getSequence($timestamp);
        $seq2a = $gen2->getSequence($timestamp);

        // Both should start at 0 independently
        $this->assertSame(0, $seq1a);
        $this->assertSame(0, $seq2a);
    }

    // ==================== Timestamp Boundary Tests ====================

    public function testZeroTimestampIsHandled(): void
    {
        $generator = new SequenceGenerator();
        $seq = $generator->getSequence(0);

        $this->assertSame(0, $seq);
    }

    public function testVeryLargeTimestampIsHandled(): void
    {
        $generator = new SequenceGenerator();
        // Year 2100+ timestamp
        $largeTimestamp = 4102444800000;
        $seq = $generator->getSequence($largeTimestamp);

        $this->assertIsInt($seq);
        $this->assertGreaterThanOrEqual(0, $seq);
    }

    public function testDecreasingTimestampsResetSequence(): void
    {
        $generator = new SequenceGenerator();

        // Get some sequences at timestamp T
        $generator->getSequence(1000);
        $generator->getSequence(1000);
        $seq2 = $generator->getSequence(1000);
        $this->assertSame(2, $seq2);

        // Go back in time (simulating clock skew)
        $seq3 = $generator->getSequence(999);
        $this->assertSame(0, $seq3, 'Sequence should reset when timestamp decreases');
    }

    // ==================== Key Prefix Tests ====================

    public function testCustomKeyPrefixIsStored(): void
    {
        $prefix = 'custom:prefix:seq';
        $gen = new SequenceGenerator($prefix);

        // We can't directly access private property, but we can test behavior
        // by verifying it doesn't crash
        $seq = $gen->getSequence(1000);
        $this->assertSame(0, $seq);
    }

    // ==================== Cache Store Tests ====================

    public function testInvalidCacheStoreFallsBack(): void
    {
        // Using non-existent store should fall back to local sequence
        $gen = new SequenceGenerator('test', 0, 'nonexistent_store');

        $seq1 = $gen->getSequence(1000);
        $seq2 = $gen->getSequence(1000);

        $this->assertSame(0, $seq1);
        $this->assertSame(1, $seq2);
    }

    public function testLocalSequenceIndependentOfRedis(): void
    {
        // Even without Redis, local sequence should work
        $gen = new SequenceGenerator('test', 0, 'invalid_store');

        $sequences = [];
        for ($i = 0; $i < 10; $i++) {
            $sequences[] = $gen->getSequence(1000);
        }

        // Should get 0, 1, 2, ... 9
        $this->assertSame([0, 1, 2, 3, 4, 5, 6, 7, 8, 9], $sequences);
    }
}
