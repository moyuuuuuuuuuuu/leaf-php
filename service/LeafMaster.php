<?php

namespace service;

use Webman\Exception\NotFoundException;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;
use Workerman\Worker;

class LeafMaster extends Worker
{

    protected $listenerList = [];
    protected $workerList   = [];

    protected $config   = [];
    protected $isMaster = true;
    /**
     * 当前最大号
     * @var int
     */
    protected $maxNumber = 0;

    public function addWorker($workerId, $data)
    {
        $this->listenerList[$workerId] = $data;
    }


    public function __construct($socket_name = '', array $config = [], array $context_option = array())
    {
        if (!$config) {
            throw new NotFoundException('Leaf config file format error');
        }
        $this->config = $config;
        parent::__construct($socket_name, $context_option);

    }

    public function onWorkerStart(Worker $worker)
    {
        //创建leafWorker
        foreach ($this->config['worker'] as $key => $config) {
            $leafWorker                = new LeafWorker("text://{$config['listen']}", $config, $this->config['master']['listen']);
            $leafWorker->onMessage     = [$leafWorker, 'onMessage'];
            $leafWorker->onWorkerStart = [$leafWorker, 'onWorkerStart'];
//            $leafWorker->onError        = [$leafWorker, 'onError'];
            $leafWorker->onWorkerReload = [$leafWorker, 'onWorkerReload'];
            $leafWorker->onClose        = [$leafWorker, 'onClose'];
//            $leafWorker->onConnect      = [$leafWorker, 'onConnnect'];
            $leafWorker->count = 1;
            $leafWorker->run();
        }
    }


    public function onMessage($connection, $data)
    {
        $data = json_decode($data, true);
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        $cmd  = $data['cmd'];
        $data = $data['data'] ?? [];

        if ($cmd == 'started') {
            $workerId = $data['workerId'];
            $this->addWorker($workerId, $data);
            $timerId = Timer::add(1, function () use ($data, &$timerId) {
                if (time() - ($this->listenerList[$data['workerId']]['lastPingTime'] ?? 0) > ($this->config['timeOut'] ?? 60)) {
                    unset($this->listenerList[$data['workerId']]);
                    Timer::del($timerId);
                    echo 'DeadLeafWorker:' . $data['workerId'] . PHP_EOL;
                    return;
                }
                $master = stream_socket_client('tcp://' . $data['listen']);
                $data   = [
                    'cmd' => 'ping',
                ];
                fwrite($master, json_encode($data) . "\n");
                fclose($master);

            });
        } else if ($cmd == 'numberOff') {
            $this->maxNumber = max($this->maxNumber, $data['number']);
        } elseif ($cmd == 'updateRange') {
            $nextMin = $this->getNextMin();
            $listen  = $this->listenerList[$data['workerId']]['listen'];
            $master  = stream_socket_client('tcp://' . $listen);
            $data    = [
                'cmd'  => 'updateRange',
                'data' => [
                    'min' => $nextMin,
                    'max' => $nextMin + $this->config['master']['step'],
                ]
            ];
            fwrite($master, json_encode($data) . "\n");
            fclose($master);

        }
    }

    public function onWorkerReload(self $worker)
    {
        $worker->reload();
    }

    public function getConfig()
    {
        return $this->config;
    }

    protected function getNextMin()
    {
        $i = $this->maxNumber;
        $a = str_pad(1, strlen($i), 0);
        return (int)str_pad(ceil($i / $a), strlen($i), 0) * (count($this->listenerList) - 1) * $this->config['master']['step'] + 1;
    }


}
