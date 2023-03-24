<?php

declare(strict_types = 1);

namespace Stash\Test\Helper;

trait MemcacheSetUp
{

    protected function getMemcacheServers(): array
    {
        $servers = [];

        $items = explode(
            ' ',
            getenv('STASH_MEMCACHE_SERVERS') ?: '127.0.0.1:11211 127.0.0.1:11211:50',
        );

        foreach ($items as $item) {
            $parts = explode(':', $item);
            if (array_key_exists(1, $parts)) {
                settype($parts[1], 'int');
            }

            if (array_key_exists(2, $parts)) {
                if (is_numeric($parts[2])) {
                    settype($parts[2], 'int');
                } else {
                    $parts[2] = $parts[2] === 'true';
                }
            }

            $servers[] = $parts;
        }

        return $servers;
    }
}
