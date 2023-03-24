<?php

declare(strict_types = 1);

/**
 * @file
 * This file is part of the Stash package.
 *
 * (c) Robert Hafner <tedivm@tedivm.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Stash\Test\Unit;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use PHPUnit\Framework\TestCase;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
abstract class TestBase extends TestCase
{

    use ArraySubsetAsserts;

    public static function accessProtected(object $obj, string $prop): mixed
    {
        $reflection = new \ReflectionClass($obj);
        $property = $reflection->getProperty($prop);
        $property->setAccessible(true);

        return $property->getValue($obj);
    }

    public static function assertAttributeEquals(
        $expectedValue,
        $actualAttributeName,
        $object,
        $errorMessage = "",
    ): void {
        $actualValue = static::accessProtected($object, $actualAttributeName);
        static::assertSame($expectedValue, $actualValue, $errorMessage);
    }

    public static function assertAttributeInstanceOf(
        $expectedClass,
        $actualAttributeName,
        $object,
        $errorMessage = "",
    ): void {
        $actualValue = static::accessProtected($object, $actualAttributeName);
        static::assertInstanceOf($expectedClass, $actualValue);
    }

    public static function assertAttributeInternalType(
        $expectedType,
        $actualAttributeName,
        $object,
        $errorMessage = '',
    ): void {
        $actualValue = static::accessProtected($object, $actualAttributeName);
        static::assertSame($expectedType, gettype($actualValue), $errorMessage);
    }
}
