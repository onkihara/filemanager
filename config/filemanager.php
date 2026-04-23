<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Filemanager config
    |--------------------------------------------------------------------------
    |
    | 
    |
    */

    // paths (e.g.: files/raws/2018/04/12345.jpg)
    'paths' => [
        'file' => 'raws',
        'thumb' => 'thumbs',
        'sub' => 'month', // year | month
        'tempdir' => 'tmp',
    ],

    // Thumbnails
    'thumbnail' => [
        'on'        => true,
        'path'      => '',
        'extension' =>  'jpg',
        'prefix'    =>  'tn_',
        'width'     =>  150,
        'height'    =>  112,
        'thumbables' => 'jpg,jpeg,png,gif',
        'icons'     => 'ico',
        'frame_seconds' => 3, // for video thumbnails
        'overlays' => [
            'video' => 'video/',
            'audio' => 'audio/',
        ]
    ],

    'maxfilesize' => '500', // MByte

    /**
     * File-Types
     */
    'filetypes' => [
        1 => ['viewable'        => 'jpg,jpeg,gif,png,svg,webp'],
        2 => ['audible'         => 'mp3,ogg,flac,aac,ac3,aiff,m4a,wav,wma,opus'],
        4 => ['videable'        => 'mp4,ogv,webm,mov,avi,mpg,mpeg,flv,wmv,3gp'],
        8 => ['documentable'    => 'doc,docx,odt,xls,xlsx,accdb,ade,adp,ai,bmp,css,csv'
                                   .'html,log,mdb,odb,odf,odg,odp,ods,otp,otg,ots,ott,pdf,ppt,pptx,psd,rtf,'
                                   .'sql,tiff,txt,xhtml,xml'],
        16 => ['zipable'        => 'zip,rar,tar,7z,gz,dmg,iso'],
        128 => ['unknown'       => ''],
        255 => ['all'           => ''],
    ],

    'conversions' => [
        'video' => [
            'max_width' => '720',
            'max_height' => '720',
            'format' => 'WebM', // Ffmpeg-Video-Format (e.g. WebM, WMV, WMV3, Ogg, X264)
            'extension' => 'webm',
            'mime_type' => 'video/webm',
        ],
        'audio' => [
            'format' => 'Mp3', // Ffmpeg-Audio-Format (e.g. Mp3, Wav, Flac, Vorbis, Aac)
            'bitrate' => '128', // in kBit/s
            'extension' => 'mp3',
            'mime_type' => 'audio/mpeg',
        ],
        'timeout' => 30 * 60 * 60, // in seconds
        'threads' => 24,
    ],



    'numberperpage' => 20,

    // time in minutes for garbace comallection in tmp-dir
    'gctime' => 30,
    
];
