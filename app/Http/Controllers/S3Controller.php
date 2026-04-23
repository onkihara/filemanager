<?php

namespace App\Http\Controllers;

use Image;
use Storage;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\File;

class S3Controller extends B3Controller
{

    public function process(Request $request, string $key='')
    {
        
        // ob_start();
        // var_dump(
        //     $request->fullUrl(),
        //     $key,
        //     $request->method(),
        //     $request->header(),
        //     $request->all(),
        //     $request->getContent()
        // );
        // info(ob_get_clean());
        //dd($request->all());

        // download request
        if ($request->has('direct-access')) {
            return $this->direct($request,$key);
        }

        // listObjectsV2:
        if ($request->has('list-type') && $request->input('list-type') == 2) {
            return $this->listObjectsV2($request);
        }

        // headObject
        if ($request->method() == 'HEAD') {
            return $this->headObject($request,$key);
        }

        throw new \Exception('Command not implemented!');
    }

    /**
     * Upload-Management for the Filemanager
     *
     * @param Request $request
     * @return Response
     */
    public function upload(Request $request, File $file)
    {
        //info($request->all());info($request->header());
        $request->validate([
            'key' => 'required|string',
            'file' => 'required|file',
            'policy' => 'required|string',
            'x-amz-signature' => 'required|string',
        ]);

        //ob_start();var_dump($request->all());info('upload');info(ob_get_clean());

        // use parent to store
        $request->merge(['path' => $request->input('key')]);
        $request->merge(['scope' => 'S3']);
        $request->merge(['by' => $request->header('origin')]);
        $result = parent::upload($request, $file);

        if ($result->getStatusCode() != 200) {
            throw new \Exception('S3: Cannot process request upload!');
        }

        $res = json_decode($result->getContent());

        // this return AWS S3
        return response()->json([
            'Bucket' => 'files',
            'ETag' => md5($res->vpath),
            'Key' => $res->vpath,
            'Location' => $res->url
        ]);

    }



    /**
     * Download via direct access, checking for modifyer
     * /vpath/filename.ext::modifyer
     * 
     * supported:   - ::download
     *  
     *             
     */
    protected function direct(Request $request, string $key)
    {
        // get modifier
        $key_parts = explode('::',$key);

        // modifyer action
        if (isset($key_parts[1])) {

            // download
            if ($key_parts[1] == 'download') {
                return $this->download($request, $key_parts[0]);
            }

            // unknown modifyer
            throw new \Exception('S3: Unknown modifyer used!');
        }

        // determine action by extension-type
        $file = (new File)->getOriginalFromVirtualpath($key_parts[0])->first();
        if ( ! $file ) {
            abort(404);
        }

        // image
        if ($file->isViewable()) {
            return Image::make(
                Storage::disk($file->getFiledisk())->get($file->getFilepath())
            )->response();
        }

        // media (mit "streaming")
        if ($file->isMediable()) {
            //return $file->stream();
            return $file->streamMedia();
        }

        // download als default (streaming for large downloads)
        return $file->download();
    }



    /**
     * Download via download access
     */
    protected function download(Request $request, string $key)
    {
        $file = (new File)->getOriginalFromVirtualpath($key)->first();

        if ( ! $file ) {
            abort(404);
        }

        return $file->download();
    }

    /**
     * Delete-Management for the Filemanager
     * Deletes all (!) files with this $key (Virtualpath and Original name)
     *
     * @param Request $request
     * @return Response
     */
    public function delete(Request $request, $key)
    {
        $files = (new File)->getOriginalFromVirtualpath($key);
        foreach ($files as $file) {
            $file->delete();
        }

        return response('',204);
    }



    private function listObjectsV2(Request $request)
    {
        $fm = new File;

        // prefix
        $prefix = '';
        $delimiter = '/';
        if ($request->has('prefix')) {
            $prefix = urldecode($request->input('prefix'));
            if (strpos($prefix, $delimiter) !== false) {
                $query = $fm->where('Virtualpath','like',$prefix);
            } else {
                $query = $fm->where('Original','like',$prefix.'%');
            }
        } else {
            $query = $fm->where('Virtualpath',$prefix);
        }
        $files = $query->get();

        // delimiter
        $paths = collect();
        if ($request->has('delimiter')) {
            $delimiter = urldecode($request->input('delimiter'));
            $delprefix = strpos($prefix, $delimiter) === false ? '' : $prefix;
            $paths = $fm->where('Virtualpath','like',$delprefix.'%'.$delimiter)->get()->pluck('Virtualpath');
            $paths = $paths->map(function($path) use ($delprefix) {
                $delprefix = str_replace('/', '\/', $delprefix);
                preg_match('/^'.$delprefix.'[^\/]*\//u',$path,$matches);
                return $matches[0];
            })->unique();
        }

        // create xmls
        $xml = '
        <?xml version="1.0" encoding="UTF-8"?>
        <ListBucketResult>
            <Name>'.trim($request->getPathInfo(),'/').'</Name>
            <Prefix>'.$prefix.'</Prefix>
            <KeyCount>'.$files->count().'</KeyCount>
            <MaxKeys>1000</MaxKeys>
            <Delimiter>'.$delimiter.'</Delimiter>
            <IsTruncated>false</IsTruncated>';
        foreach ($files as $file) {
            $xml .= '
            <Contents>
                <Key>'.$file->Original.'</Key>
                <LastModified>'.$file->CreationDate.'</LastModified>
                <ETag>"'.md5($file->Original.$file->CreationDate.$file->Size).'"</ETag>
                <Size>'.$file->Size.'</Size>
                <StorageClass>STANDARD</StorageClass>
            </Contents>';
        }
        foreach ($paths as $path) {
            $xml .= '
            <CommonPrefixes>
                <Prefix>'.$path.'</Prefix>
            </CommonPrefixes>';
        }
        $xml .= '
        </ListBucketResult>';

        return response(trim($xml))
            ->header('Content-Type','application/xml')
            ->header('Content-Length',strlen(trim($xml)));
    }



    private function headObject(Request $request, string $key)
    {

        $file = (new File)->getOriginalFromVirtualpath($key)->first();

        if ( ! $file) {
            abort(404);
        }

        return response('')
            ->header('x-amz-request-id',$request->header('aws-sdk-invocation-id'))
            ->header('ETag',md5($file->Original.$file->CreationDate.$file->Size))
            ->header('Date', Carbon::now())
            ->header('Last-Modified', $file->CreationDate)
            ->header('Content-Length', $file->Size)
            ->header('Content-Type', $file->MimeType)
            ->header('Connection','Close')
            ->header('Server', 'BlikkS3');

        // x-amz-id-2: ef8yU9AS1ed4OpIszj7UDNEHGran
        // x-amz-request-id: 318BC8BC143432E5
        // x-amz-version-id: 3HL4kqtJlcpXroDTDmjVBH40Nrjfkd
        // Date: Wed, 28 Oct 2009 22:32:00 GMT
        // Last-Modified: Sun, 1 Jan 2006 12:00:00 GMT
        // ETag: "fba9dede5f27731c9771645a39863328"
        // Content-Length: 434234
        // Content-Type: text/plain
        // Connection: close
        // Server: AmazonS3
    }

/**
 *
 *
 * [2024-01-30 14:28:35] local.INFO: string(32) "somestrq%C3%A4ngekey/exampel.gif"
string(6) "DELETE"
array(10) {
  ["user-agent"]=>
  array(1) {
    [0]=>
    string(74) "aws-sdk-php/3.297.1 OS/Linux#5.10.25-linuxkit lang/php#8.2.15 GuzzleHttp/7"
  }
  ["authorization"]=>
  array(1) {
    [0]=>
    string(231) "AWS4-HMAC-SHA256 Credential=AKIAZI2LEZPTY6KP7FTS/20240130/eu-central-1/s3/aws4_request, SignedHeaders=host;x-amz-content-sha256;x-amz-date;x-amz-user-agent, Signature=3583c1569e7bf999482459e4e88cd684c9436b21647cb73e7f46dd46f88a3771"
  }
  ["x-amz-date"]=>
  array(1) {
    [0]=>
    string(16) "20240130T142834Z"
  }
  ["x-amz-content-sha256"]=>
  array(1) {
    [0]=>
    string(64) "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
  }
  ["aws-sdk-retry"]=>
  array(1) {
    [0]=>
    string(3) "0/0"
  }
  ["aws-sdk-invocation-id"]=>
  array(1) {
    [0]=>
    string(32) "4858a3486bb69e1d9b0445101d3c2b8c"
  }
  ["x-amz-user-agent"]=>
  array(1) {
    [0]=>
    string(61) "aws-sdk-php/3.297.1 OS/Linux#5.10.25-linuxkit lang/php#8.2.15"
  }
  ["host"]=>
  array(1) {
    [0]=>
    string(16) "files.blikk.test"
  }
  ["content-length"]=>
  array(1) {
    [0]=>
    string(0) ""
  }
  ["content-type"]=>
  array(1) {
    [0]=>
    string(0) ""
  }
}




 * [2024-01-29 11:10:30] local.INFO: string(3) "GET"
array(10) {
  ["user-agent"]=>
  array(1) {
    [0]=>
    string(74) "aws-sdk-php/3.296.0 OS/Linux#5.10.25-linuxkit lang/php#8.2.15 GuzzleHttp/7"
  }
  ["authorization"]=>
  array(1) {
    [0]=>
    string(231) "AWS4-HMAC-SHA256 Credential=AKIAZI2LEZPTY6KP7FTS/20240129/eu-central-1/s3/aws4_request, SignedHeaders=host;x-amz-content-sha256;x-amz-date;x-amz-user-agent, Signature=60b51576179acc1d5e08a1822d1b137b040d666ee97fbb267add1bcc249bd36c"
  }
  ["x-amz-date"]=>
  array(1) {
    [0]=>
    string(16) "20240129T111029Z"
  }
  ["x-amz-content-sha256"]=>
  array(1) {
    [0]=>
    string(64) "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
  }
  ["aws-sdk-retry"]=>
  array(1) {
    [0]=>
    string(3) "0/0"
  }
  ["aws-sdk-invocation-id"]=>
  array(1) {
    [0]=>
    string(32) "7d234fa5602292852005b3fe6a514d69"
  }
  ["x-amz-user-agent"]=>
  array(1) {
    [0]=>
    string(61) "aws-sdk-php/3.296.0 OS/Linux#5.10.25-linuxkit lang/php#8.2.15"
  }
  ["host"]=>
  array(1) {
    [0]=>
    string(16) "files.blikk.test"
  }
  ["content-length"]=>
  array(1) {
    [0]=>
    string(0) ""
  }
  ["content-type"]=>
  array(1) {
    [0]=>
    string(0) ""
  }
}
array(3) {
  ["list-type"]=>
  string(1) "2"
  ["prefix"]=>
  string(37) "a336859b-19b1-43bf-bef6-73493123fd43/"
  ["delimiter"]=>
  string(1) "/"
}
string(0) ""


  [2024-01-24 10:55:10] local.INFO: array(13) {
  ["key"]=>
  string(70) "76613ae4-e3f5-4121-9d21-e74590c9b7b6/220px-Sir_Winston_S_Churchill.jpg"
  ["Content-Type"]=>
  string(10) "image/jpeg"
  ["success_action_status"]=>
  string(3) "200"
  ["acl"]=>
  string(7) "private"
  ["x-amz-meta-unique_folder_name"]=>
  string(36) "76613ae4-e3f5-4121-9d21-e74590c9b7b6"
  ["x-amz-meta-media_to_replace_id"]=>
  string(4) "null"
  ["x-amz-meta-qqfilename"]=>
  string(33) "220px-Sir_Winston_S_Churchill.jpg"
  ["x-amz-algorithm"]=>
  string(16) "AWS4-HMAC-SHA256"
  ["x-amz-credential"]=>
  string(58) "AKIAZI2LEZPTY6KP7FTS/20240124/eu-central-1/s3/aws4_request"
  ["x-amz-date"]=>
  string(16) "20240124T105505Z"
  ["policy"]=>
  string(768) "eyJleHBpcmF0aW9uIjoiMjAyNC0wMS0yNFQxMTowMDowNS40OThaIiwiY29uZGl0aW9ucyI6W3siYWNsIjoicHJpdmF0ZSJ9LHsiYnVja2V0IjoiZmlsZXMifSx7IkNvbnRlbnQtVHlwZSI6ImltYWdlXC9qcGVnIn0seyJzdWNjZXNzX2FjdGlvbl9zdGF0dXMiOiIyMDAifSx7IngtYW16LWFsZ29yaXRobSI6IkFXUzQtSE1BQy1TSEEyNTYifSx7ImtleSI6Ijc2NjEzYWU0LWUzZjUtNDEyMS05ZDIxLWU3NDU5MGM5YjdiNlwvMjIwcHgtU2lyX1dpbnN0b25fU19DaHVyY2hpbGwuanBnIn0seyJ4LWFtei1jcmVkZW50aWFsIjoiQUtJQVpJMkxFWlBUWTZLUDdGVFNcLzIwMjQwMTI0XC9ldS1jZW50cmFsLTFcL3MzXC9hd3M0X3JlcXVlc3QifSx7IngtYW16LWRhdGUiOiIyMDI0MDEyNFQxMDU1MDVaIn0seyJ4LWFtei1tZXRhLXVuaXF1ZV9mb2xkZXJfbmFtZSI6Ijc2NjEzYWU0LWUzZjUtNDEyMS05ZDIxLWU3NDU5MGM5YjdiNiJ9LHsieC1hbXotbWV0YS1tZWRpYV90b19yZXBsYWNlX2lkIjoibnVsbCJ9LHsieC1hbXotbWV0YS1xcWZpbGVuYW1lIjoiMjIwcHgtU2lyX1dpbnN0b25fU19DaHVyY2hpbGwuanBnIn1dfQ=="
  ["x-amz-signature"]=>
  string(64) "344899e602846bc5eb6a42646e5786cab8c48a9b7871e9a038361be95df221c4"
  ["file"]=>
  object(Illuminate\Http\UploadedFile)#308 (7) {
    ["test":"Symfony\Component\HttpFoundation\File\UploadedFile":private]=>
    bool(false)
    ["originalName":"Symfony\Component\HttpFoundation\File\UploadedFile":private]=>
    string(33) "220px-Sir_Winston_S_Churchill.jpg"
    ["mimeType":"Symfony\Component\HttpFoundation\File\UploadedFile":private]=>
    string(10) "image/jpeg"
    ["error":"Symfony\Component\HttpFoundation\File\UploadedFile":private]=>
    int(0)
    ["hashName":protected]=>
    NULL
    ["pathName":"SplFileInfo":private]=>
    string(14) "/tmp/phpIBFckn"
    ["fileName":"SplFileInfo":private]=>
    string(9) "phpIBFckn"
  }
}
*/

/**
 * 
 * 
 * 
 * 
 * [2024-06-11 14:04:17] local.INFO: array (
  'DeleteMarker' => false,
  'AcceptRanges' => 'bytes',
  'Expiration' => '',
  'Restore' => '',
  'ArchiveStatus' => '',
  'LastModified' => 
  \Aws\Api\DateTimeResult::__set_state(array(
     'date' => '2024-06-11 14:04:13.000000',
     'timezone_type' => 2,
     'timezone' => 'GMT',
  )),
  'ContentLength' => 657734,
  'ChecksumCRC32' => '',
  'ChecksumCRC32C' => '',
  'ChecksumSHA1' => '',
  'ChecksumSHA256' => '',
  'ETag' => '"ff88e314bd9a9710da119099ea5ea832"',
  'MissingMeta' => '',
  'VersionId' => '',
  'CacheControl' => '',
  'ContentDisposition' => '',
  'ContentEncoding' => '',
  'ContentLanguage' => '',
  'ContentType' => 'application/pdf',
  'Expires' => 
  \Aws\Api\DateTimeResult::__set_state(array(
     'date' => '1970-01-01 00:00:00.000000',
     'timezone_type' => 3,
     'timezone' => 'UTC',
  )),
  'WebsiteRedirectLocation' => '',
  'ServerSideEncryption' => 'AES256',
  'Metadata' => 
  array (
    'media_to_replace_id' => 'null',
    'qqfilename' => 'Poster_Forging%20New%20Paths_8th%20Conference%20for%20English%20Teachers_2023.pdf',
    'unique_folder_name' => '91cc0609-8d05-4776-b3c7-3bd495b9db3e',
  ),
  'SSECustomerAlgorithm' => '',
  'SSECustomerKeyMD5' => '',
  'SSEKMSKeyId' => '',
  'BucketKeyEnabled' => false,
  'StorageClass' => '',
  'RequestCharged' => '',
  'ReplicationStatus' => '',
  'PartsCount' => '',
  'ObjectLockMode' => '',
  'ObjectLockRetainUntilDate' => 
  \Aws\Api\DateTimeResult::__set_state(array(
     'date' => '1970-01-01 00:00:00.000000',
     'timezone_type' => 3,
     'timezone' => 'UTC',
  )),
  'ObjectLockLegalHoldStatus' => '',
  '@metadata' => 
  array (
    'statusCode' => 200,
    'effectiveUri' => 'https://blikk-test-bucket.s3.eu-central-1.amazonaws.com/91cc0609-8d05-4776-b3c7-3bd495b9db3e/Poster_ForgingNewPaths_8thConferenceforEnglishTeachers_2023.pdf',
    'headers' => 
    array (
      'x-amz-id-2' => 'uswzh1NQGqH4KjGW29JlNrNcSrJ5QJeEk3oGyryR2uoRZwdbcHiU1OHYjQGXTDypnuAPTlkOIDs=',
      'x-amz-request-id' => 'NDCCCT2BH0FC8QG9',
      'date' => 'Tue, 11 Jun 2024 14:04:18 GMT',
      'last-modified' => 'Tue, 11 Jun 2024 14:04:13 GMT',
      'etag' => '"ff88e314bd9a9710da119099ea5ea832"',
      'x-amz-server-side-encryption' => 'AES256',
      'x-amz-meta-media_to_replace_id' => 'null',
      'x-amz-meta-qqfilename' => 'Poster_Forging%20New%20Paths_8th%20Conference%20for%20English%20Teachers_2023.pdf',
      'x-amz-meta-unique_folder_name' => '91cc0609-8d05-4776-b3c7-3bd495b9db3e',
      'accept-ranges' => 'bytes',
      'content-type' => 'application/pdf',
      'server' => 'AmazonS3',
      'content-length' => '657734',
    ),
    'transferStats' => 
    array (
      'http' => 
      array (
        0 => 
        array (
        ),
      ),
    ),
  ),
)  

 * 
 * 
 * 
 */


}
