<?php

namespace service;

use Webman\Exception\NotFoundException;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;
use Workerman\Worker;

class LeafWorker extends Worker
{
    public $onMessage      = [self::class, 'onMessage'];
    public $onWorkerStart  = [self::class, 'onWorkerStart'];
    public $onError        = [self::class, 'onError'];
    public $onWorkerReload = [self::class, 'onWorkerReload'];
    public $onClose        = [self::class, 'onClose'];

    protected static $masterListen = '';
    protected static $isMaster     = false;
    protected static $config       = null;
    protected static $currentId    = 0;

    public function __construct($socket_name = '', array $config = [], string $master = '', array $context_option = array())
    {
        parent::__construct($socket_name, $context_option);
        if (!$config) {
            throw new NotFoundException('Leaf config file format error');
        }
        if (!$master) {
            throw new NotFoundException('Leaf master listen not found');
        }
        self::$masterListen = $master;
        self::$config       = $config;
        self::$currentId    = $config['min'];
    }

    static function setMaster(string $listen)
    {
        self::$masterListen = $listen;
    }

    static function onWorkerStart(Worker $worker)
    {
        $master = stream_socket_client('tcp://' . self::$masterListen);
        $data   = [
            'cmd'  => 'started',
            'data' => [
                'workerId' => $worker->id,
                'listen'   => self::$config['listen']
            ]
        ];
        fwrite($master, json_encode($data) . "\n");

    }

    static function onMessage($connection, $data)
    {
        $data = json_decode($data, true);
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        $cmd  = $data['cmd'];
        $data = $data['data'];
        if ($cmd == 'ping') {
            $data['lastPingTime'] = time();
            $master               = stream_socket_client('tcp://' . self::$masterListen);
            $data                 = ['cmd' => 'pong', 'data' => $data];
            fwrite($master, json_encode($data) . "\n");
        }
    }

    static function onClose(TcpConnection $connection)
    {
    }

    static function onError(TcpConnection $connection, $code, $msg)
    {

    }

    static function onWorkerReload(self $worker)
    {
        $worker->reload();
    }
}
