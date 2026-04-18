<?php
declare(strict_types=1);

namespace qs9000\thinkuuidv7\tests;

use PHPUnit\Framework\TestCase;
use qs9000\thinkuuidv7\UuidV7;
use qs9000\thinkuuidv7\UuidV7Facade;
use qs9000\thinkuuidv7\UuidV7Generator;
use qs9000\thinkuuidv7\UuidV7Interface;
use qs9000\thinkuuidv7\UuidV7Manager;
use qs9000\thinkuuidv7\SequenceGenerator;

/**
 * Interface compliance test - verifies UuidV7Interface contract
 */
class UuidV7InterfaceTest extends TestCase
{
    public function testUuidV7GeneratorImplementsInterface(): void
    {
        $generator = new UuidV7Generator();
        
        $this->assertInstanceOf(UuidV7Interface::class, $generator);
    }

    public function testAllInterfaceMethodsExist(): void
    {
        $generator = new UuidV7Generator();
        $interfaceMethods = [
            'generate',
            'make',
            'makeBatch',
            'parse',
            'validate',
            'timestamp',
            'datetime',
        ];
        
        foreach ($interfaceMethods as $method) {
            $this->assertTrue(
                method_exists($generator, $method),
                "UuidV7Generator should have method: $method"
            );
        }
    }

    public function testInterfaceMethodSignatures(): void
    {
        $reflection = new \ReflectionClass(UuidV7Interface::class);
        
        // generate(?string $driver = null): string
        $method = $reflection->getMethod('generate');
        $this->assertSame('string', $method->getReturnType()->getName());
        
        // make(?string $driver = null): UuidV7
        $method = $reflection->getMethod('make');
        $this->assertSame(UuidV7::class, $method->getReturnType()->getName());
        
        // makeBatch(int $count, ?string $driver = null): array
        $method = $reflection->getMethod('makeBatch');
        $this->assertSame('array', $method->getReturnType()->getName());
        
        // parse(string $uuid, ?string $driver = null): ?UuidV7
        $method = $reflection->getMethod('parse');
        $returnType = $method->getReturnType();
        $this->assertTrue($returnType->allowsNull());
        
        // validate(string $uuid, ?string $driver = null): bool
        $method = $reflection->getMethod('validate');
        $this->assertSame('bool', $method->getReturnType()->getName());
        
        // timestamp(string $uuid, ?string $driver = null): int
        $method = $reflection->getMethod('timestamp');
        $this->assertSame('int', $method->getReturnType()->getName());
        
        // datetime(string $uuid, ?string $driver = null): DateTimeImmutable
        $method = $reflection->getMethod('datetime');
        $this->assertSame(\DateTimeImmutable::class, $method->getReturnType()->getName());
    }
}
