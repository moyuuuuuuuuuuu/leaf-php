<?php

namespace service;

use support\Log;
use support\Redis;
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
        foreach ($this->getConfig('worker') as $key => $config) {
            $leafWorker                 = new LeafWorker("text://{$config['listen']}", $config, $this->getConfig('master.listen'), $this);
            $leafWorker->onMessage      = [$leafWorker, 'onMessage'];
            $leafWorker->onWorkerStart  = [$leafWorker, 'onWorkerStart'];
            $leafWorker->onWorkerReload = [$leafWorker, 'onWorkerReload'];
            $leafWorker->count          = 1;
            $leafWorker->run();
        }
        //从redis获取maxNumber 并更新各个桶的取号范围
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
            $this->addWorker($data['workerId'], $data);
            $timerId = Timer::add(10, function () use ($data, &$timerId) {
                if (time() - ($this->listenerList[$data['workerId']]['lastPingTime'] ?? 0) > $this->getConfig('timeOut', 60)) {
                    unset($this->listenerList[$data['workerId']]);
                    Timer::del($timerId);
                    Log::log('INFO', 'DeadLeafWorker:' . $data['workerId'] . ',listen:' . $data['listen'] . PHP_EOL);
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
//            Redis::set('leaf:maxNumber', $this->maxNumber);
        } elseif ($cmd == 'updateRange') {
            $nextMin = $this->getNextMin();
            if (!isset($this->listenerList[$data['workerId']])) {
                return;
            }
            $listen = $this->listenerList[$data['workerId']]['listen'];
            $master = stream_socket_client('tcp://' . $listen);
            $data   = [
                'cmd'  => 'updateRange',
                'data' => [
                    'min' => $nextMin,
                    'max' => $nextMin + ($this->getConfig('step', 1000)) - 1,
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

    /**
     * @param $key
     * @return array|string
     */
    public function getConfig($key = '*', $defaultValue = '')
    {
        if (strstr($key, '.')) {
            list($firstName, $secondName) = explode('.', $key);
            return $this->config[$firstName][$secondName] ?? $defaultValue;
        } else if ($key != '*') {
            return $this->config[$key] ?? $defaultValue;
        } else {
            return $this->config;
        }
    }

    protected function getNextMin($currentMaxNumber = null)
    {
        $i                 = $currentMaxNumber ?? $this->maxNumber;
        $bucketSpaceNumber = count($this->listenerList) - 1 ?? 1;
        $bucketStep        = $this->getConfig('step');
        $num               = str_pad(1, strlen($i), 0);
        return (int)ceil($i / $num) * $bucketSpaceNumber * $bucketStep + 1;
    }


}
