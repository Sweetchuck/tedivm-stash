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

use Stash\Utilities;

class NativeEncoder implements EncoderInterface
{
    public function deserialize($path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        $expiration = null;
        try {
            include($path);
        } catch (\Error $e) {
            return null;
        }

        if (!isset($loaded)) {
            return null;
        }

        if (!isset($data)) {
            $data = null;
        }

        return [
            'data' => $data,
            'expiration' => $expiration,
        ];
    }

    public function serialize(string $key, mixed $data, ?int $expiration = null): string
    {
        $storeString = '<?php ' . PHP_EOL
            . '/* Cachekey: ' . str_replace('*/', '', $key) . ' */' . PHP_EOL
            . '/* Type: ' . gettype($data) . ' */' . PHP_EOL
            . '/* Expiration: ' . (isset($expiration) ? date(DATE_W3C, $expiration) : 'none') . ' */' . PHP_EOL
            . PHP_EOL
            . PHP_EOL
            . PHP_EOL
            . '$loaded = true;' . PHP_EOL;

        if (isset($expiration)) {
            $storeString .= '$expiration = ' . $expiration . ';' . PHP_EOL;
        }

        $storeString .= PHP_EOL;

        if (is_array($data)) {
            $storeString .= "\$data = array();" . PHP_EOL;

            foreach ($data as $key => $value) {
                $dataString = $this->encode($value);
                $keyString = "'" . str_replace("'", "\\'", (string) $key) . "'";
                $storeString .= PHP_EOL;
                $storeString .= '/* Child Type: ' . gettype($value) . ' */' . PHP_EOL;
                $storeString .= "\$data[{$keyString}] = {$dataString};" . PHP_EOL;
            }
        } else {
            $dataString = $this->encode($data);
            $storeString .= '/* Type: ' . gettype($data) . ' */' . PHP_EOL;
            $storeString .= "\$data = {$dataString};" . PHP_EOL;
        }

        return $storeString;
    }

    public function getExtension(): string
    {
        return '.php';
    }

    /**
     * Finds the method of encoding that has the cheapest decode needs and encodes the data with that method.
     *
     * @param mixed $data
     */
    protected function encode($data): string
    {
        switch (Utilities::encoding($data)) {
            case 'bool':
                return $data ? 'true' : 'false';

            case 'string':
                return sprintf('"%s"', addcslashes($data, "\t\"\$\\"));

            case 'numeric':
                return (string) $data;

            default:
            case 'serialize':
                if (!is_object($data)) {
                    return var_export($data, true);
                }
                return "unserialize(base64_decode('" . base64_encode(serialize($data)) . "'))";
        }
    }
}
