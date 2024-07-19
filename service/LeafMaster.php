<?php

namespace service;

use util\{Config, Storage, Util};
use Exception;
use support\Redis;
use Webman\Exception\NotFoundException;
use Workerman\Timer;
use Workerman\Worker;

class LeafMaster extends Worker
{
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
    /**
     * @var Storage
     */

    protected $storage = null;

    public function setStorage($redis)
    {
        $this->storage = $redis;
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
        $this->maxNumber  = $this->storage->get() ?? 0;
        $currentMaxNumber = $this->maxNumber;
        //创建leafWorker
        $bucketNumber = count(Config::getInstance()->get('worker'));
        foreach (Config::getInstance()->get('worker') as $key => $config) {
            if ($currentMaxNumber != 0) {
                $config['min']    = $this->getNextMin($currentMaxNumber, $bucketNumber);
                $config['max']    = $config['min'] + Config::getInstance()->get('step') - 1;
                $currentMaxNumber = $config['min'] + Config::getInstance()->get('step');
            }
            $this->runLeafWorker($worker, $config);
        }
        $this->runDisReqCenter(Config::getInstance()->get('distribution'));
    }

    public function onMessage($connection, $data)
    {
        $connection->close();
        list($cmd, $data) = Util::parse($data);
        if ($cmd === 'startedDisReq') {
            $timerId = Timer::add(10, function () use ($data, &$timerId) {
                $worker = $this->distributionNode;
                if (!$worker) {
                    $this->runDisReqCenter(Config::getInstance()->get('distribution'));
                }
                if (time() - $worker->lastPingTime > Config::getInstance()->get('timeOut')) {
                    $this->runDisReqCenter(Config::getInstance()->get('distribution'));
                    return;
                }
                Util::send($worker->getSocketName(), 'ping', [], [
                    'onMessage' => function ($connection, $data) use ($worker) {
                        $connection->close();
                        list($cmd, $data) = Util::parse($data);
                        if ($cmd == 'pong') {
                            $worker->lastPingTime = time();
                        }
                    }
                ]);
            });
        } elseif ($cmd == 'started') {
            $timerId = Timer::add(10, function () use ($data, &$timerId) {
                $worker = $this->getWorker($data['workerId']);
                if (!$worker) {
                    $this->removeLeafWorker($data['workerId']);
                    $this->runLeafWorker($this, $data);
                }

                if (!$worker) {
                    Timer::del($timerId);
                    return;
                }

                if (time() - $worker->lastPingTime > Config::getInstance()->get('timeOut')) {
                    $this->runLeafWorker($this, $data);
                    return;
                }

                Util::send($worker->getSocketName(), 'ping', [], [
                    'onMessage' => function ($connection, $data) use ($worker) {
                        $connection->close();
                        list($cmd, $data) = Util::parse($data);
                        if ($cmd == 'pong') {
                            $worker->lastPingTime = time();
                        }
                    }
                ]);
            });
        } else if ($cmd == 'numberOff') {
            $this->maxNumber = max($this->maxNumber, $data['number']);
            $this->storage->set($this->maxNumber);
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

    protected function getNextMin($currentMaxNumber = null, $bucketNumber = 0): int
    {
        $i                 = $currentMaxNumber ?? $this->maxNumber;
        $bucketSpaceNumber = ($bucketNumber <= 0 ? count($this->workerNodeList) : $bucketNumber) - 1;
        $bucketSpaceNumber = $bucketSpaceNumber > 0 ? $bucketSpaceNumber : 1;
        $bucketStep        = Config::getInstance()->get('step');
        return bcadd(bcmul($bucketStep, $bucketSpaceNumber), bcadd($i, 1));
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
