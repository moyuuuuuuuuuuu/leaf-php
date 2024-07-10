<?php

namespace service;

use Webman\Exception\NotFoundException;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;
use Workerman\Worker;

class LeafWorker extends Worker
{
    public $onMessage      = [self::class, 'onMessage'];
    public $onWorkerStart  = [self::class, 'onWorkerStart'];
    public $onError        = [self::class, 'onError'];
    public $onWorkerReload = [self::class, 'onWorkerReload'];
    public $onClose        = [self::class, 'onClose'];

    protected $masterListen = '';
    protected $isMaster     = false;
    protected $config       = null;
    protected $currentId    = 0;
    /**
     * 是否可以发号
     * 当发号到最大值时，请求master更新取号范围期间不可发号
     * @var bool
     */
    protected $canGiveOffer = true;

    public function __construct($socket_name = '', array $config = [], string $master = '', array $context_option = array())
    {
        parent::__construct($socket_name, $context_option);
        if (!$config) {
            throw new NotFoundException('Leaf config file format error');
        }
        if (!$master) {
            throw new NotFoundException('Leaf master listen not found');
        }
        $this->masterListen = $master;
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
                'workerId'     => $worker->id,
                'listen'       => $this->config['listen'],
                'pidFile'      => $worker::$pidFile,
                'lastPingTime' => time()
            ]
        ];
        fwrite($master, json_encode($data) . "\n");
    }

    public function onMessage(TcpConnection $connection, $data)
    {
        $data = json_decode($data, true);
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        $cmd  = $data['cmd'];
        $data = $data['data'] ?? [];
        if ($cmd == 'ping') {
            $data   = [
                'workerId'     => $this->id,
                'listen'       => $this->config['listen'],
                'lastPingTime' => time(),
            ];
            $master = stream_socket_client('tcp://' . $this->masterListen);
            $data   = ['cmd' => 'pong', 'data' => $data];
            fwrite($master, json_encode($data) . "\n");
            fclose($master);

        } else if ($cmd == 'offer') {
            //发号
            if (!$this->canGiveOffer) {
                $connection->send(json_encode(['status' => 'wait', 'no' => null]));
                return;
            }
            //取号
            $connection->send(json_encode(['status' => 'success', 'no' => $this->currentId]));

            $master = stream_socket_client('tcp://' . $this->masterListen);
            $data   = ['cmd' => 'numberOff', 'data' => ['workerId' => $this->id, 'number' => $this->currentId]];
            fwrite($master, json_encode($data) . "\n");
            fclose($master);

            if ($this->currentId + 1 <= $this->config['max']) {
                $this->currentId++;
            } else {
                $this->canGiveOffer = false;
                $master             = stream_socket_client('tcp://' . $this->masterListen);
                $data               = ['cmd' => 'updateRange', 'data' => ['workerId' => $this->id]];
                fwrite($master, json_encode($data) . "\n");
                fclose($master);

            }

        } else if ($cmd == 'updateRange') {
            //更新取号范围
            $this->currentId    = $data['min'];
            $this->canGiveOffer = true;
            $this->config       = array_merge($this->config, $data);
        }
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
