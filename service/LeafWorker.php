<?php

namespace service;

use Webman\Exception\NotFoundException;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;
use Workerman\Worker;

class LeafWorker extends Worker
{
    public    $onMessage      = [self::class, 'onMessage'];
    public    $onWorkerStart  = [self::class, 'onWorkerStart'];
    public    $onWorkerReload = [self::class, 'onWorkerReload'];
    protected $workId         = 0;
    protected $masterListen   = '';
    protected $isMaster       = false;
    protected $config         = null;
    protected $currentId      = 0;
    protected $master         = null;
    /**
     * 是否可以发号
     * 当发号到最大值时，请求master更新取号范围期间不可发号
     * @var bool
     */
    protected $canGiveOffer = true;

    public function __construct($socket_name = '', array $config = [], string $masterListen = '', LeafMaster $master = null, array $context_option = array())
    {
        parent::__construct($socket_name, $context_option);
        $this->workUinId = rand(1000, 9999);
        echo $this->workUinId . PHP_EOL;
        if (!$config) {
            throw new NotFoundException('Leaf config file format error');
        }
        if (!$master) {
            throw new NotFoundException('Leaf master listen not found');
        }
        $this->masterListen = $masterListen;
        $this->master       = $master;
        $this->config       = $config;
        $this->currentId    = $config['min'];
    }

    public function setMaster(string $listen)
    {
        $this->masterListen = $listen;
    }

    public function onWorkerStart(Worker $worker)
    {
        $master = stream_socket_client('tcp://' . $this->masterListen);
        $data   = [
            'cmd'  => 'started',
            'data' => [
                'workerId'     => $worker->workUinId,
                'listen'       => $this->getConfig('listen'),
                'pidFile'      => $worker::$pidFile,
                'lastPingTime' => time()
            ]
        ];
        fwrite($master, json_encode($data) . "\n");
    }

    public function onMessage(TcpConnection $connection, $data)
    {
        echo "LeafWorker" . $data . PHP_EOL;
        $data = json_decode($data, true);
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        $cmd    = $data['cmd'];
        $data   = $data['data'] ?? [];
        $master = stream_socket_client('tcp://' . $this->masterListen);
        if ($cmd == 'ping') {
            $data = [
                'workerId'     => $this->workUinId,
                'listen'       => $this->getConfig('listen'),
                'lastPingTime' => time(),
            ];
            $data = ['cmd' => 'pong', 'data' => $data];
            fwrite($master, json_encode($data) . "\n");

        } else if ($cmd == 'offer') {
            //发号
            if (!$this->canGiveOffer) {
                $connection->send(json_encode(['status' => 'wait', 'message' => 'This bucket is refreshing ,please try again later', 'no' => null]));
                return;
            }
            //取号
            $currentId = $this->currentId;
            if ($this->master->getConfig('fill')) {
                $currentId = str_pad($currentId, 10, 0, STR_PAD_LEFT);
            }
            $connection->send(json_encode(['status' => 'success', 'no' => $currentId]));
            //通知master更新已发的最大号
            $data = ['cmd' => 'numberOff', 'data' => ['workerId' => $this->workUinId, 'number' => $this->currentId]];
            fwrite($master, json_encode($data) . "\n");
            if ($this->currentId + ($this->getConfig('step', 1)) <= $this->getConfig('max')) {
                $this->currentId += $this->getConfig('step', 1);
            } else {
                $this->canGiveOffer = false;
                $data               = ['cmd' => 'updateRange', 'data' => ['workerId' => $this->workUinId]];
                fwrite($master, json_encode($data) . "\n");
            }
        } else if ($cmd == 'updateRange') {
            $this->currentId    = $data['min'];
            $this->config       = array_merge($this->config, $data);
            $this->canGiveOffer = true;
        }
        fclose($master);

    }

    public function onWorkerReload(self $worker)
    {
        $worker->reload();
    }

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
}
