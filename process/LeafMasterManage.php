<?php

namespace process;

use Predis\Client;
use service\LeafMaster;
use support\Redis;
use util\Config;
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
        if (function_exists('event_base_new')) {
            Worker::$globalEvent = new Epoll();
        }
        $this->master                 = new LeafMaster("text://{$this->config->get('master.listen')}");
        $this->master->onMessage      = [$this->master, 'onMessage'];
        $this->master->onWorkerStart  = [$this->master, 'onWorkerStart'];
        $this->master->onWorkerReload = [$this->master, 'onWorkerReload'];
        $this->master->count          = 1;
        $this->master->setRedis($this->redis);
        $this->master->run();
        Timer::add(5, function () {
            // 检查 Redis 连接
            try {
                $this->redis->ping();
            } catch (\Exception $e) {
                $this->redis = new Client(array_merge(config('redis.default'), ['scheme' => 'tcp']));
            }
        });
    }
}
