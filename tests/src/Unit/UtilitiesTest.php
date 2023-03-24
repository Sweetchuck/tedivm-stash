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

use PHPUnit\Framework\TestCase;
use Stash\Utilities;
use Stash\Driver\FileSystem;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 *
 * @covers \Stash\Utilities
 */
class UtilitiesTest extends TestCase
{

    public function casesEncodeDecodeSimple(): array
    {
        $intMax = pow(2, 31) - 1;

        return [
            'bool true' => ['bool', true, 'true'],
            'bool false' => ['bool', false, 'false'],
            'string int' => ['string', '1', '1'],
            'string float' => ['string', '1.2', '1.2'],
            'string normal' => ['string', 'String of doom!', 'String of doom!'],
            'int normal' => ['numeric', 234, 234],
            'int max' => ['numeric', $intMax, $intMax],
            'float normal' => ['numeric', 1.432, 1.432],
        ];
    }

    public function casesEncodeDecodeComplex(): array
    {
        $intOverSized = pow(2, 31);
        $objectSimple = new \stdClass();

        return [
            'int over sized' => ['serialize', pow(2, 31), serialize($intOverSized)],
            'object simple' => ['serialize', $objectSimple, serialize($objectSimple)],
            'array empty' => ['serialize', [], serialize([])],
            'array not empty' => ['serialize', ['a'], serialize(['a'])],
        ];
    }

    /**
     * @dataProvider casesEncodeDecodeSimple
     */
    public function testEncodeDecodeSimple(string $type, $value, $encoded): void
    {
        $this->assertSame($type, Utilities::encoding($value));
        $this->assertSame($encoded, Utilities::encode($value));
        $this->assertSame($value, Utilities::decode($encoded, $type));
    }

    /**
     * @dataProvider casesEncodeDecodeComplex
     */
    public function testEncodeDecodeComplex(string $type, $value, $encoded): void
    {
        $this->assertSame($type, Utilities::encoding($value));
        $this->assertEquals($encoded, Utilities::encode($value));
        $this->assertEquals($value, Utilities::decode($encoded, $type));
    }

    public function testGetBaseDirectory(): void
    {
        $filesystem = new FileSystem();
        $tmp = sys_get_temp_dir();
        $directory = Utilities::getBaseDirectory($filesystem);
        $this->assertStringStartsWith($tmp, $directory, 'Base directory is placed inside the system temp directory.');
        $this->assertTrue(is_dir($directory), 'Base Directory exists and is a directory');
        $this->assertTrue(touch($directory . 'test'), 'Base Directory is writeable.');
    }

    public function testDeleteRecursive(): void
    {
        $tmp = sys_get_temp_dir() . '/stash/';
        $dirOne = $tmp . 'test/delete/recursive';
        @mkdir($dirOne, 0770, true);
        touch($dirOne . '/test');
        touch($dirOne . '/test2');

        $dirTwo = $tmp . 'recursive/delete/test';
        @mkdir($dirTwo, 0770, true);
        touch($dirTwo . '/test3');
        touch($dirTwo . '/test4');

        $this->assertTrue(
            Utilities::deleteRecursive("$dirTwo/test3"),
            'deleteRecursive returned true when removing single file.',
        );

        $this->assertFileDoesNotExist(
            "$dirTwo/test3",
            'deleteRecursive removed single file',
        );

        $this->assertTrue(
            Utilities::deleteRecursive($tmp),
            'deleteRecursive returned true when removing directories.',
        );

        $this->assertFileDoesNotExist(
            $tmp,
            'deleteRecursive cleared out the directory',
        );

        $this->assertFalse(
            Utilities::deleteRecursive($tmp),
            'deleteRecursive returned false when passed nonexistant directory',
        );

        $tmp = sys_get_temp_dir() . '/stash/test/';
        $dirOne = $tmp . '/Test1';
        @mkdir($dirOne, 0770, true);
        $dirTwo = $tmp . '/Test2';
        @mkdir($dirTwo, 0770, true);

        Utilities::deleteRecursive($dirOne, true);
        $this->assertFileExists($dirTwo, 'deleteRecursive does not erase sibling directories.');

        Utilities::deleteRecursive($dirTwo, true);
        $this->assertFileDoesNotExist($dirTwo, 'deleteRecursive cleared out the empty parent directory');
    }

    public function testDeleteRecursiveRelativeException(): void
    {
        $this->expectException('RuntimeException');
        Utilities::deleteRecursive('../tests/fakename');
    }

    public function testDeleteRecursiveRootException(): void
    {
        $this->expectException('RuntimeException');
        Utilities::deleteRecursive('/');
    }


    public function testCheckEmptyDirectory(): void
    {
        $tmp = sys_get_temp_dir() . '/stash/';
        $dir2 = $tmp . 'emptytest/';
        @mkdir($dir2, 0770, true);

        $this->assertTrue(Utilities::checkForEmptyDirectory($dir2), 'Returns true for empty directories');
        $this->assertFalse(Utilities::checkForEmptyDirectory($tmp), 'Returns false for non-empty directories');
        Utilities::deleteRecursive($tmp);
    }

    public function testCheckFileSystemPermissionsNullException(): void
    {
        $this->expectException('RuntimeException');
        Utilities::checkFileSystemPermissions(null, octdec('0644'));
    }

    public function testCheckFileSystemPermissionsFileException(): void
    {
        $this->expectException('InvalidArgumentException');
        $tmp = sys_get_temp_dir() . '/stash/';
        $dir2 = $tmp . 'emptytest/';
        @mkdir($dir2, 0770, true);
        touch($dir2 . 'testfile');

        Utilities::checkFileSystemPermissions($dir2 . 'testfile', octdec('0644'));
    }

    public function testCheckFileSystemPermissionsUnaccessibleException(): void
    {
        if ($this->isGitHubActions()) {
            // @todo Fix it.
            $this->markTestSkipped('Unable to run on Github');
        }

        $this->expectException('InvalidArgumentException');
        Utilities::checkFileSystemPermissions('/fakedir/cache', octdec('0644'));
    }

    public function testCheckFileSystemPermissionsUnwritableException(): void
    {
        if ($this->isGitHubActions()) {
            // @todo Fix it.
            $this->markTestSkipped('Unable to run on Github');
        }

        $this->expectException('InvalidArgumentException');
        Utilities::checkFileSystemPermissions('/home', octdec('0644'));
    }

    protected function isGitHubActions(): bool
    {
        return getenv('GITHUB_ACTIONS') === 'true';
    }
}
