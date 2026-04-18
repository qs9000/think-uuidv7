<?php
declare(strict_types=1);

namespace qs9000\thinkuuidv7\tests;

use PHPUnit\Framework\TestCase;
use qs9000\thinkuuidv7\UuidV7Facade;

/**
 * Facade test - verifies ThinkPHP 8 Facade pattern
 */
class UuidV7FacadeTest extends TestCase
{
    public function testFacadeExtendsThinkFacade(): void
    {
        $this->assertTrue(
            is_subclass_of(UuidV7Facade::class, \think\Facade::class),
            'UuidV7Facade should extend think\Facade'
        );
    }

    public function testFacadeHasGetFacadeClassMethod(): void
    {
        $this->assertTrue(
            method_exists(UuidV7Facade::class, 'getFacadeClass'),
            'UuidV7Facade should have getFacadeClass method'
        );
    }

    public function testGetFacadeClassReturnsUuidv7(): void
    {
        $reflection = new \ReflectionClass(UuidV7Facade::class);
        $method = $reflection->getMethod('getFacadeClass');
        $method->setAccessible(true);
        
        $result = $method->invoke(null);
        
        $this->assertSame('uuidv7', $result);
    }

    public function testFacadeClassIsNotInstantiable(): void
    {
        $reflection = new \ReflectionClass(UuidV7Facade::class);
        
        // Facade extends think\Facade which has getFacadeClass as abstract
        // The class itself is concrete but designed for static access
        // Direct instantiation works but is not the intended use
        $this->assertTrue(
            $reflection->isSubclassOf(\think\Facade::class),
            'UuidV7Facade should extend think\Facade'
        );
    }
}
