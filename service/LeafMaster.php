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

    protected $config = [];

    /**
     * @var LeafWorker[]
     */
    protected $workerNodeList = [];
    protected $isMaster       = true;
    /**
     * 当前最大号
     * @var int
     */
    protected $maxNumber = 0;
    /**
     * 请求分发节点
     * @var RequestDistribution
     */
    protected $distributionNode;

    public function addWorker($workerId, $data)
    {
        $this->listenerList[$workerId] = $data;
    }


    /**
     * @return LeafWorker[]
     */
    public function getNodes()
    {
        return $this->workerNodeList;
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
            $leafWorker                                   = $this->runWorker($config);
            $this->workerNodeList[$leafWorker->workUinId] = $leafWorker;
        }
        $this->distributionNode            = new RequestDistribution("text://{$this->getConfig('distribution.listen','127.0.0.1:8080')}", $worker);
        $this->distributionNode->count     = $this->getConfig('distribution.count', 1);
        $this->distributionNode->reusePort = $this->getConfig('distribution.reusePort', false);
        $this->distributionNode->onMessage = [$this->distributionNode, 'onMessage'];
        $this->distributionNode->run();
    }


    public function onMessage($connection, $data)
    {
        list($cmd, $data) = $this->parse($data);
        if ($cmd == 'started') {
            $this->addWorker($data['workerId'], $data);
            $timerId = Timer::add(10, function () use ($data, &$timerId) {
                if (time() - ($this->listenerList[$data['workerId']]['lastPingTime'] ?? 0) > $this->getConfig('timeOut', 60)) {
                    if (isset($this->workerNodeList[$data['workerId']]))
                        $this->runWorker($this->workerNodeList[$data['workerId']]->getConfig());
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

            $address = 'text://' . $this->listenerList[$data['workerId']]['listen'];
            InnerTcpConnection::getInstance()->listen($address)->send($address, 'updateRange', [
                'min' => $nextMin = $this->getNextMin(),
                'max' => $nextMin + ($this->getConfig('step', 1000)) - 1,
            ]);
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

    protected function runWorker(array $config = [])
    {
        $leafWorker                 = new LeafWorker("text://{$config['listen']}", $config, $this->getConfig('master.listen'), $this);
        $leafWorker->count          = 4;
        $leafWorker->onMessage      = [$leafWorker, 'onMessage'];
        $leafWorker->onWorkerStart  = [$leafWorker, 'onWorkerStart'];
        $leafWorker->onWorkerReload = [$leafWorker, 'onWorkerReload'];
        $leafWorker->count          = 1;
        $leafWorker->run();
        return $leafWorker;
    }

}
