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

use Stash\Test\Helper\RedisSetUp;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class RedisSocketTest extends RedisTest
{

    protected function setUp() : void
    {
        $this->servers = $this->getRedisServers();

        if (!$this->setup) {
            $this->startTime = time();
            $this->expiration = $this->startTime + 3600;

            $sock = @fsockopen(
                'unix://' . $this->servers['socket']['host'],
                $this->servers['socket']['port'],
                $errorCode,
                $errorMessage,
                1,
            );
            if (!$sock) {
                static::markTestSkipped(sprintf(
                    'Redis server unavailable for testing on %s:%s Code %s; Message: %s',
                    $this->servers['socket']['host'],
                    $this->servers['socket']['port'],
                    $errorCode,
                    $errorMessage,
                ));
            }
            fclose($sock);
        }
    }

    protected function getOptions(): array
    {
        $options = parent::getOptions();

        return $options + [
            'servers' => [
                [
                    'socket' => $this->servers['socket']['host'],
                    'ttl' => 0.1,
                ],
            ],
        ];
    }
}
