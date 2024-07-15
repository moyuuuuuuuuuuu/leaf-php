<?php

namespace process;

use service\LeafMaster;
use util\Config;
use Webman\Exception\No;
use Workerman\Timer;
use Workerman\Worker;

class LeafMasterManage
{
    protected $config = [];

    private $master = null;

    public function __construct(array $config = [])
    {
        $this->config = Config::getInstance();
        $this->config->load($config);
    }

    public function onWorkerStart(Worker $worker)
    {
        $this->master                 = new LeafMaster("text://{$this->config->get('master.listen')}");
        $this->master->onMessage      = [$this->master, 'onMessage'];
        $this->master->onWorkerStart  = [$this->master, 'onWorkerStart'];
        $this->master->onWorkerReload = [$this->master, 'onWorkerReload'];
        $this->master->count          = 1;
        $this->master->run();
    }
}
