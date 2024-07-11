<?php

namespace service;

use support\Log;
use Webman\Exception\NotFoundException;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Timer;
use Workerman\Worker;

class LeafMaster extends BaseLeaf
{

    protected $listenerList = [];

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
            $leafWorker->reusePort      = true;
            $leafWorker->count          = 4;
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
        Log::log('INFO', 'LeafMaster:' . $data . PHP_EOL);
        list($cmd, $data) = $this->parse($data);
        if ($cmd == 'started') {
            $this->addWorker($data['workerId'], $data);
            $timerId = Timer::add(10, function () use ($data, &$timerId) {
                if (time() - ($this->listenerList[$data['workerId']]['lastPingTime'] ?? 0) > $this->getConfig('timeOut', 60)) {
                    unset($this->listenerList[$data['workerId']]);
                    Timer::del($timerId);
                    Log::log('INFO', 'DeadLeafWorker:' . $data['workerId'] . ',listen:' . $data['listen'] . PHP_EOL);
                    return;
                }
                $address = 'text://' . $data['listen'];
                InnerTcpConnection::getInstance()->listen($address)->send($address, 'ping', []);
            });
        } else if ($cmd == 'numberOff') {
            $this->maxNumber = max($this->maxNumber, $data['number']);
//            Redis::set('leaf:maxNumber', $this->maxNumber);
        } elseif ($cmd == 'updateRange') {
            if (!isset($this->listenerList[$data['workerId']])) {
                return;
            }
            $connection->send(json_encode([
                'cmd'  => 'updateRange',
                'data' => [
                    'min' => $nextMin = $this->getNextMin(),
                    'max' => $nextMin + ($this->getConfig('step', 1000)) - 1,
                ]
            ]));
        }
    }

    public function onWorkerReload(self $worker)
    {
        $worker->reload();
    }

    protected function getNextMin($currentMaxNumber = null)
    {
        $i                 = $currentMaxNumber ?? $this->maxNumber;
        $bucketSpaceNumber = count($this->listenerList) - 1;
        $bucketSpaceNumber = $bucketSpaceNumber > 0 ? $bucketSpaceNumber : 1;
        $bucketStep        = $this->getConfig('step');
        return $i + $bucketStep * $bucketSpaceNumber + 1;
    }


}
