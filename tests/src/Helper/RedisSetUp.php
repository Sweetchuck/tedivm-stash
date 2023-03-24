<?php

declare(strict_types = 1);

namespace Stash\Test\Helper;

trait RedisSetUp
{

    protected function getRedisServers(): array
    {
        $definitions = [
            'url1' => getenv('STASH_REDIS_SERVER_URL1') ?: '127.0.0.1:6379',
            'url2' => getenv('STASH_REDIS_SERVER_URL2') ?: '127.0.0.1:6380',
            'socket' => getenv('STASH_REDIS_SERVER_SOCKET') ?: '/tmp/redis.sock:-1',
            'none' => getenv('STASH_REDIS_SERVER_NONE') ?: '127.0.0.1:6381',
        ];

        $servers = [];
        foreach ($definitions as $name => $definition) {
            $parts = explode(':', $definition);
            $servers[$name] = [
                'host' => $parts[0],
                'port' => $parts[1] ?? -1,
            ];
            settype($servers[$name]['port'], 'int');
        }

        return $servers;
    }
}
