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

namespace Stash\Test\Acceptance\Driver;

use Stash\Driver\FileSystem as DriverFileSystem;
use Stash\Driver\FileSystem\NativeEncoder;
use Stash\Exception\WindowsPathMaxLengthException;
use Stash\Test\Helper\PoolGetDriverStub;
use Stash\Driver\FileSystem;
use Stash\Item;
use Stash\Test\Helper\Utils as TestUtils;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class FileSystemTest extends DriverTestBase
{
    protected string $driverClass = DriverFileSystem::class;

    protected string $extension = '.php';

    protected bool $persistence = true;

    protected function getOptions(array $options = []): array
    {
        return array_merge(
            [
                'memKeyLimit' => 2,
            ],
            $options,
        );
    }

    public function testOptionKeyHashFunctionException(): void
    {
        $this->expectException(\RuntimeException::class);
        new FileSystem($this->getOptions([
            'keyHashFunction' => 'foobar_' . mt_rand()
        ]));
    }

    public function testOptionEncoderObjectException(): void
    {
        $this->expectException(\RuntimeException::class);
        $encoder = new \stdClass();
        new FileSystem($this->getOptions(['encoder' => $encoder]));
    }

    public function testOptionEncoderStringException(): void
    {
        $this->expectException(\RuntimeException::class);
        $encoder = 'stdClass';
        new FileSystem($this->getOptions(['encoder' => $encoder]));
    }

    public function testOptionEncoderAsObject(): void
    {
        $encoder = new NativeEncoder();
        $driver = new FileSystem($this->getOptions(['encoder' => $encoder]));
        static::assertNotNull($driver);
    }

    public function testOptionEncoderAsString(): void
    {
        $encoder = NativeEncoder::class;
        $driver = new FileSystem($this->getOptions(['encoder' => $encoder]));
        static::assertNotNull($driver);
    }


    public function testOptionKeyHashFunction(): void
    {
        $driver = new FileSystem(['keyHashFunction' => 'md5']);
        static::assertNotNull($driver);
    }

    /**
     * Test that the paths are created using the key hash function.
     */
    public function testOptionKeyHashFunctionDirs(): void
    {
        $hashFunctions = [
            TestUtils::class . '::myHash',
            'strrev',
            'md5',
            function ($value) {
                return abs(crc32($value));
            },
        ];
        $paths = ['one', 'two', 'three', 'four'];

        foreach ($hashFunctions as $hashFunction) {
            $driver = new FileSystem($this->getOptions([
                'keyHashFunction' => $hashFunction,
                'path' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'stash',
                'dirSplit' => 1,
            ]));

            $rand = str_repeat(uniqid(), 32);

            $item = new Item();

            $poolStub = new PoolGetDriverStub();
            $poolStub->setDriver($driver);
            $item->setPool($poolStub);
            $item->setKey($paths);
            $item->set($rand)->save();

            $allPaths = array_merge(['cache'], $paths);
            $predicted = implode(
                DIRECTORY_SEPARATOR,
                [
                    sys_get_temp_dir(),
                    'stash',
                    implode(DIRECTORY_SEPARATOR, array_map($hashFunction, $allPaths)),
                ],
            );
            $predicted .= $this->extension;

            static::assertFileExists($predicted);
        }
    }

    /**
     * Test if the dir split functionality cleanly finished an uneven split.
     *
     * e.g. when requested to split "one" into 2, it should split it in "o" and "ne".
     */
    public function testDirSplitFinishesCorrectly(): void
    {
        $paths = ['one', 'two', 'three', 'four'];
        $outputPaths = ['o', 'ne', 't', 'wo', 'th', 'ree', 'fo', 'ur'];

        $driver = new FileSystem($this->getOptions([
             'keyHashFunction' => function ($key) {
                 return $key;
             },
             'path' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'stash',
             'dirSplit' => 2,
        ]));

        $rand = str_repeat(uniqid(), 32);

        $item = new Item();

        $poolStub = new PoolGetDriverStub();
        $poolStub->setDriver($driver);
        $item->setPool($poolStub);
        $item->setKey($paths);
        $item->set($rand)->save();

        $allPaths = array_merge(['ca', 'che'], $outputPaths);
        $predicted = sys_get_temp_dir().
          DIRECTORY_SEPARATOR.
          'stash'.
          DIRECTORY_SEPARATOR.
          implode(DIRECTORY_SEPARATOR, $allPaths).
          $this->extension;

        static::assertFileExists($predicted);
    }

    /**
     * Test creation of directories with long paths (Windows issue)
     *
     * Regression test for https://github.com/tedivm/Stash/issues/61
     *
     * There are currently no short term plans to allow long paths in PHP windows
     * http://www.mail-archive.com/internals@lists.php.net/msg62672.html
     *
     */
    public function testLongPathFolderCreation(): void
    {
        if (strtolower(substr(PHP_OS, 0, 3)) !== 'win') {
            $this->markTestSkipped('This test can only occur on Windows based systems.');
        }

        $cachePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'stash';

        $driver = new FileSystem($this->getOptions([
            'keyHashFunction' => 'Stash\Test\Driver\strdup',
            'path' => $cachePath,
            'dirSplit' => 1
        ]));
        $key = [];

        // MAX_PATH is 260 - http://msdn.microsoft.com/en-us/library/aa365247(VS.85).aspx
        while (strlen($cachePath . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $key)) < 259) {
            // 32 character string typical of an md5 sum.
            $key[] = 'abcdefghijklmnopqrstuvwxyz123456';
        }
        $key[] = 'abcdefghilkmnopqrstuvwxyz123456';
        $this->expiration = time() + 3600;

        $this->expectException(\Stash\Exception\WindowsPathMaxLengthException::class);
        $driver->storeData($key, 'test', $this->expiration);
    }

    /**
     * Test creation of file with long paths (Windows issue)
     *
     * Regression test for https://github.com/tedivm/Stash/issues/61
     *
     * There are currently no short term plans to allow long paths in PHP windows
     * http://www.mail-archive.com/internals@lists.php.net/msg62672.html
     *
     */
    public function testLongPathFileCreation(): void
    {
        if (strtolower(substr(PHP_OS, 0, 3)) !== 'win') {
            static::markTestSkipped('This test can only occur on Windows based systems.');
        }

        $cachePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'stash';

        $driver = new FileSystem($this->getOptions([
            'keyHashFunction' => 'Stash\Test\Driver\strdup',
            'path' => $cachePath,
            'dirSplit' => 1
        ]));
        $key = [];

        // MAX_PATH is 260 - http://msdn.microsoft.com/en-us/library/aa365247(VS.85).aspx
        while (strlen($cachePath . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $key)) < 259) {
            // 32 character string typical of an md5 sum
            $key[] = 'abcdefghijklmnopqrstuvwxyz123456';
        }
        $this->expiration = time() + 3600;

        $this->expectException(WindowsPathMaxLengthException::class);
        $driver->storeData($key, 'test', $this->expiration);
    }
}
