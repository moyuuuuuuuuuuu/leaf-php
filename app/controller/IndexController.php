<?php

namespace app\controller;

use GuzzleHttp\Client;
use service\RequestDistribution;
use support\Log;
use support\Request;
use util\Config;

class IndexController
{
    public function index(Request $request)
    {
        $stream = stream_socket_client('tcp://127.0.0.1:9090', $errorno, $errorstr, 1, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT);
        $data   = ['cmd' => 'offer', 'data' => ['fill' => false]];
        fwrite($stream, json_encode($data) . "\n");
        // 准备读取服务器响应
        $response = '';
        while (!feof($stream)) {
            $response .= fread($stream, 1024);
            if (strpos($response, "\n") !== false) {
                break;
            }
        }
        fclose($stream);
        echo $response . PHP_EOL;
//        Log::log('info', "Controller/" . $response);
        return $response;
    }

}
