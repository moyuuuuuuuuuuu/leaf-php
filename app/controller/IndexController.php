<?php

namespace app\controller;

use GuzzleHttp\Client;
use service\RequestDistribution;
use support\Log;
use support\Request;
use util\Config;
use util\Util;

class IndexController
{
    public function index(Request $request)
    {
        return Util::get();
    }

}
