<?php

namespace service;

use Workerman\Worker;

class BaseLeaf extends Worker
{
    public function parse(string $data)
    {
        $data = json_decode($data, true);
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        $cmd  = $data['cmd'] ?? null;
        $data = $data['data'] ?? [];
        return [$cmd, $data];
    }


    /**
     * @param $key
     * @return array|string
     */
    public function getConfig($key = '*', $defaultValue = '')
    {
        if (strstr($key, '.')) {
            list($firstName, $secondName) = explode('.', $key);
            return $this->config[$firstName][$secondName] ?? $defaultValue;
        } else if ($key != '*') {
            return $this->config[$key] ?? $defaultValue;
        } else {
            return $this->config;
        }
    }

}
