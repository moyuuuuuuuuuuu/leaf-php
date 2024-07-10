<?php

namespace process;

use service\LeafMaster;
use Webman\Exception\NotFoundException;
use Workerman\Timer;
use Workerman\Worker;

class LeafMasterManage
{
    protected $config = [];

    private $master = null;

    private $worker = [];

    public function __construct(string $configFile)
    {
        if (!is_file($configFile)) {
            throw new NotFoundException('Leaf config file not found');
        }
        $this->config = json_decode(file_get_contents($configFile), true);
        if (json_last_error()) {
            throw new NotFoundException('Leaf config file format error');
        }
    }

    public function onWorkerStart(Worker $worker)
    {
        $this->master                 = new LeafMaster("text://{$this->config['master']['listen']}", $this->config);
        $this->master->onMessage      = [$this->master, 'onMessage'];
        $this->master->onWorkerStart  = [$this->master, 'onWorkerStart'];
//        $this->master->onError        = [$this->master, 'onError'];
        $this->master->onWorkerReload = [$this->master, 'onWorkerReload'];
//        $this->master->onClose        = [$this->master, 'onClose'];
//        $this->master->onConnect      = [$this->master, 'onConnnect'];
        $this->master->count = 1;
        $this->master->run();
    }
}
