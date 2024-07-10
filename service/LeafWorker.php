<?php

namespace service;

use Workerman\Connection\TcpConnection;
use Workerman\Timer;
use Workerman\Worker;

class LeafWorker extends Worker
{
    public    $onMessage      = [self::class, 'onMessage'];
    public    $onWorkerStart  = [self::class, 'onWorkerStart'];
    public    $onError        = [self::class, 'onError'];
    public    $onWorkerReload = [self::class, 'onWorkerReload'];
    public    $onClose        = [self::class, 'onClose'];
    public    $onConnect      = [self::class, 'onConnnect'];
    protected $listenerList   = [];
    protected $isMaster       = false;

    public function onWorkerStart(self $worker)
    {

    }

    public function onMessage($connection, $data)
    {
    }

    public function onClose(TcpConnection $connection)
    {

    }

    public function onError(TcpConnection $connection, $code, $msg)
    {

    }

    public function onWorkerReload(self $worker)
    {
        $worker->reload();
    }
}
