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

use Stash\Pool;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class PoolNamespaceTest extends PoolTestBase
{
    protected function getTestPool($skipNameTest = false): Pool
    {
        $pool = parent::getTestPool();

        if (!$skipNameTest) {
            $pool->setNamespace('TestSpace');
        }

        return $pool;
    }

    public function testClearNamespacedCache(): void
    {
        $pool = $this->getTestPool(true);

        // No Namespace.
        $item = $pool->getItem('base.one');
        $item->set($this->data)->save();

        // TestNamespace.
        $pool->setNamespace('TestNamespace1');
        $item = $pool->getItem('test.one');
        $item->set($this->data)->save();

        // TestNamespace2
        $pool->setNamespace('TestNamespace2');
        $item = $pool->getItem('test.one');
        $item->set($this->data)->save();

        // Clear TestNamespace.
        $pool->setNamespace('TestNamespace1');
        static::assertTrue($pool->clear(), 'Clear succeeds with namespace selected.');

        // Return to No Namespace.
        $pool->setNamespace();
        $item = $pool->getItem('base.one');
        static::assertFalse($item->isMiss(), 'Base item exists after other namespace was cleared.');
        static::assertEquals($this->data, $item->get(), 'Base item returns data after other namespace was cleared.');

        // Clear All.
        static::assertTrue($pool->clear(), 'Clear succeeds with no namespace.');

        // Return to TestNamespace2
        $pool->setNamespace('TestNamespace2');
        $item = $pool->getItem('base.one');
        static::assertTrue($item->isMiss(), 'Namespaced item disappears after complete clear.');
    }

    public function testNamespacing(): void
    {
        $pool = $this->getTestPool(true);

        static::assertAttributeEquals(null, 'namespace', $pool, 'Namespace starts empty.');

        $pool->setNamespace('TestSpace');
        static::assertAttributeEquals('TestSpace', 'namespace', $pool, 'setNamespace sets the namespace.');
        static::assertSame('TestSpace', $pool->getNamespace(), 'getNamespace returns current namespace.');

        $pool->setNamespace();
        static::assertAttributeEquals(null, 'namespace', $pool, 'setNamespace() empties namespace.');
        static::assertNull($pool->getNamespace(), 'getNamespace returns NULL when no namespace is set.');
    }
}
