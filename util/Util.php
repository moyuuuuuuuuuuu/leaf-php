<?php

namespace util;

use Workerman\Connection\AsyncTcpConnection;

class Util
{
    static function send($address, string $cmd, ?array $data = null, array $context = []): void
    {
        $asyncTcpConnection = new AsyncTcpConnection($address, $context);
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
