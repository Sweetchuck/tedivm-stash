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

namespace Stash\Driver;

use Stash\Interfaces\DriverInterface;
use Stash\Exception\RuntimeException;

/**
 * Abstract base class for all drivers to use.
 *
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
abstract class AbstractDriver implements DriverInterface
{

    /**
     * {@inheritdoc}
     */
    public static function isAvailable(): bool
    {
        return true;
    }

    /**
     * Initializes the driver.
     *
     * @param array $options
     *   An additional array of options to pass through to setOptions().
     *
     * @throws RuntimeException
     */
    public function __construct(array $options = [])
    {
        if (!static::isAvailable()) {
            throw new RuntimeException(get_class($this) . ' is not available.');
        }

        $this->setOptions($options);
    }

    /**
     * {@inheritdoc}
     */
    public function isPersistent(): bool
    {
        return false;
    }

    public function getDefaultOptions(): array
    {
        return [];
    }

    protected function setOptions(array $options = []): static
    {
        // Empty.
        return $this;
    }
}
