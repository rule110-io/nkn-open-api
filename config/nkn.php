<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Update interval
    |--------------------------------------------------------------------------
    |
    | This option controls the update interval of the blockchain crawler in
    | seconds. The suggested number is the current best value but you can
    | change it if you want.
    |
    */

    'update-interval' => env('UPDATEINTERVAL', 20),


    /*
    |--------------------------------------------------------------------------
    | Remote node
    |--------------------------------------------------------------------------
    |
    | This is the remote node (URL or IP) to sending RPCs to. You can change
    | the address as well as the querying port.
    |
    */

    'remote-node' => [
        'address' => env('REMOTENODE_ADDR', 'http://mainnet-seed-0006.nkn.org'),
        'port' => env('REMOTENODE_PORT', 30003)
    ]
];
