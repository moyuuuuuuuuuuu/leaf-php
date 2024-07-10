<?php

namespace app\controller;

use support\Request;

class IndexController
{
    public function index(Request $request)
    {
        $master = stream_socket_client('tcp://127.0.0.1:900' . rand(2, 5));
        $data   = ['cmd' => 'offer'];
        fwrite($master, json_encode($data) . "\n");
        $response = fread($master, 1024);
        fclose($master);
        echo $response;
        return json(json_decode($response));

    }

}
