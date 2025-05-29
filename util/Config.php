<?php

namespace util;

class Config implements \ArrayAccess
{

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var Config
     */
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

    public function load(array $config = []): void
    {
        $this->config = $config;
    }

    public function get($offset): array|string|null
    {
        return $this->offsetGet($offset);
    }

    public function set($offset, $value): void
    {
        $this->offsetSet($offset, $value);
    }

    public function unset($offset): void
    {
        $this->offsetUnset($offset);
    }

    public function has($offset): bool
    {
        return $this->offsetExists($offset);
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($offset): bool
    {
        if (strstr($offset, '.')) {
            list($first, $second) = explode('.', $offset);
            return (bool)isset($this->config[$first][$second]);
        }
        return (bool)isset($this->config[$offset]);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet($offset): mixed
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
    public function offsetSet($offset, $value): void
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
    public function offsetUnset($offset): void
    {
        if (strstr($offset, '.')) {
            list($first, $second) = explode('.', $offset);
            unset($this->config[$first][$second]);
        } else {
            unset($this->config[$offset]);
        }
    }
}
