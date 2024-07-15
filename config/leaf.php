<?php

return [
    'step'         => 10,
    'timeOut'      => 50,
    'fill'         => false,
    'master'       => [
        'currentId' => 0,
        'listen'    => '127.0.0.1:9001',
    ],
    'distribution' => [
        'listen'    => '127.0.0.1:9090',
        'count'     => 6,
        'reusePort' => true,
        'token'     => 'leaf',
    ],
    'worker'       => [
        [
            'listen' => '127.0.0.1:9002',
            'min'    => 1,
            'max'    => 10,
            'step'   => 1,
        ],
        /*[
            'listen' => '127.0.0.1:9003',
            'min'    => 1001,
            'max'    => 2000,
            'step'   => 1,
        ],*/
    ],
];
