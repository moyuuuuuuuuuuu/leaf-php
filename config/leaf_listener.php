<?php

return [
    'master' => [
        'currentId' => 0,//当前生成的最大id
    ],
    'worker' => [
        [
            'url' => '127.0.0.1:9002',
            'min' => 1,
            'max' => 1000,
        ],
        [
            'url' => '127.0.0.1:9003',
            'min' => 1001,
            'max' => 2000,
        ],
    ]
];
