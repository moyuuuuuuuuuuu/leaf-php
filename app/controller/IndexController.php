<?php

namespace app\controller;

use support\Request;

class IndexController
{
    public function index(Request $request)
    {
        var_dump('tcp://127.0.0.1:900' . rand(2, 3));
        $master = stream_socket_client('tcp://127.0.0.1:900' . rand(2, 3));
        $data   = ['cmd' => 'offer'];
        fwrite($master, json_encode($data) . "\n");
        $response = fread($master, 1024);
        fclose($master);
        return json(json_decode($response));

    }

}
