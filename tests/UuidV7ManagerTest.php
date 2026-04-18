<?php
declare(strict_types=1);

namespace qs9000\thinkuuidv7\tests;

use PHPUnit\Framework\TestCase;
use qs9000\thinkuuidv7\UuidV7;
use qs9000\thinkuuidv7\UuidV7Generator;

/**
 * Manager Test - Tests UuidV7Manager functionality via Generator
 * Note: Full Manager tests require ThinkPHP App container
 */
class UuidV7ManagerTest extends TestCase
{
    private UuidV7Generator $generator;

    protected function setUp(): void
    {
        // Manager requires ThinkPHP App container, so we test via Generator
        $this->generator = new UuidV7Generator(10);
    }

    public function testGenerateReturnsValidUuid(): void
    {
        $uuid = $this->generator->generate();
        
        // Verify UUID format
        $this->assertTrue($this->generator->validate($uuid));
        
        // Verify it's version 7
        $hex = str_replace('-', '', $uuid);
        $this->assertSame(7, hexdec($hex[8]));
    }

    public function testGenerateIsVersion7(): void
    {
        $uuid = $this->generator->generate();
        $hex = str_replace('-', '', $uuid);
        
        $this->assertSame(7, hexdec($hex[8]));
    }

    public function testMakeReturnsUuidV7(): void
    {
        $uuidV7 = $this->generator->make();
        
        $this->assertInstanceOf(UuidV7::class, $uuidV7);
    }

    public function testMakeBatchReturnsArray(): void
    {
        $uuids = $this->generator->makeBatch(10);
        
        $this->assertIsArray($uuids);
        $this->assertCount(10, $uuids);
    }

    public function testValidateReturnsBool(): void
    {
        $uuid = $this->generator->generate();
        
        $this->assertTrue($this->generator->validate($uuid));
        $this->assertFalse($this->generator->validate('invalid'));
    }

    public function testTimestampReturnsInt(): void
    {
        $uuid = $this->generator->generate();
        $timestamp = $this->generator->timestamp($uuid);
        
        $this->assertIsInt($timestamp);
        $this->assertGreaterThan(0, $timestamp);
    }

    public function testParseReturnsUuidV7(): void
    {
        $uuid = $this->generator->generate();
        $parsed = $this->generator->parse($uuid);
        
        $this->assertInstanceOf(UuidV7::class, $parsed);
    }

    public function testParseReturnsNullForInvalid(): void
    {
        $parsed = $this->generator->parse('invalid');
        
        $this->assertNull($parsed);
    }

    public function testConfigValuesAreUsed(): void
    {
        $generator = new UuidV7Generator(99);
        $uuid = $generator->generate();
        $parsed = $generator->parse($uuid);
        
        $this->assertSame(99, $parsed->getShardId());
    }
}
