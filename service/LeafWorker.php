<?php

namespace service;

use util\{Config, Util};
use support\Log;
use Webman\Exception\NotFoundException;
use Workerman\Connection\TcpConnection;
use Workerman\Worker;

class LeafWorker extends Worker
{
    protected $master    = '';
    protected $config    = null;
    protected $currentId = 0;
    protected $address   = '';
    protected $lock      = false;

    protected $hasNumber = true;

    protected $workUinId = 0;

    public function hasNumber()
    {
        return $this->hasNumber;
    }

    public function isLock(): bool
    {
        return $this->lock;
    }

    public function setLock($lockNum)
    {
        $this->lock = $lockNum;
    }

    public function unlock()
    {
        $this->lock = false;
    }

    /**
     * @throws NotFoundException
     */
    public function __construct($socket_name = '', array $config = [], LeafMaster $master = null, array $context_option = array())
    {
        if (!$config) {
            throw new NotFoundException('Leaf Worker config file format error');
        }
        parent::__construct($socket_name, $context_option);
        $this->master    = $master;
        $this->config    = $config;
        $this->currentId = $config['min'];
        $this->max       = $config['max'];
    }

    /**
     * @param self $worker
     * @return void
     */
    public function onWorkerStart(LeafWorker $worker)
    {
        Util::send($this->master->getSocketName(), 'started', [
            'workerId'     => $worker->workerId,
            'listen'       => $worker->getConfig('listen'),
            'lastPingTime' => time(),
            'w'            => static::class
        ]);
    }

    public function onMessage(TcpConnection $connection, $data)
    {
        $connection->close();
        list($cmd, $data) = Util::parse($data);
        if ($cmd == 'ping') {
            $data = [
                'workerId'     => $this->workerId,
                'listen'       => $this->config['listen'],
                'lastPingTime' => time(),
                'w'            => static::class
            ];
            Util::send($this->master->getSocketName(), 'pong', $data);
        } else if ($cmd == 'updateRange') {
            $this->currentId = $data['min'];
            $this->config    = array_merge($this->config, $data);
            $this->hasNumber = true;
        }
    }

    public function onWorkerReload(self $worker)
    {
        $worker->reload();
    }

    public function getOffer($withLock = false, $lockNum = null): array
    {

        //发号
        if ($this->lock != $lockNum && $withLock) {
            return ['status' => 'fail', 'msg' => 'The bucket is locked'];
        }

        if (!$this->hasNumber) {
            $data = ['workerId' => $this->workerId];
            Util::send($this->master->getSocketName(), 'updateRange', $data);
            return ['status' => 'fail', 'msg' => 'The bucket has no number'];
        }
        //取号
        $currentId = $this->currentId;
        $fill      = $data['fill'] ?? false;
        if ($fill) {
            $currentId = str_pad($currentId, 10, 0, STR_PAD_LEFT);
        }
        $result = ['status' => 'success', 'no' => $currentId];
        //通知master更新已发的最大号
        $data = ['workerId' => $this->workerId, 'number' => $this->currentId];
        Util::send($this->master->getSocketName(), 'numberOff', $data);
        if ($this->currentId + $this->config['step'] <= $this->config['max']) {
            $this->currentId += $this->config['step'];
        } else {
            $data = ['workerId' => $this->workerId];
            Util::send($this->master->getSocketName(), 'updateRange', $data);
            $this->hasNumber = false;
        }
        return $result;
    }

    /**
     * @param string $key
     * @param string $defaultValue
     * @return array|string
     */
    public function getConfig(string $key = '*', string $defaultValue = '')
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
}
