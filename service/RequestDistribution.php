<?php

namespace service;

use service\BaseLeaf;
use Workerman\Connection\TcpConnection;

class RequestDistribution extends BaseLeaf
{
    protected $master;

    public function __construct($socket_name = '', LeafMaster $master = null)
    {
        parent::__construct($socket_name, []);
        $this->master = $master;
    }

    public function onMessage(TcpConnection $connection, $data)
    {
        list($cmd, $data) = $this->parse($data);
        if (!$cmd) {
            $connection->send(json_encode(['cmd' => 'error', 'data' => 'cmd not found']));
            $connection->close();
            return;
        }
        if ($cmd == 'offer') {
            $worker = $this->master->getNodes();
            foreach ($worker as $node) {
                if ($node->getCanGiveOff()) {
                    $result = $node->getOffer();
                    $connection->send(json_encode($result));
                    $connection->close();
                    return;
                }
            }
            $connection->send(json_encode(['cmd' => 'fail', 'data' => 'node is busy']));
            $connection->close();
            return;
        }
        $connection->send(json_encode(['cmd' => 'fail', 'data' => 'do nothing']));
        $connection->close();
        return;


    }
}
