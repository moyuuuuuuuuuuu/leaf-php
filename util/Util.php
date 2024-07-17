<?php

namespace util;

use Workerman\Connection\AsyncTcpConnection;

class Util
{
    /**
     * @param $address
     * @param string $cmd
     * @param array|null $data
     * @param array $context
     * @param array{
     *     onMessage:callable,
     *     onConnect:callable,
     *     onClose:callable,
     *     onError:callable
     * } $callableMap
     * @return AsyncTcpConnection
     * @throws \Exception
     */
    static function send($address, string $cmd, ?array $data = null, array $callableMap = [], array $context = []): void
    {
        $asyncTcpConnection = new AsyncTcpConnection($address, $context);
        foreach ($callableMap as $key => $callable) {
            $asyncTcpConnection->$key = $callable;
        }
        if ($data) {
            $data['data'] = $data;
        }
        $data['cmd'] = $cmd;
        $asyncTcpConnection->send(json_encode($data));
        $asyncTcpConnection->connect();
    }

    static function parse(string $data)
    {
        $data = json_decode($data, true);
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        $cmd  = $data['cmd'] ?? null;
        $data = $data['data'] ?? [];
        return [$cmd, $data];
    }
}
