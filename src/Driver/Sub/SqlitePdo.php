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

namespace Stash\Driver\Sub;

use Stash\Exception\RuntimeException;
use Stash\Utilities;

/**
 * Class SqlitePDO
 *
 * This SQLite subdriver uses PDO and the latest version of SQLite.
 *
 * @internal
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class SqlitePdo
{

    /**
     * Checks that PDO extension is present and has the appropriate SQLite driver.
     *
     */
    public static function isAvailable()
    {
        $drivers = \PDO::getAvailableDrivers();
        return in_array(static::$pdoDriver, $drivers);
    }

    /**
     * Directory where the SQLite databases are stored.
     */
    protected string $path = '';

    /**
     * Output of buildDriver, used to interact with the relevant SQLite extension.
     */
    protected ?\PDO $driver = null;

    /**
     * PDO driver string, used to distinguish between SQLite versions.
     */
    protected static string $pdoDriver = 'sqlite';

    /**
     * The SQLite query used to generate the database.
     */
    protected string $creationSql = <<< SQLITE
        CREATE TABLE cacheStore (
            key TEXT UNIQUE ON CONFLICT REPLACE,
            expiration INTEGER,
            encoding TEXT,
            data BLOB
        );
        CREATE INDEX keyIndex ON cacheStore (key);
    SQLITE;

    /**
     * File permissions of new SQLite databases.
     */
    protected int $filePermissions;

    /**
     * File permissions of new directories leading to SQLite databases.
     */
    protected int $dirPermissions;

    /**
     * Amounts of time in milliseconds to wait for the SQLite engine before timing out.
     */
    protected int $busyTimeout;

    /**
     * The appropriate response code to use when retrieving data.
     *
     * @todo Rename this.
     */
    protected int $responseCode;

    public function __construct(
        string $path,
        int $directoryPermission,
        int $filePermission,
        int $busyTimeout,
    ) {
        $this->path = $path;
        $this->filePermissions = $filePermission;
        $this->dirPermissions = $directoryPermission;
        $this->busyTimeout = $busyTimeout;
        $this->responseCode = \PDO::FETCH_ASSOC;
    }

    /**
     * Clear out driver, closing file sockets.
     */
    public function __destruct()
    {
        $this->driver = null;
    }

    /**
     * Retrieves data from cache store.
     */
    public function get(string $key): mixed
    {
        $statement = $this
            ->getDriver()
            ->prepare('SELECT * FROM cacheStore WHERE key LIKE :key');
        if (!$statement) {
            return false;
        }

        // @todo In 0.17.x there was no escape.
        // What if there are multiple rows?
        $statement->execute([
            'key' => $key,
        ]);

        $resultArray = $statement->fetch($this->responseCode);

        return $resultArray ?
            unserialize(base64_decode($resultArray['data']))
            : false;
    }

    /**
     * Stores data in sqlite database.
     */
    public function set(string $key, mixed $value, int $expiration): bool
    {
        $driver = $this->getDriver();
        $data = base64_encode(serialize($value));

        $contentLength = strlen($data);
        if ($contentLength > 100000) {
            // 0.5s per 100k.
            $busyTimeout = (int) ($this->busyTimeout * (ceil($contentLength / 100000)));
            $this->setTimeout($busyTimeout);
        }

        $query = <<< SQLITE
            INSERT INTO cacheStore
                (
                    key,
                    expiration,
                    data
                )
            VALUES
                (
                    :key,
                    :expiration,
                    :data
                )
            ;
        SQLITE;

        return $driver
            ->prepare($query)
            ->execute([
                'key' => $key,
                'expiration' => $expiration,
                'data' => $data,
            ]);
    }

    /**
     * Clears data from database. If a key is defined only it and it's children are removed. If everything is set to be
     * cleared then the database itself is deleted off disk.
     */
    public function clear(?string $key = null): bool
    {
        // Return true if the cache is already empty
        try {
            $driver = $this->getDriver();
        } catch (RuntimeException $e) {
            return true;
        }

        if ($key === null) {
            unset($driver);
            $this->driver = null;
            Utilities::deleteRecursive($this->path);

            return true;
        }

        return $driver
            ->prepare('DELETE FROM cacheStore WHERE key LIKE :prefix')
            ->execute([
                'prefix' => $this->escapeLike($key) . '%',
            ]);
    }

    /**
     * Old data is removed and the "vacuum" operation is run.
     */
    public function purge(): bool
    {
        $driver = $this->getDriver();
        $result1 = $driver
            ->prepare('DELETE FROM cacheStore WHERE expiration < :now')
            ->execute([
                'now' => time(),
            ]);
        $result2 = $driver->query('VACUUM');

        return $result1 && $result2;
    }

    /**
     * Tells the SQLite driver how long to wait for data to be written.
     */
    protected function setTimeout(int $milliseconds): static
    {
        $driver = $this->getDriver();
        $timeout = ceil($milliseconds / 1000);
        $driver->setAttribute(\PDO::ATTR_TIMEOUT, (int) $timeout);

        return $this;
    }

    /**
     * Retrieves the relevant SQLite driver, creating the database file if necessary.
     *
     * @throws \Stash\Exception\RuntimeException
     */
    protected function getDriver(): \PDO
    {
        if ($this->driver) {
            return $this->driver;
        }

        if (!file_exists($this->path)) {
            $dir = $this->path;

            // Since PHP will understand paths with mixed slashes- both the windows \ and unix / variants- we have
            // to test for both and see which one is the last in the string.
            $pos1 = strrpos($this->path, '/');
            $pos2 = strrpos($this->path, '\\');

            if ($pos1 || $pos2) {
                $pos = $pos1 >= $pos2 ? $pos1 : $pos2;
                $dir = substr($this->path, 0, $pos);
            }

            if (!is_dir($dir) && !mkdir($dir, $this->dirPermissions, true) && !is_dir($dir)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
            }
            if (file_put_contents($this->path, '') === false) {
                throw new RuntimeException(sprintf('Cache file "%s" was not created', basename($this->path)));
            }
            if (!chmod($this->path, $this->filePermissions)) {
                throw new RuntimeException(sprintf(
                    'Cache file permissions for "%s" could not be set',
                    basename($this->path),
                ));
            }
            $runInstall = true;
        } else {
            $runInstall = false;
        }

        $db = $this->buildDriver();

        if ($runInstall && !$db->query($this->creationSql)) {
            unlink($this->path);

            throw new RuntimeException('Unable to set SQLite: structure');
        }

        $this->driver = $db;

        // prevent the cache from getting hungup waiting on a return
        $this->setTimeout($this->busyTimeout);

        return $this->driver;
    }

    /**
     * Creates the actual database driver itself.
     */
    protected function buildDriver(): \PDO
    {
        return new \PDO(static::$pdoDriver . ':' . $this->path);
    }

    public function escapeLike(string $string): string
    {
        return addcslashes($string, '\%_');
    }
}
