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

use Stash\Driver\Redis;
use Stash\Test\Helper\RedisSetUp;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 *
 * @covers \Stash\Driver\Redis
 */
class RedisTest extends DriverTestBase
{
    use RedisSetUp;

    protected array $servers = [];

    protected string $driverClass = Redis::class;

    protected bool $persistence = true;

    protected function setUp(): void
    {
        $this->servers = $this->getRedisServers();

        if (!$this->setup) {
            $this->startTime = time();
            $this->expiration = $this->startTime + 3600;

            $this
                ->setUpCheckServer($this->servers['url1'], true)
                ->setUpCheckServer($this->servers['none'], false);

            if (!$this->getFreshDriver()) {
                static::markTestSkipped('Driver class unsuited for current environment');
            }

            $this->data['object'] = new \stdClass();
            $this->data['large_string'] = str_repeat('apples', (int) ceil(200000 / 6));
        }
    }

    protected function setUpCheckServer(array $server, bool $mustBeAvailable): static
    {
        $sock = @fsockopen(
            $server['host'],
            $server['port'],
            $errorCode,
            $errorMessage,
            1,
        );

        $available = (bool) $sock;
        if ($available) {
            fclose($sock);
        }

        if (!$available && $mustBeAvailable) {
            static::markTestSkipped(sprintf(
                'Redis server unavailable for testing on %s:%s',
                $server['host'],
                $server['port'],
            ));
        }

        if ($available && !$mustBeAvailable) {
            static::markTestSkipped(sprintf(
                'No server should be listening on %s:%s so that we can test for exceptions.',
                $server['host'],
                $server['port'],
            ));
        }

        return $this;
    }

    protected function getOptions(): array
    {
        $options = parent::getOptions();

        return $options + [
            'servers' => [
                [
                    'server' => $this->servers['url1']['host'],
                    'port' => $this->servers['url1']['port'],
                    'ttl' => 0.1,
                ],
            ],
        ];
    }

    protected function getInvalidOptions(): array
    {
        return [
            'servers' => [
                [
                    'server' => $this->servers['none']['host'],
                    'port' => $this->servers['none']['port'],
                    'ttl' => 0.1,
                ],
            ],
        ];
    }

    public function testBadDisconnect(): void
    {
        if (defined('HHVM_VERSION')) {
            static::markTestSkipped('This test can not run on HHVM as HHVM throws a different set of errors.');
        }

        $this->expectException(\RedisException::class);
        $driver = $this->getFreshDriver($this->getInvalidOptions());
        $driver->__destruct();
        $driver = null;
    }
}
