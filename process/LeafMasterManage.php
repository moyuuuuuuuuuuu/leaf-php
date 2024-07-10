<?php

namespace process;

use service\LeafMaster;
use Webman\Exception\NotFoundException;

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
        $this->config = json_decode(file_get_contents($configFile));
        if (json_last_error()) {
            throw new NotFoundException('Leaf config file format error');
        }
    }

    protected function buildMaster()
    {
        $this->master        = new LeafMaster("text://{$this->config['listen']}");
        $this->master->count = 1;
        $this->master->listen();
    }
}
