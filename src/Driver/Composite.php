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

use Stash;
use Stash\Exception\RuntimeException;
use Stash\Exception\InvalidArgumentException;
use Stash\Interfaces\DriverInterface;

/**
 * Composite is a wrapper around one or more StashDrivers, allowing faster caching engines with size or
 * persistence limitations to be backed up by slower but larger and more persistent caches. There are no artificial
 * limits placed on how many drivers can be staggered.
 *
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class Composite extends AbstractDriver
{
    /**
     * The drivers this driver encapsulates.
     *
     * @var \Stash\Interfaces\DriverInterface[]
     */
    protected array $drivers = [];

    /**
     * Takes an array of Drivers.
     *
     * @throws \Stash\Exception\RuntimeException
     */
    protected function setOptions(array $options = []): static
    {
        $options += $this->getDefaultOptions();

        if (!isset($options['drivers'])) {
            throw new RuntimeException('One or more secondary drivers are required.');
        }

        if (!is_array($options['drivers'])) {
            throw new InvalidArgumentException('Drivers option requires an array.');
        }

        if (count($options['drivers']) < 1) {
            throw new RuntimeException('One or more secondary drivers are required.');
        }

        $this->drivers = array();
        foreach ($options['drivers'] as $driver) {
            if (!is_object($driver) || !($driver instanceof DriverInterface)) {
                continue;
            }
            $this->drivers[] = $driver;
        }

        if (count($this->drivers) < 1) {
            throw new RuntimeException('None of the secondary drivers can be enabled.');
        }

        return $this;
    }

    /**
     * This starts with the first driver and keeps trying subsequent drivers until a result is found. It then fills
     * in the result to any of the drivers that failed to retrieve it.
     *
     * {@inheritdoc}
     */
    public function getData(array $key): array
    {
        /* @var \Stash\Interfaces\DriverInterface[] $failedDrivers */
        $failedDrivers = [];
        $return = false;
        foreach ($this->drivers as $driver) {
            if ($return = $driver->getData($key)) {
                $failedDrivers = array_reverse($failedDrivers);
                foreach ($failedDrivers as $failedDriver) {
                    $failedDriver->storeData($key, $return['data'], $return['expiration']);
                }

                break;
            } else {
                $failedDrivers[] = $driver;
            }
        }

        return $return;
    }

    /**
     * This function stores the passed data on all drivers, starting with the most "distant" one (the last fallback) so
     * in order to prevent race conditions.
     *
     * {@inheritdoc}
     */
    public function storeData(array $key, mixed $data, int $expiration): bool
    {
        return $this->actOnAll('storeData', [$key, $data, $expiration]);
    }

    /**
     * This function clears the passed key on all drivers, starting with the most "distant" one (the last fallback) so
     * in order to prevent race conditions.
     *
     * {@inheritdoc}
     */
    public function clear(?array $key = null): bool
    {
        return $this->actOnAll('clear', [$key]);
    }

    /**
     * This function runs the purge operation on all drivers.
     *
     * {@inheritdoc}
     */
    public function purge(): bool
    {
        return $this->actOnAll('purge');
    }

    /**
     * {@inheritdoc}
     */
    public function isPersistent(): bool
    {
        // If one of the drivers is persistent, then this should be marked as persistent as well. This does not
        // require all of the drivers to be persistent.
        foreach ($this->drivers as $driver) {
            if ($driver->isPersistent()) {
                return true;
            }
        }
        return false;
    }

    /**
     * This function runs the suggested action on all drivers in the reverse order, passing arguments when called for.
     *
     * @param string $action
     *   Allowed values: purge|clear|storeData
     * @param array $args
     */
    protected function actOnAll(string $action, array $args = []): bool
    {
        $drivers = array_reverse($this->drivers);
        /* @var DriverInterface[] $drivers */

        $return = true;
        $results = false;
        foreach ($drivers as $driver) {
            switch ($action) {
                case 'purge':
                    $results = $driver->purge();
                    break;
                case 'clear':
                    $results = $driver->clear($args[0]);
                    break;
                case 'storeData':
                    $results = $driver->storeData($args[0], $args[1], $args[2]);
                    break;
            }
            $return = $return && $results;
        }

        return $return;
    }
}
