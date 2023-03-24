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

class SerializerEncoder implements EncoderInterface
{
    public function deserialize($path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        $raw = unserialize(file_get_contents($path));
        if (!is_array($raw)) {
            return null;
        }

        return [
            'data' => $raw['data'],
            'expiration' => $raw['expiration'] ?? null,
        ];
    }

    public function serialize(string $key, mixed $data, ?int $expiration = null): string
    {
        return serialize([
            'key' => $key,
            'data' => $data,
            'expiration' => $expiration
        ]);
    }

    public function getExtension(): string
    {
        return '.pser';
    }
}
