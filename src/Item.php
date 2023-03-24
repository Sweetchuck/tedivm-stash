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

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stash\Exception\Exception;
use Stash\Interfaces\DriverInterface;
use Stash\Interfaces\ItemInterface;
use Stash\Interfaces\PoolInterface;

/**
 * Stash caches data that has a high generation cost, such as template
 * preprocessing or code that requires a database connection.
 * This class can store any native php datatype, as long as it can be serialized
 * (so when creating classes that you wish to store instances of, remember the
 * __sleep and __wake magic functions).
 *
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class Item implements ItemInterface
{
    use LoggerAwareTrait;

    /**
     * This is the default time, in seconds, that objects are cached for.
     *
     * Default value: five days.
     */
    public static int $cacheTime = 432000;

    /**
     * Disables the cache system wide.
     *
     * It is used internally when the storage engine fails or if the cache is
     * being cleared. This differs from the cacheEnabled property in that it
     * affects all instances of the cache, not just one.
     */
    public static bool $runtimeDisable = false;

    /**
     * Used internally to mark the class as disabled. Unlike the static runtimeDisable flag this is effective only for
     * the current instance.
     */
    protected bool $cacheEnabled = true;

    /**
     * Contains a list of default arguments for when users do not supply them.
     */
    protected array $defaults = [
        // Time, in seconds, before expiration.
        'precompute_time' => 40,

        // Time, in microseconds, to sleep.
        'sleep_time' => 500,

        // Number of times to sleep, wake up, and recheck cache.
        'sleep_attempts' => 1,

        // How long a stampede flag will be acknowledged.
        'stampede_ttl' => 30,
    ];

    /**
     * The data to store in the cache
     */
    protected mixed $data = null;

    /**
     * When the cache for this item expires
     */
    protected null|\DateTimeInterface $expiration = null;

    protected InvalidationMethod $invalidationMethod = InvalidationMethod::Precompute;

    protected mixed $invalidationArg1 = null;

    protected mixed $invalidationArg2 = null;

    /**
     * The identifier for the item being cached. It is set through the setupKey function.
     *
     * @var string[]
     *   One dimensional array representing the location of a cached object.
     */
    protected array $key = [];

    /**
     * A serialized version of the key, used primarily used as the index in various arrays.
     */
    protected string $keyString = '';

    /**
     * Marks whether or not stampede protection is enabled for this instance of Stash.
     */
    protected bool $stampedeRunning = false;

    /**
     * The Pool that spawned this instance of the Item.
     */
    protected PoolInterface $pool;

    /**
     * The cacheDriver being used by the system. While this class handles all of the higher functions, it's the cache
     * driver here that handles all of the storage/retrieval functionality. This value is set by the constructor.
     */
    protected DriverInterface $driver;

    /**
     * Defines the namespace the item lives in.
     */
    protected ?string $namespace = null;

    /**
     * This is a flag to see if a valid response is returned. It is set by the getData function and is used by the
     * isMiss function.
     */
    protected ?bool $isHit = null;

    /**
     * This clears out any locks that are present if this Item is prematurely destructed.
     */
    public function __destruct()
    {
        if ($this->stampedeRunning === true) {
            $spKey = $this->key;
            $spKey[0] = 'sp';
            $this->driver->clear($spKey);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setPool(PoolInterface $pool): static
    {
        $this->pool = $pool;
        $this->driver = $pool->getDriver();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setKey(array $key, $namespace = null): static
    {
        $this->namespace = $namespace;

        $keyStringTmp = $key;
        if (isset($this->namespace)) {
            array_shift($keyStringTmp);
        }

        $this->keyString = implode('/', $keyStringTmp);

        // We implant the namespace "cache" to the front of every stash object's key.
        // This allows us to segment off the user data, and use other 'namespaces'
        // for internal purposes.
        array_unshift($key, 'cache');
        $this->key = array_map('strtolower', $key);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function disable(): bool
    {
        $this->cacheEnabled = false;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getKey(): string
    {
        return $this->keyString;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        try {
            return $this->executeClear();
        } catch (Exception $e) {
            $this->logException('Clearing cache caused exception.', $e);
            $this->disable();

            return false;
        }
    }

    protected function executeClear(): bool
    {
        $this->data = null;
        $this->expiration = null;

        if ($this->isDisabled()) {
            return false;
        }

        return $this->driver->clear($this->key ?: null);
    }

    /**
     * {@inheritdoc}
     */
    public function get(): mixed
    {
        try {
            if (!isset($this->data)) {
                $this->data = $this->executeGet(
                    $this->invalidationMethod,
                    $this->invalidationArg1,
                    $this->invalidationArg2
                );
            }

            if (false === $this->isHit) {
                return null;
            }

            return $this->data;
        } catch (Exception $e) {
            $this->logException('Retrieving from cache caused exception.', $e);
            $this->disable();

            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setInvalidationMethod(
        InvalidationMethod $invalidation = InvalidationMethod::Precompute,
        mixed $arg1 = null,
        mixed $arg2 = null,
    ): static {
        $this->invalidationMethod = $invalidation;
        $this->invalidationArg1 = $arg1;
        $this->invalidationArg2 = $arg2;

        return $this;
    }

    protected function executeGet(
        ?InvalidationMethod $invalidationMethod = InvalidationMethod::Precompute,
        mixed $arg1 = null,
        mixed $arg2 = null,
    ): mixed {
        $this->isHit = false;

        if ($this->isDisabled()) {
            return null;
        }

        if (!isset($this->key)) {
            return null;
        }

        $vArray = [];
        if ($invalidationMethod !== null) {
            $vArray[] = $invalidationMethod;

            if (isset($arg1)) {
                $vArray[] = $arg1;
                if (isset($arg2)) {
                    $vArray[] = $arg2;
                }
            }
        }

        $record = $this->getRecord();

        $this->validateRecord($vArray, $record);

        return $record['data']['return'] ?? null;
    }

    /**
    * {@inheritdoc}
    */
    public function isHit(): bool
    {
        return !$this->isMiss();
    }

    /**
     * {@inheritdoc}
     */
    public function isMiss(): bool
    {
        if ($this->isHit === null) {
            $this->get();
        }

        if ($this->isDisabled()) {
            return true;
        }

        return !$this->isHit;
    }

    /**
     * {@inheritdoc}
     */
    public function lock(?int $ttl = null): bool
    {
        if ($this->isDisabled()) {
            return true;
        }

        if (!$this->key) {
            return false;
        }

        $this->stampedeRunning = true;

        $expiration = $ttl !== null ? $ttl : $this->defaults['stampede_ttl'];


        $spKey = $this->key;
        $spKey[0] = 'sp';

        return $this->driver->storeData($spKey, true, time() + $expiration);
    }

    /**
     * {@inheritdoc}
     */
    public function set(mixed $value): static
    {
        if (!$this->key) {
            return $this;
        }

        if ($this->isDisabled()) {
            return $this;
        }

        $this->data = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setTTL(null|int|\DateInterval|\DateTimeInterface $ttl = null): static
    {
        if (is_numeric($ttl) || $ttl instanceof \DateInterval) {
            return $this->expiresAfter($ttl);
        }

        if ($ttl instanceof \DateTimeInterface) {
            return $this->expiresAt($ttl);
        }

        $this->expiration = null;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function expiresAt(?\DateTimeInterface $expiration = null): static
    {
        $this->expiration = $expiration;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function expiresAfter(null|int|\DateInterval $time): static
    {
        if ($time === null) {
            $this->expiration = null;

            return $this;
        }

        $this->expiration = new \DateTime();
        if (is_numeric($time)) {
            $dateInterval = \DateInterval::createFromDateString(abs($time) . ' seconds');
            if ($time > 0) {
                $this->expiration->add($dateInterval);
            } else {
                $this->expiration->sub($dateInterval);
            }

            return $this;
        }

        if ($time instanceof \DateInterval) {
            $this->expiration->add($time);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function save(): bool
    {
        try {
            return $this->executeSet($this->data, $this->expiration);
        } catch (Exception $e) {
            $this->logException('Setting value in cache caused exception.', $e);
            $this->disable();

            return false;
        }
    }

    protected function executeSet(mixed $data, null|int|\DateTimeInterface $time): bool
    {
        if ($this->isDisabled() || !$this->key) {
            return false;
        }

        $store = [
            'return' => $data,
            'createdOn' => time(),
        ];

        if (isset($time) && (($time instanceof \DateTimeInterface))) {
            $expiration = $time->getTimestamp();
            $cacheTime = $expiration - $store['createdOn'];
        } else {
            $cacheTime = static::$cacheTime;
        }

        $expiration = $store['createdOn'] + $cacheTime;

        if ($cacheTime > 0) {
            $expirationDiff = rand(0, (int) floor($cacheTime * 0.15));
            $expiration -= $expirationDiff;
        }

        if ($this->stampedeRunning === true) {
            $spKey = $this->key;
            // Change "cache" data namespace to stampede namespace.
            $spKey[0] = 'sp';
            $this->driver->clear($spKey);
            $this->stampedeRunning = false;
        }

        return $this->driver->storeData($this->key, $store, $expiration);
    }

    /**
     * {@inheritdoc}
     */
    public function extend(null|int|\DateInterval $ttl = null): bool
    {
        if ($this->isDisabled()) {
            return false;
        }

        $expiration = $this->getExpiration();

        if (is_numeric($ttl)) {
            $dateInterval = \DateInterval::createFromDateString(abs($ttl) . ' seconds');
            if ($ttl > 0) {
                $expiration->add($dateInterval);
            } else {
                $expiration->sub($dateInterval);
            }
        } elseif ($ttl instanceof \DateInterval) {
            $expiration->add($ttl);
        } else {
            $expiration = null;
        }

        return $this->executeSet($this->get(), $expiration);
    }

    /**
     * {@inheritdoc}
     */
    public function isDisabled(): bool
    {
        return static::$runtimeDisable
            || !$this->cacheEnabled
            || (defined('STASH_DISABLE_CACHE') && STASH_DISABLE_CACHE);
    }

    public function getLogger(): LoggerInterface
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
                'key' => $this->keyString
            ],
        );

        return $this;
    }

    /**
     * Returns true if another Item is currently recalculating the cache.
     *
     * @param string[] $key
     */
    protected function getStampedeFlag(array $key): bool
    {
        // Change "cache" data namespace to stampede namespace.
        $key[0] = 'sp';
        $spReturn = $this->driver->getData($key);
        $sp = $spReturn['data'] ?? false;

        if (isset($spReturn['expiration'])) {
            if ($spReturn['expiration'] < time()) {
                $sp = false;
            }
        }

        return $sp;
    }

    /**
     * Returns the record for the current key.
     *
     * If there is no record than an empty array is returned.
     */
    protected function getRecord(): array
    {
        return $this->driver->getData($this->key);
    }

    /**
     * Decides whether the current data is fresh according to the supplied validation technique. As some techniques
     * actively change the record this function takes that in as a reference.
     *
     * This function has the ability to change the isHit property as well as the record passed.
     *
     * @internal
     */
    protected function validateRecord(array $validation, array &$record)
    {
        $invalidationMethod = InvalidationMethod::Precompute;
        if (is_array($validation)) {
            $argArray = $validation;
            $invalidationMethod = $argArray[0] ?? InvalidationMethod::Precompute;

            if (isset($argArray[1])) {
                $arg1 = $argArray[1];
            }

            if (isset($argArray[2])) {
                $arg2 = $argArray[2];
            }
        }

        $curTime = microtime(true);

        if (isset($record['expiration']) && ($ttl = $record['expiration'] - $curTime) > 0) {
            $this->isHit = true;

            if ($invalidationMethod === InvalidationMethod::Precompute) {
                $time = isset($arg1) && is_numeric($arg1) ? $arg1 : $this->defaults['precompute_time'];

                // If stampede control is on it means another cache is already processing, so we return
                // true for the hit.
                if ($ttl < $time) {
                    $this->isHit = $this->getStampedeFlag($this->key);
                }
            }

            return;
        }

        if (!isset($invalidationMethod) || $invalidationMethod === InvalidationMethod::None) {
            $this->isHit = false;

            return;
        }

        if (!$this->getStampedeFlag($this->key)) {
            $this->isHit = false;

            return;
        }

        switch ($invalidationMethod) {
            case InvalidationMethod::Value:
                if (!isset($arg1)) {
                    $this->isHit = false;

                    return;
                } else {
                    $record['data']['return'] = $arg1;
                    $this->isHit = true;
                }
                break;

            case InvalidationMethod::Sleep:
                $time = isset($arg1) && is_numeric($arg1) ? $arg1 : $this->defaults['sleep_time'];
                $attempts = isset($arg2) && is_numeric($arg2) ? $arg2 : $this->defaults['sleep_attempts'];
                $ptime = $time * 1000;

                if ($attempts <= 0) {
                    $this->isHit = false;
                    $record['data']['return'] = null;
                    break;
                }

                usleep($ptime);
                $record['data']['return'] = $this->executeGet(InvalidationMethod::Sleep, $time, $attempts - 1);
                break;

            case InvalidationMethod::Old:
                $this->isHit = isset($record['data']) && $record['data']['return'] !== null;
                break;

            default:
                $this->isHit = false;
                break;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCreation(): ?\DateTimeInterface
    {
        $record = $this->getRecord();
        if (!isset($record['data']['createdOn'])) {
            return null;
        }

        $dateTime = new \DateTime();
        $dateTime->setTimestamp($record['data']['createdOn']);

        return $dateTime;
    }

    /**
     * {@inheritdoc}
     */
    public function getExpiration(): ?\DateTimeInterface
    {
        if (!$this->expiration) {
            $record = $this->getRecord();
            $now = new \DateTime();

            if (!isset($record['expiration'])) {
                return null;
            }

            $this->expiration = $now->setTimestamp($record['expiration']);
        }

        return $this->expiration;
    }
}
