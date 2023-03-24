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
use Stash\Interfaces\PoolInterface;
use Stash\Pool;
use Stash\Session;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 *
 * @covers \Stash\Session
 */
class SessionTest extends TestCase
{

    protected function setUp() : void
    {
        if (defined('HHVM_VERSION')
            && version_compare(HHVM_VERSION, '3.0.0', '<')
        ) {
            static::markTestSkipped('Sessions not supported on older versions of HHVM.');
        }
    }

    public function testReadAndWrite(): void
    {
        $session = $this->getSession();

        static::assertSame(
            '',
            $session->read('session_id'),
            'Empty session returns empty string.'
        );

        static::assertTrue(
            $session->write('session_id', 'session_data'),
            'Data was written to the session.'
        );
        static::assertSame(
            'session_data',
            $session->read('session_id'),
            'Active session returns session data.'
        );
    }

    public function testOpen(): void
    {
        $pool = $this->getPool();

        $sessionA = $this->getSession($pool);
        $sessionA->open('first', 'session');
        $sessionA->write('shared_id', 'session_a_data');

        $sessionB = $this->getSession($pool);
        $sessionB->open('second', 'session');
        $sessionB->write('shared_id', 'session_b_data');

        $dataA = $sessionA->read('shared_id');
        $dataB = $sessionB->read('shared_id');

        static::assertTrue(
            $dataA != $dataB,
            'Sessions with different paths do not share data.',
        );

        $pool = $this->getPool();

        $sessionA = $this->getSession($pool);
        $sessionA->open('shared_path', 'sessionA');
        $sessionA->write('shared_id', 'session_a_data');

        $sessionB = $this->getSession($pool);
        $sessionB->open('shared_path', 'sessionB');
        $sessionB->write('shared_id', 'session_b_data');

        $dataA = $sessionA->read('shared_id');
        $dataB = $sessionB->read('shared_id');

        static::assertTrue(
            $dataA != $dataB,
            'Sessions with different names do not share data.'
        );
    }

    public function testClose(): void
    {
        $session = $this->getSession();
        static::assertTrue(
            $session->close(),
            'Session was closed',
        );
    }

    public function testDestroy(): void
    {
        $session = $this->getSession();

        $session->write('session_id', 'session_data');
        $session->write('session_id', 'session_data');
        static::assertSame(
            'session_data',
            $session->read('session_id'),
            'Active session returns session data.',
        );

        static::assertTrue(
            $session->destroy('session_id'),
            'Data was removed from the session.',
        );

        static::assertSame(
            '',
            $session->read('session_id'),
            'Destroyed session returns empty string.',
        );
    }

    public function testGarbageCollect(): void
    {
        $pool = $this->getPool();

        $sessionA = $this->getSession($pool);
        $sessionA->setOptions(['ttl' => -30]);
        $sessionA->write('session_id', 'session_a_data');

        $sessionB = $this->getSession($pool);
        $sessionB->gc(0);

        $sessionC = $this->getSession($pool);
        static::assertSame(
            '',
            $sessionC->read('session_id'),
            'Purged session returns empty string.',
        );
    }

    protected function getSession(?PoolInterface $pool = null): Session
    {
        return new Session($pool ?: $this->getPool());
    }

    protected function getPool(): Pool
    {
        return new Pool();
    }
}
