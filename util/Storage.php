<?php

namespace util;

class Storage
{
    protected $file;
    protected $encrypt = false;
    protected $apiKey  = '';
    const ALLOW__METHOD   = [
        'base64', 'openssl'
    ];
    const ENCRYPT_BASE    = 'base64';
    const ENCRYPT_OPENSSL = 'openssl';
    const ENCRYPT_NONE    = false;


    public function __construct($storageFile = '', $encrypt = false, $apiKey = '')
    {
        if (!is_dir(dirname($storageFile))) {
            mkdir(dirname($storageFile), 0755, true);
        }
        if (!is_file($storageFile)) {
            file_put_contents($storageFile, 0);
        }
        $this->file = $storageFile;

        $this->encrypt = $encrypt;

        $this->apiKey = $apiKey;
    }

    public function get()
    {
        $data = file_get_contents($this->file);
        if ($this->encrypt) {
            $data = $this->decrypt($data);
        }

        return (int)$data;
    }

    public function set($data)
    {
        var_dump($data);
        if ($this->encrypt) {
            $data = $this->encrypt($data);
        }
        file_put_contents($this->file, $data);
    }

    private function decrypt($data)
    {
        if (!in_array($this->encrypt, self::ALLOW__METHOD)) {
            return $data;
        }

        switch ($this->encrypt) {
            case self::ENCRYPT_BASE:
                return base64_decode($data);
            case self::ENCRYPT_OPENSSL:
                $algo = 'AES-256-CBC';
                $iv   = substr($data, 0, openssl_cipher_iv_length($algo));
                return openssl_decrypt(substr($data, openssl_cipher_iv_length($algo)), $algo, $this->apiKey, OPENSSL_RAW_DATA, $iv);
            case self::ENCRYPT_NONE:
                return $data;
        }
        return $data;
    }

    private function encrypt($data)
    {
        if (!in_array($this->encrypt, self::ALLOW__METHOD)) {
            return $data;
        }
        switch ($this->encrypt) {
            case self::ENCRYPT_BASE:
                return base64_encode($data);
            case self::ENCRYPT_OPENSSL:
                $algo = 'AES-256-CBC';
                $iv   = openssl_random_pseudo_bytes(openssl_cipher_iv_length($algo));
                return base64_encode($iv . openssl_encrypt($data, $algo, $this->apiKey, OPENSSL_RAW_DATA, $iv));
            case self::ENCRYPT_NONE:
                return $data;
        }
        return $data;
    }
}
