<?php

namespace app\controller;

use service\RequestDistribution;
use support\Log;
use support\Request;

class IndexController
{
    public function index(Request $request)
    {
        $master = stream_socket_client('tcp://127.0.0.1:8080', $errorno, $errorstr, 1, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT);
        $data   = ['cmd' => 'offer', 'data' => ['fill' => false]];
        fwrite($master, json_encode($data) . "\n");
        // 准备读取服务器响应
        $response = '';
        while (!feof($master)) {
            $response .= fread($master, 1024);
            if (strpos($response, "\n") !== false) {
                break;
            }
        }
        fclose($master);
        echo $response;
        Log::log('info', "Controller/" . $response);
        return json(json_decode($response));
    }

}
