<?php

namespace service;

use util\{Config, Util};
use Workerman\Connection\TcpConnection;
use Workerman\Worker;

class DisReqCenter extends Worker
{

    public    $lastPingTime;
    protected $master;

    public function __construct($socket_name = '', LeafMaster $master = null)
    {
        parent::__construct($socket_name);
        $this->master = $master;
    }

    public function onWorkerStart(self $worker)
    {
        Util::send($this->master->getSocketName(), 'startedDisReq', [
            'workerId'     => $worker->workerId,
            'listen'       => Config::getInstance()->get('distribution.listen'),
            'lastPingTime' => $this->lastPingTime ?? time(),
        ]);
    }

    public function onMessage(TcpConnection $connection, $data)
    {
        list($cmd,) = Util::parse($data);
        if (!$cmd) {
            $connection->send(json_encode(['cmd' => 'error', 'data' => 'cmd not found']));
            $connection->close();
            return;
        }
        if ($cmd == 'offer') {
            //检测白名单以及token
            /* $remoteIp = $connection->getRemoteIp();
             $token    = $data['signature'];
             if (!empty(Config::getInstance()->get('whiteList')) && !in_array($remoteIp, Config::getInstance()->get('whiteList'))) {
                 $connection->send(json_encode(['cmd' => 'fail', 'data' => 'ip not in white list']));
                 $connection->close();
                 return;
             }*/
            $worker = $this->master->getNodes();
            foreach ($worker as $node) {
                if (!$node->isLock() && $node->hasNumber()) {
                    $node->setLock($connection->id);
                    $result = $node->getOffer(true, $connection->id);
                    $connection->send(json_encode($result));
                    $connection->close();
                    $node->unlock();
                    return;
                }
            }
            $connection->send(json_encode(['cmd' => 'fail', 'data' => 'node is busy']));
            $connection->close();
            return;
        } elseif ($cmd == 'ping') {
            $this->lastPingTime = time();
            /* $data               = [
                 'workerId'     => $this->workerId,
                 'listen'       => Config::getInstance()->get('distribution.listen'),
                 'lastPingTime' => $this->lastPingTime,
             ];*/
//            Util::send($this->master->getSocketName(), 'pong', $data);
            return;
        }
        $connection->send(json_encode(['cmd' => 'fail', 'data' => 'do nothing']));
        $connection->close();
    }
}
