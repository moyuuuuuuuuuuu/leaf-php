<?php

namespace service;

use support\Log;
use Webman\Exception\NotFoundException;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;
use Workerman\Worker;

class LeafWorker extends BaseLeaf
{
    public    $onMessage      = [self::class, 'onMessage'];
    public    $onWorkerStart  = [self::class, 'onWorkerStart'];
    public    $onWorkerReload = [self::class, 'onWorkerReload'];
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

    public function onWorkerStart(Worker $worker)
    {
        InnerTcpConnection::getInstance()->listen('text://' . $this->masterListen, [], function (AsyncTcpConnection $connection, $data) use (&$worker) {
            Log::log('INFO', 'LeafTcpConnection:' . $data . PHP_EOL);
            list($cmd, $data) = $worker->parse($data);
            if ($cmd == 'updateRange') {
                $this->currentId    = $data['min'];
                $this->config       = array_merge($this->config, $data);
                $this->canGiveOffer = true;
            }
        })->send('text://' . $this->masterListen, 'started', [
            'workerId'     => $worker->workUinId,
            'listen'       => $this->getConfig('listen'),
            'pidFile'      => $worker::$pidFile,
            'lastPingTime' => time()
        ]);
    }

    public function onMessage(TcpConnection $connection, $data)
    {
        Log::log('INFO', 'LeafWorker:' . $data . PHP_EOL);
        list($cmd, $data) = $this->parse($data);
        if ($cmd == 'ping') {
            $data = [
                'workerId'     => $this->workUinId,
                'listen'       => $this->getConfig('listen'),
                'lastPingTime' => time(),
            ];
            InnerTcpConnection::getInstance()->send('text://' . $this->masterListen, 'pong', $data);
        } else if ($cmd == 'offer') {
            //发号
            if ($this->currentId + ($this->getConfig('step', 1)) > $this->getConfig('max')) {
                $connection->send(json_encode(['status' => 'fail', 'msg' => 'The number has been used up']));
                $connection->close();
                $data = ['workerId' => $this->workUinId];
                InnerTcpConnection::getInstance()->send('text://' . $this->masterListen, 'updateRange', $data);
                return;
            }
            //取号
            $currentId = $this->currentId;
            $fill      = $data['fill'] ?? false;
            if ($fill) {
                $currentId = str_pad($currentId, 10, 0, STR_PAD_LEFT);
            }
            $connection->send(json_encode(['status' => 'success', 'no' => $currentId]));
            $connection->close();
            //通知master更新已发的最大号
            $data = ['workerId' => $this->workUinId, 'number' => $this->currentId];
            InnerTcpConnection::getInstance()->send('text://' . $this->masterListen, 'numberOff', $data);
            if ($this->currentId + ($this->getConfig('step', 1)) <= $this->getConfig('max')) {
                $this->currentId += $this->getConfig('step', 1);
            } else {
                $data = ['workerId' => $this->workUinId];
                InnerTcpConnection::getInstance()->send('text://' . $this->masterListen, 'updateRange', $data);
            }
        }
    }

    public function onWorkerReload(self $worker)
    {
        $worker->reload();
    }
}
