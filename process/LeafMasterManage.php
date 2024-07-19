<?php

namespace process;

use Predis\Client;
use service\LeafMaster;
use support\Redis;
use util\Config;
use util\Storage;
use Webman\Exception\No;
use Workerman\Timer;
use Workerman\Worker;

class LeafMasterManage
{
    protected $config = [];

    /**
     * @var Client $redis
     */
    protected $redis;

    private $master = null;

    public function __construct(array $config = [])
    {
        $this->config = Config::getInstance();
        $this->config->load($config);
        $this->redis = new Client(array_merge(config('redis.default'), ['scheme' => 'tcp']));
    }

    public function onWorkerStart(Worker $worker)
    {
        $this->master                 = new LeafMaster("text://{$this->config->get('master.listen')}");
        $this->master->onMessage      = [$this->master, 'onMessage'];
        $this->master->onWorkerStart  = [$this->master, 'onWorkerStart'];
        $this->master->onWorkerReload = [$this->master, 'onWorkerReload'];
        $this->master->count          = 1;
        $this->master->setStorage(new Storage(...array_values($this->config->get('storage'))));
        $this->master->run();
    }
}
