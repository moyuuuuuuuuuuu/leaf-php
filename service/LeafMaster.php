<?php

namespace service;

use Workerman\Connection\TcpConnection;
use Workerman\Timer;
use Workerman\Worker;

class LeafMaster extends Worker
{
    public    $onMessage      = [self::class, 'onMessage'];
    public    $onWorkerStart  = [self::class, 'onWorkerStart'];
    public    $onError        = [self::class, 'onError'];
    public    $onWorkerReload = [self::class, 'onWorkerReload'];
    public    $onClose        = [self::class, 'onClose'];
    public    $onConnect      = [self::class, 'onConnnect'];
    protected $listenerList   = [];

    protected $isMaster = true;

    public function addWorker($listen)
    {
        $this->listenerList[] = $listen;
    }

    protected $config = [];

    public function __construct($socket_name = '', array $config = [], array $context_option = array())
    {
        parent::__construct($socket_name, $context_option);
        $this->config = $config;
    }

    public
    function onWorkerStart(self $worker)
    {
        //创建leafWorker
        foreach ($this->config['worker'] as $worker) {
            $leafWorker        = new LeafWorker("text://{$worker['listen']}");
            $leafWorker->count = 1;
            $leafWorker->listen();
            $this->addWorker($worker['listen']);
        }
        Timer::add(1, function () use ($worker) {
            foreach ($worker->connections as $connection) {
                $connection->send('ping');
            }
        });
    }

    public
    function onMessage($connection, $data)
    {
    }

    public
    function onClose(TcpConnection $connection)
    {

    }

    public
    function onError(TcpConnection $connection, $code, $msg)
    {

    }

    public
    function onWorkerReload(self $worker)
    {
        $worker->reload();
    }


}
