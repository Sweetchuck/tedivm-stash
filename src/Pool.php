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

namespace Stash;

use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stash\Exception\InvalidArgumentException;
use Stash\Driver\Ephemeral;
use Stash\Interfaces\DriverInterface;
use Stash\Interfaces\ItemInterface;
use Stash\Interfaces\PoolInterface;
use Stash\Validator\BasicValidator;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class Pool implements PoolInterface
{
    use LoggerAwareTrait;

    /**
     * The cacheDriver being used by the system. While this class handles all of the higher functions, it's the cache
     * driver here that handles all of the storage/retrieval functionality. This value is set by the constructor.
     */
    protected DriverInterface $driver;

    /**
     * Is this Pool disabled.
     */
    protected bool $isDisabled = false;

    /**
     * Default "Item" class to use for making new items.
     */
    protected string $itemClass = Item::class;

    /**
     * Current namespace, if any.
     */
    protected ?string $namespace = null;

    /**
     * The default cache invalidation method for items created by this pool object.
     */
    protected InvalidationMethod $invalidationMethod = InvalidationMethod::Precompute;

    /**
     * Argument 1 for the default cache invalidation method
     */
    protected mixed $invalidationArg1 = null;

    /**
     * Argument 2 for the default cache invalidation method
     */
    protected mixed $invalidationArg2 = null;

    protected ValidatorInterface $validator;

    /**
     * The constructor takes a Driver class which is used for persistent
     * storage. If no driver is provided then the Ephemeral driver is used by
     * default.
     */
    public function __construct(DriverInterface $driver = null)
    {
        $this->validator = new BasicValidator();
        $this->setDriver($driver ?: new Ephemeral());
    }

    /**
     * {@inheritdoc}
     */
    public function setItemClass(string $class): static
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Item class $class does not exist");
        }

        if (!in_array(ItemInterface::class, class_implements($class))) {
            throw new InvalidArgumentException("Item class $class must inherit from " . ItemInterface::class);
        }

        $this->itemClass = $class;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getItem(string $key): CacheItemInterface
    {
        $this->validator->assertKey($key);

        $keyString = trim($key, '/');
        $key = explode('/', $keyString);
        $namespace = empty($this->namespace) ? 'stash_default' : $this->namespace;

        array_unshift($key, $namespace);

        foreach ($key as $node) {
            if (!isset($node[1]) && strlen($node) < 1) {
                throw new InvalidArgumentException('Invalid or Empty Node passed to getItem constructor.');
            }
        }

        /** @var \Stash\Interfaces\ItemInterface $item */
        $item = new $this->itemClass();
        $item->setPool($this);
        $item->setKey($key, $namespace);
        $item->setInvalidationMethod($this->invalidationMethod, $this->invalidationArg1, $this->invalidationArg2);

        if ($this->isDisabled) {
            $item->disable();
        }

        if (isset($this->logger)) {
            $item->setLogger($this->logger);
        }

        return $item;
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = []): iterable
    {
        $this->validator->assertKeys($keys);

        // Temporarily cheating here by wrapping around single calls.
        $items = [];
        foreach ($keys as $key) {
            $item = $this->getItem($key);
            $items[$item->getKey()] = $item;
        }

        return new \ArrayIterator($items);
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem(string $key): bool
    {
        $this->validator->assertKey($key);

        return $this->getItem($key)->isHit();
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item): bool
    {
        return $item->save();
    }

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys): bool
    {
        $this->validator->assertKeys($keys);

        // Temporarily cheating here by wrapping around single calls.
        $results = true;
        foreach ($keys as $key) {
            $results = $this->deleteItem($key) && $results;
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem(string $key): bool
    {
        $this->validator->assertKey($key);

        return $this->getItem($key)->clear();
    }


    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        if ($this->isDisabled) {
            return false;
        }

        try {
            $driver = $this->getDriver();
            if (isset($this->namespace)) {
                $normalizedNamespace = strtolower($this->namespace);
                $results = $driver->clear(['cache', $normalizedNamespace])
                        && $driver->clear(['sp', $normalizedNamespace]);
            } else {
                $results = $driver->clear();
            }
        } catch (\Exception $e) {
            $this->isDisabled = true;
            $this->logException('Flushing Cache Pool caused exception.', $e);

            return false;
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function purge(): bool
    {
        if ($this->isDisabled) {
            return false;
        }

        try {
            $results = $this->getDriver()->purge();
        } catch (\Exception $e) {
            $this->isDisabled = true;
            $this->logException('Purging Cache Pool caused exception.', $e);

            return false;
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function setDriver(DriverInterface $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getDriver(): DriverInterface
    {
        return $this->driver;
    }

    /**
     * {@inheritdoc}
     */
    public function setNamespace(?string $namespace = null): static
    {
        $errors = $this->validateNamespace($namespace);
        if ($errors) {
            $error = reset($errors);

            throw new InvalidArgumentException($error['message'], $error['code'] ?? 0);
        }

        $this->namespace = $namespace;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    public function validateNamespace(?string $namespace): array
    {
        if ($namespace === null || ctype_alnum($namespace)) {
            return [];
        }

        return [
            [
                'message' => 'Namespace must be NULL or alphanumeric string.',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function setInvalidationMethod(
        InvalidationMethod $invalidationMethod = InvalidationMethod::Precompute,
        mixed $arg1 = null,
        mixed $arg2 = null,
    ): static {
        $this->invalidationMethod = $invalidationMethod;
        $this->invalidationArg1 = $arg1;
        $this->invalidationArg2 = $arg2;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger ?: new NullLogger();
    }

    /**
     * Logs an exception with the Logger class, if it exists.
     */
    protected function logException(string $message, \Throwable $exception): static
    {
        $this->getLogger()->critical(
            $message,
            [
                'exception' => $exception,
            ],
        );

        return $this;
    }
}
