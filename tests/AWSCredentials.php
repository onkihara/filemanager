<?php

namespace Tests;

use App\Custom\AWS;

trait AWSCredentials
{

    public $awscredentials = [
        'policy' => 'eyJleHBpcmF0aW9uIjoiMjAyNC0wMS0yNFQxMToyOTo0MC4zNDlaIiwiY29uZGl0aW9ucyI6W3siYWNsIjoicHJpdmF0ZSJ9LHsiYnVja2V0IjoiZmlsZXMifSx7IkNvbnRlbnQtVHlwZSI6ImltYWdlXC9qcGVnIn0seyJzdWNjZXNzX2FjdGlvbl9zdGF0dXMiOiIyMDAifSx7IngtYW16LWFsZ29yaXRobSI6IkFXUzQtSE1BQy1TSEEyNTYifSx7ImtleSI6ImNmZGQzODQ2LTBjNGQtNDZlYS1hMzNiLWY4ODIxZTMwZTcwMFwvMjIwcHgtU2lyX1dpbnN0b25fU19DaHVyY2hpbGwuanBnIn0seyJ4LWFtei1jcmVkZW50aWFsIjoiQUtJQVpJMkxFWlBUWTZLUDdGVFNcLzIwMjQwMTI0XC9ldS1jZW50cmFsLTFcL3MzXC9hd3M0X3JlcXVlc3QifSx7IngtYW16LWRhdGUiOiIyMDI0MDEyNFQxMTI0NDBaIn0seyJ4LWFtei1tZXRhLXVuaXF1ZV9mb2xkZXJfbmFtZSI6ImNmZGQzODQ2LTBjNGQtNDZlYS1hMzNiLWY4ODIxZTMwZTcwMCJ9LHsieC1hbXotbWV0YS1tZWRpYV90b19yZXBsYWNlX2lkIjoibnVsbCJ9LHsieC1hbXotbWV0YS1xcWZpbGVuYW1lIjoiMjIwcHgtU2lyX1dpbnN0b25fU19DaHVyY2hpbGwuanBnIn1dfQ==',
        'x-amz-signature' => '2c828dd7bf3b9217fb26546f9273cf1a18e9582582765987e716c9f4cbd4d2e1'
    ];


    public function createSignedHeaders(string $method, string $path, array $query = [], string $content = '')
    {
        $date = date('Ymd\TGis\Z');
        $headers = [
            'credential' => config('auth.s3.key').'/'.date('Ymd').'/eu-central-1/s3/aws4_request',
            'signedheaders' => 'host;x-amz-date',
            'method' => $method,
            'path' => $path,
            'query' => $query,
            'hashed_content' => hash('sha256',$content),
            'date' => $date,
            'host' => 'files.blikk.test',
            'x-amz-date' => $date
        ];
        $signature = (new AWS)->createSignature($headers);
        return [
            'authorization' => "AWS4-HMAC-SHA256 Credential=".$headers['credential'].", SignedHeaders=".$headers['signedheaders'].', Signature='.$signature,
            'host' => $headers['host'],
            'x-amz-date' => $date
        ];
    }

    /*
    string(231) "AWS4-HMAC-SHA256 Credential=AKIAZI2LEZPTY6KP7FTS/20240130/eu-central-1/s3/aws4_request, SignedHeaders=host;x-amz-content-sha256;x-amz-date;x-amz-user-agent, Signature=3583c1569e7bf999482459e4e88cd684c9436b21647cb73e7f46dd46f88a3771"
    */


}
