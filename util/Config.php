<?php

namespace util;

class Config implements \ArrayAccess
{

    protected        $config = [];
    protected static $instance;

    private function __construct()
    {
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Config();
        }
        return self::$instance;
    }

    public function load(array $config = [])
    {
        $this->config = $config;
    }

    public function get($offset)
    {
        return $this->offsetGet($offset);
    }

    public function set($offset, $value)
    {
        $this->offsetSet($offset, $value);
    }

    public function has($offset)
    {
        return $this->offsetExists($offset);
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($offset)
    {
        if (strstr($offset, '.')) {
            list($first, $second) = explode('.', $offset);
            return isset($this->config[$first][$second]);
        }
        return isset($this->config[$offset]);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet($offset)
    {
        if (!$this->offsetExists($offset)) {
            return null;
        }
        if (strstr($offset, '.')) {
            list($first, $second) = explode('.', $offset);
            return $this->config[$first][$second] ?? null;
        }
        return $this->config[$offset] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function offsetSet($offset, $value)
    {
        if (strstr($offset, '.')) {
            list($first, $second) = explode('.', $offset);
            $this->config[$first][$second] = $value;
        } else {
            $this->config[$offset] = $value;
        }
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset($offset)
    {
        if (strstr($offset, '.')) {
            list($first, $second) = explode('.', $offset);
            unset($this->config[$first][$second]);
        } else {
            unset($this->config[$offset]);
        }
    }
}
