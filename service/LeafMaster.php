<?php

namespace service;

use Webman\Exception\NotFoundException;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;
use Workerman\Worker;

class LeafMaster extends Worker
{
    public           $onMessage      = [self::class, 'onMessage'];
    public           $onWorkerStart  = [self::class, 'onWorkerStart'];
    public           $onError        = [self::class, 'onError'];
    public           $onWorkerReload = [self::class, 'onWorkerReload'];
    public           $onClose        = [self::class, 'onClose'];
    public           $onConnect      = [self::class, 'onConnnect'];
    protected static $listenerList   = [];
    protected static $workerList     = [];

    protected static $config   = [];
    protected        $isMaster = true;

    static function addWorker($listen)
    {
        self::$listenerList[] = $listen;
    }


    public function __construct($socket_name = '', array $config = [], array $context_option = array())
    {
        if (!$config) {
            throw new NotFoundException('Leaf config file format error');
        }
        self::$config = $config;
        parent::__construct($socket_name, $context_option);

    }

    static function onWorkerStart(Worker $worker)
    {
        //创建leafWorker
        foreach (self::$config['worker'] as $key => $config) {
            $leafWorker        = new LeafWorker("text://{$config['listen']}", $config, self::$config['master']['listen']);
            $leafWorker->count = 1;
            $leafWorker->run();
            self::addWorker($config['listen']);
            $connection = stream_socket_client('tcp://' . $config['listen']);
            $data       = json_encode([
                'cmd'  => 'ping',
                'data' => [
                    'listen' => $config['listen'],
                ]
            ]);
            fwrite($connection, json_encode($data) . "\n");
        }
    }

    static function onConnnect(TcpConnection $connection)
    {
    }


    static function onMessage($connection, $data)
    {
        $data = json_decode($data, true);
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        $cmd  = $data['cmd'];
        $data = $data['data'];
        if ($cmd == 'pong') {
            $listen       = $data['listen'];
            $lastPingTime = $data['lastPingTime'] ?? null;
            if (in_array($listen, self::$listenerList)) {
                $timerId = Timer::add(1, function () use (&$listen, $lastPingTime, &$timerId) {
                    if (!$lastPingTime || time() - $lastPingTime < 10) {
                        array_slice(self::$listenerList, array_search($listen, self::$listenerList), 1);
                        Timer::del($timerId);
                        return;
                    }
                    $connection = stream_socket_client('tcp://' . $listen);
                    $data       = ['cmd' => 'ping', 'data' => ['listen' => $listen]];
                    fwrite($connection, json_encode($data) . "\n");
                });
            }
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

    static function getConfig()
    {
        return self::$config;
    }


}
