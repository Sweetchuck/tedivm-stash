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

namespace Stash\Driver\FileSystem;

interface EncoderInterface
{
    public function deserialize(string $path): ?array;

    public function serialize(string $key, mixed $data): string;

    public function getExtension(): string;
}
