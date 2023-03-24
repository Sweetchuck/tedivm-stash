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
use Stash\Exception\InvalidArgumentException;

/**
 * The ephemeral class exists to assist with testing the main Stash class. Since this is a very minimal driver we can
 * test Stash without having to worry about underlying problems interfering.
 *
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class Ephemeral extends AbstractDriver
{
    /**
     * Contains the cached data.
     */
    protected array $store = [];

    protected int $maxItems = 0;

    public function getDefaultOptions(): array
    {
        $options = parent::getDefaultOptions();

        return $options + [
            'maxItems' => 0,
        ];
    }

    /**
     * Allows setting maxItems.
     *
     * @param array $options
     *   If maxItems is 0, infinite items will be cached.
     */
    protected function setOptions(array $options = []): static
    {
        $options += $this->getDefaultOptions();

        if (array_key_exists('maxItems', $options)) {
            $maxItems = $options['maxItems'];
            if (!is_int($maxItems) || $maxItems < 0) {
                throw new InvalidArgumentException(
                    'maxItems must be a positive integer.'
                );
            }
            $this->maxItems = $maxItems;
            if ($this->maxItems > 0 && count($this->store) > $this->maxItems) {
                $this->evict(count($this->store) - $this->maxItems);
            }
        }

        return $this;
    }

    /**
     * Evicts the first $count items that were added to the store.
     *
     * Subclasses could implement more advanced eviction policies.
     */
    protected function evict(int $count): static
    {
        $this->store = array_slice($this->store, $count);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getData(array $key): array
    {
        $key = $this->getKeyIndex($key);

        return $this->store[$key] ?? [];
    }

    /**
     * Converts the key array into a passed function.
     *
     * @param string[] $key
     */
    protected function getKeyIndex(array $key): string
    {
        $index = '';
        foreach ($key as $value) {
            $index .= str_replace('#', '#:', $value) . '#';
        }

        return $index;
    }

    /**
     * {@inheritdoc}
     */
    public function storeData(array $key, mixed $data, int $expiration): bool
    {
        if ($this->maxItems > 0 && count($this->store) >= $this->maxItems) {
            $this->evict((count($this->store) + 1) - $this->maxItems);
        }

        $this->store[$this->getKeyIndex($key)] = [
            'data' => $data,
            'expiration' => $expiration,
        ];

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(?array $key = null): bool
    {
        if (!$key) {
            $this->store = [];

            return true;
        }

        $clearIndex = $this->getKeyIndex($key);
        foreach ($this->store as $index => $data) {
            if (str_starts_with($index, $clearIndex)) {
                unset($this->store[$index]);
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function purge(): bool
    {
        $now = time();
        foreach ($this->store as $index => $data) {
            if ($data['expiration'] <= $now) {
                unset($this->store[$index]);
            }
        }

        return true;
    }
}
