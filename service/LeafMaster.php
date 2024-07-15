<?php

namespace service;

use util\{Config, Util};
use Exception;
use support\Log;
use Webman\Exception\NotFoundException;
use Workerman\Timer;
use Workerman\Worker;

class LeafMaster extends Worker
{

    protected $listenerList = [];

    /**
     * @var LeafWorker[]
     */
    protected $workerNodeList = [];
    /**
     * 当前最大号
     * @var int
     */
    protected $maxNumber = 0;
    /**
     * 请求分发节点
     * @var DisReqCenter
     */
    protected $distributionNode;

    public function addWorker($workerId, $data)
    {
        if (!isset($this->listenerList[$workerId])) {
            $this->listenerList[$workerId] = $data;
            return;
        }
    }

    /**
     * @return LeafWorker[]
     */
    public function getNodes(): array
    {
        return $this->workerNodeList;
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function onWorkerStart(Worker $worker)
    {
        //创建leafWorker
        foreach (Config::getInstance()->get('worker') as $key => $config) {
            $this->runLeafWorker($worker, $config, $key);
        }
        $this->runDisReqCenter(Config::getInstance()->get('distribution'));

    }

    public function onMessage($connection, $data)
    {
        $connection->close();
        list($cmd, $data) = Util::parse($data);
        if ($cmd == 'started') {
            $timerId = Timer::add(10, function () use ($data, &$timerId) {
                if ($data['w'] instanceof LeafWorker) {
                    $worker = $this->getWorker($data['workerId']);
                    if (!$worker) {
                        $this->removeLeafWorker($data['workerId']);
                        $callback = function () use ($data) {
                            $this->runLeafWorker($this, $data);
                        };
                    }
                } else if ($data['w'] instanceof DisReqCenter) {
                    $worker = $this->distributionNode;
                    if (!$worker) {
                        $callback = function () use ($data) {
                            $this->runDisReqCenter(Config::getInstance()->get('distribution'));
                        };
                    }
                }
                if (!$worker) {
                    Timer::del($timerId);
                    return;
                }

                if (time() - $worker->lastPingTime > Config::getInstance()->get('timeOut')) {
                    $callback();
                    return;
                }

                Util::send($worker->getSocketName(), 'ping', []);
            });
        } else if ($cmd == 'numberOff') {
            $this->maxNumber = max($this->maxNumber, $data['number']);
//            Redis::set('leaf:maxNumber', $this->maxNumber);
        } elseif ($cmd == 'updateRange') {
            Util::send($this->getWorker($data['workerId'])->getSocketName(), 'updateRange', [
                'min' => $nextMin = $this->getNextMin(),
                'max' => $nextMin + (Config::getInstance()->get('step', 1000)) - 1,
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
        $bucketStep        = Config::getInstance()->get('step');
        return $i + $bucketStep * $bucketSpaceNumber + 1;
    }

    /**
     * 启动发号节点
     * @param $worker
     * @param array $config
     * @return void
     * @throws NotFoundException
     */
    protected function runLeafWorker($worker, array $config = []): void
    {
        try {
            $leafWorker                 = new LeafWorker("text://{$config['listen']}", $config, $worker);
            $leafWorker->onMessage      = [$leafWorker, 'onMessage'];
            $leafWorker->onWorkerStart  = [$leafWorker, 'onWorkerStart'];
            $leafWorker->onWorkerReload = [$leafWorker, 'onWorkerReload'];
            $leafWorker->run();
            $this->workerNodeList[$leafWorker->workerId] = $leafWorker;
        } catch (Exception $e) {
            throw new NotFoundException('run hosted ' . $config['listen'] . ' Leaf Worker failure : ' . $e->getMessage());
        }

    }

    /**
     * 启动请求统一分发中心
     * @param array $config
     * @return void
     * @throws Exception
     */
    protected function runDisReqCenter(array $config = [])
    {
        $listen                                = $config['listen'] ?? '127.0.0.1:8080';
        $this->distributionNode                = new DisReqCenter("tcp://{$listen}", $this);
        $this->distributionNode->count         = $config['count'] ?? 4;
        $this->distributionNode->reusePort     = $config['reusePort'] ?? false;
        $this->distributionNode->onMessage     = [$this->distributionNode, 'onMessage'];
        $this->distributionNode->onWorkerStart = [$this->distributionNode, 'onWorkerStart'];
        $this->distributionNode->run();
    }

    /**
     * @param $workerId
     * @return LeafWorker|null
     */
    protected function getWorker($workerId)
    {
        if (!isset($this->workerNodeList[$workerId])) return null;
        return $this->workerNodeList[$workerId];
    }

    protected function removeLeafWorker($workerId)
    {
        if (isset($this->workerNodeList[$workerId])) {
            unset($this->workerNodeList[$workerId]);
        }
    }

}
