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
use Stash\Driver\Sub\SqlitePdo;
use Stash\Utilities;
use Stash\Exception\RuntimeException;

/**
 * StashSqlite is a wrapper around one or more SQLite databases stored on the local system. While not as quick at
 * reading as the StashFilesystem driver this class is significantly better when it comes to clearing multiple keys
 * at once.
 *
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class Sqlite extends AbstractDriver
{

    /**
     * {@inheritdoc}
     */
    public static function isAvailable(): bool
    {
        return Sub\SqlitePdo::isAvailable();
    }

    protected int $filePermissions = 0644;

    protected int $dirPermissions = 0755;

    protected int $busyTimeout = 0;

    protected string $cachePath;

    protected string $driverClass = SqlitePdo::class;

    protected int $nesting = 10;

    protected array $subDrivers = [];

    protected bool $disabled = false;

    public function getDefaultOptions(): array
    {
        $options = parent::getDefaultOptions();

        return $options + [
            'filePermissions' => 0660,
            'dirPermissions' => 0770,
            'busyTimeout' => 500,
            'nesting' => 0,
            'subdriver' => 'PDO',
        ];
    }

    /**
     * @throws \Stash\Exception\RuntimeException
     */
    protected function setOptions(array $options = []): static
    {
        $options += $this->getDefaultOptions();

        if (!isset($options['path'])) {
            $options['path'] = Utilities::getBaseDirectory($this);
        }

        $this->cachePath = rtrim($options['path'], '\\/') . DIRECTORY_SEPARATOR;
        $this->filePermissions = $options['filePermissions'];
        $this->dirPermissions = $options['dirPermissions'];
        $this->busyTimeout = $options['busyTimeout'];
        $this->nesting = max((int) $options['nesting'], 0);

        Utilities::checkFileSystemPermissions($this->cachePath, $this->dirPermissions);
        $driver = $this->getSqliteDriverByKey(['_none']);
        if (!$driver || !static::isAvailable() || !Sub\SqlitePdo::isAvailable()) {
            throw new RuntimeException('No Sqlite driver could be loaded.');
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getData(array $key): array
    {
        $sqlKey = self::makeSqlKey($key);
        if (!($sqlDriver = $this->getSqliteDriverByKey($key)) || !($data = $sqlDriver->get($sqlKey))) {
            return [];
        }

        $data['data'] = Utilities::decode($data['data'], $data['encoding']);

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function storeData(array $key, mixed $data, int $expiration): bool
    {
        $sqlDriver = $this->getSqliteDriverByKey($key);
        if (!$sqlDriver) {
            return false;
        }

        $storeData = [
            'data' => Utilities::encode($data),
            'expiration' => $expiration,
            'encoding' => Utilities::encoding($data),
        ];

        return $sqlDriver->set(self::makeSqlKey($key), $storeData, $expiration);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(?array $key = null): bool
    {
        $databases = $this->getCacheList();
        if (!$databases) {
            return true;
        }

        $sqlKey = $key ? static::makeSqlKey($key) : null;

        foreach ($databases as $database) {
            $driver = $this->getSqliteDriverByFile($database, true);
            if (!$driver) {
                continue;
            }

            $driver->clear($sqlKey);
            $driver->__destruct();
            unset($driver);
        }
        $this->subDrivers = [];

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function purge(): bool
    {
        if (!($databases = $this->getCacheList())) {
            return true;
        }

        foreach ($databases as $database) {
            if ($driver = $this->getSqliteDriverByFile($database)) {
                $driver->purge();
            }
        }

        return true;
    }

    /**
     * @param null|string|string[] $key
     *
     * @deprecated
     *
     * @see static::getSqliteDriverByFile
     * @see static::getSqliteDriverByKey
     */
    protected function getSqliteDriver(null|string|array $key): ?SqlitePdo
    {
        if (is_string($key)) {
            return $this->getSqliteDriverByFile($key);
        }

        if (is_array($key)) {
            return $this->getSqliteDriverByKey($key);
        }

        return null;
    }

    protected function getSqliteDriverByFile(string $file): ?SqlitePdo
    {
        if (isset($this->subDrivers[$file])) {
            return $this->subDrivers[$file];
        }

        $driverClass = $this->driverClass;
        $driver = new $driverClass($file, $this->dirPermissions, $this->filePermissions, $this->busyTimeout);

        $this->subDrivers[$file] = $driver;

        return $driver;
    }

    protected function getSqliteDriverByKey(?array $key): ?SqlitePdo
    {
        $file = $this->getSqliteDriverFile($key);

        return $file ?
            $this->getSqliteDriverByFile($file)
            : null;
    }

    protected function getSqliteDriverFile(?array $key): ?string
    {
        if (!$key) {
            return null;
        }

        $key = Utilities::normalizeKeys($key);

        $nestingLevel = min($this->nesting, count($key)+1);
        $fileName = 'cache_';
        for ($i = 1; $i < $nestingLevel; $i++) {
            $fileName .= $key[$i - 1] . '_';
        }

        return $this->cachePath . rtrim($fileName, '_') . '.sqlite';
    }

    /**
     * Destroys the sub-drivers when this driver is unset -- required for Windows compatibility.
     */
    public function __destruct()
    {
        if ($this->subDrivers) {
            foreach ($this->subDrivers as &$driver) {
                $driver->__destruct();
                unset($driver);
            }
        }
    }

    protected function getCacheList(): ?array
    {
        $filePath = $this->cachePath;
        $caches = [];
        $databases = glob($filePath . '*.sqlite');
        foreach ($databases as $database) {
            $caches[] = $database;
        }

        return count($caches) > 0 ? $caches : null;
    }

    /**
     * This function takes an array of strings and turns it into the sqlKey. It does this by iterating through the
     * array, running the string through sqlite_escape_string() and then combining that string to the keystring with a
     * delimiter.
     *
     * @param string[] $key
     */
    public static function makeSqlKey(array $key): string
    {
        $key = Utilities::normalizeKeys($key, 'base64_encode');
        $path = '';
        foreach ($key as $rawPathPiece) {
            $path .= $rawPathPiece . ':::';
        }

        return $path;
    }

    /**
     * {@inheritdoc}
     */
    public function isPersistent(): bool
    {
        return true;
    }
}
