<?php

namespace service;


use Workerman\Connection\AsyncTcpConnection;

class InnerTcpConnection
{

    protected static $instance = null;

    /**
     * @var AsyncTcpConnection[] $addressConnection
     */
    protected $addressConnection = [];

    protected function __construct()
    {
    }

    public static function getInstance()
    {
        if (self::$instance) {
            return self::$instance;
        }
        self::$instance = new self;
        return self::$instance;
    }

    public function listen($address, $context = [], callable $onMessage = null, callable $onError = null, callable $onClose = null, callable $onConnect = null)
    {
        if (isset($this->addressConnection[$address])) {
            return self::getInstance();
        }
        $asyncTcpConnection = new AsyncTcpConnection($address, $context);
        if ($onMessage) {
            $asyncTcpConnection->onMessage = $onMessage;
        }
        if ($onError) {
            $asyncTcpConnection->onError = $onError;
        }
        if ($onClose) {
            $asyncTcpConnection->onClose = $onClose;
        }
        if ($onConnect) {
            $asyncTcpConnection->onConnect = $onConnect;
        }
        $asyncTcpConnection->connect();
        $this->addressConnection[$address] = $asyncTcpConnection;
        return self::getInstance();
    }

    public function send($address, string $cmd, ?array $data = null): void
    {
        if ($data) {
            $data['data'] = $data;
        }
        $data['cmd'] = $cmd;
        $this->addressConnection[$address]->send(json_encode($data));
    }


}
