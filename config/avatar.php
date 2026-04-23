<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Avatar Configuration
    |--------------------------------------------------------------------------
     */

    'tmppath' => 'storage/app/tmp/',

    'profilepath' => 'files/profiles/',

    'templatepath' => [
        'avatar' => 'app/public/avatar/avatar/',
        'team' => 'app/public/avatar/team/'
    ],

    'sizes' => [
        'avatar' => [
            'width' => 300,
            'height' => 300
        ],
        'team' => [
            'width' => 300,
            'height' => 300
        ]
    ],

    'targetapiurl' => [
        'avatar' => env('AVATAR_API_URL'),
        'team' => ''
    ],

    'targettestpath' => [
        'avatar' => env('TEST_AVATAR_DIR'),
        'team' => ''
    ],

    'targeturl' => [
        'avatar' => '/auth/profiles/',
        'team' => ''
    ]


];
