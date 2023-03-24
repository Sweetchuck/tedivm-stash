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

use Stash\Driver\FileSystem;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class FileSystemSerializerTest extends FileSystemTest
{

    protected string $driverClass = FileSystem::class;

    protected string $extension = '.pser';

    protected function getOptions(array $options = []): array
    {
        return array_merge(
            [
                'memKeyLimit' => 2,
                'encoder' => 'Serializer',
            ],
            $options,
        );
    }
}
