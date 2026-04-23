<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\InitFileTests;
use Tests\AWSCredentials;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\File;

class S3ControllerTest extends TestCase
{

    use InitFileTests, AWSCredentials;


    public function test_s3_authentication_with_V4_Signature()
    {
        $query = http_build_query(['list-type' => 2, 'delimiter' => '/']);
        $response = $this->withHeaders([
               "authorization" => "AWS4-HMAC-SHA256 Credential=AKIAZI2LEZPTY6KP7FTS/20240131/eu-central-1/s3/aws4_request, SignedHeaders=host;x-amz-content-sha256;x-amz-date;x-amz-user-agent, Signature=e4089b47f1cc4b226bdaf96d4d4b3cb13704df1cd7b71031e26ca9a9e37d95b9",
               "x-amz-date" => "20240131T153041Z",
               "x-amz-content-sha256" => "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
               "x-amz-user-agent" => "aws-sdk-php/3.297.1 OS/Linux#5.10.25-linuxkit lang/php#8.2.15",
               "host" => "files.blikk.test"
            ])->get($this->url.'/api-s3-v1?'.$query);
        $response->assertStatus(200);

    }



    // should create a db entry and the file entries
    // needs authentication
    public function test_s3_route_upload()
    {
        Storage::fake('files');
        $path = urlencode('test1/test2/test3/Süße Kartoffeln.png');
        $file = UploadedFile::fake()->image('Süße Kartoffeln.png');

        $response = $this->post($this->url.'/api-s3-v1',
            array_merge($this->awscredentials,[
            'file' => $file,
            'key' => $path,
        ]));

        $response->assertStatus(200);
        $resp = json_decode($response->getContent());
        $file = (new File)->getOriginalFromVirtualpath($resp->Key)->first();
        Storage::disk('files')->assertExists((new File)->fpath.$file->Filename);
        $this->assertEquals($resp->Key,$file->Virtualpath.$file->Original);
    }


    // should return status 204
    public function test_s3_deleteObject()
    {
        Storage::fake('files');
        $this->uploadFakeImage('abc/def/ghi.jpg');
        $key = 'abc/def/ghi.jpg';
        $path = 'api-s3-v1';
        $headers = $this->createSignedHeaders('DELETE',$path.'/'.$key);

        $response = $this->withHeaders($headers)->delete($this->url.'/'.$path.'/'.$key);
        $response->assertStatus(204);
        $files = (new File)->getOriginalFromVirtualpath($key);
        $this->assertEmpty($files);
    }


   // should return appropriate XML
    public function test_s3_listObjectsV2()
    {
        $this->uploadFakeImage('abc/def/ghi.jpg');
        $this->uploadFakeImage('jkl.jpg');
        $this->uploadFakeImage('abc/mno.jpg');
        $this->uploadFakeImage('abc/rst/def/xyz.jpg');

        // prefix = '', delimiter = '' => should get jkl.jpg (among others) but not ghi.jpg and mno.jpg
        // CommonPrefixes = null
        $query = ['list-type' => 2];
        $path = 'api-s3-v1';
        $headers = $this->createSignedHeaders('GET',$path,$query);
        $response = $this->withHeaders($headers)->get($this->url.'/'.$path.'?'.http_build_query($query));
        $contents = $this->getXmlContents($response);
        $commonprefixes = $this->getXmlCommonPrefixes($response);
        $this->assertEmpty($commonprefixes);
        $this->assertContains('jkl.jpg',$contents->pluck('Key'));
        $this->assertNotContains('ghi.jpg',$contents->pluck('Key'));
        $this->assertNotContains('mno.jpg',$contents->pluck('Key'));

        // prefix = '', delimiter = / => jkl.jpg (! ghi.jpg, ! mno.jpg),
        // CommonPrefixes = abc/, virt1/

        $query = ['list-type' => 2,'delimiter' => '/'];
        $path = 'api-s3-v1';
        $headers = $this->createSignedHeaders('GET',$path,$query);
        $response = $this->withHeaders($headers)->get($this->url.'/'.$path.'?'.http_build_query($query));
        $contents = $this->getXmlContents($response);
        $commonprefixes = $this->getXmlCommonPrefixes($response);
        $this->assertContains('abc/',$commonprefixes->pluck('Prefix'));
        $this->assertContains('virt1/',$commonprefixes->pluck('Prefix'));
        $this->assertNotContains('abc/def/',$commonprefixes->pluck('Prefix'));
        $this->assertNotContains('virt1/virt2/',$commonprefixes->pluck('Prefix'));
        $this->assertContains('jkl.jpg',$contents->pluck('Key'));
        $this->assertNotContains('ghi.jpg',$contents->pluck('Key'));
        $this->assertNotContains('mno.jpg',$contents->pluck('Key'));

        // prefix = 'abc/', delimiter = '' => mno.jpg only
        // CommonPrefixes = null
        $query = ['list-type' => 2,'prefix' => 'abc/'];
        $path = 'api-s3-v1';
        $headers = $this->createSignedHeaders('GET',$path,$query);
        $response = $this->withHeaders($headers)->get($this->url.'/'.$path.'?'.http_build_query($query));
        $contents = $this->getXmlContents($response);
        $commonprefixes = $this->getXmlCommonPrefixes($response);
        $this->assertEmpty($commonprefixes);
        $this->assertContains('mno.jpg',$contents->pluck('Key'));
        $this->assertNotContains('jkl.jpg',$contents->pluck('Key'));
        $this->assertNotContains('ghi.jpg',$contents->pluck('Key'));

        // prefix = 'abc/', delimiter = '/' => mno.jpg only
        // CommonPrefixes = abc/def/
        $query = ['list-type' => 2,'prefix' => 'abc/','delimiter' => '/'];
        $path = 'api-s3-v1';
        $headers = $this->createSignedHeaders('GET',$path,$query);
        $response = $this->withHeaders($headers)->get($this->url.'/'.$path.'?'.http_build_query($query));
        $contents = $this->getXmlContents($response);
        $commonprefixes = $this->getXmlCommonPrefixes($response);
        $this->assertContains('abc/def/',$commonprefixes->pluck('Prefix'));
        $this->assertContains('mno.jpg',$contents->pluck('Key'));
        $this->assertNotContains('jkl.jpg',$contents->pluck('Key'));
        $this->assertNotContains('ghi.jpg',$contents->pluck('Key'));

        // prefix = 'abc/rst/', delimiter = '/' => no Key
        // CommonPrefixes = abc/rst/def/
        $query = ['list-type' => 2,'prefix' => 'abc/rst/','delimiter' => '/'];
        $path = 'api-s3-v1';
        $headers = $this->createSignedHeaders('GET',$path,$query);
        $response = $this->withHeaders($headers)->get($this->url.'/'.$path.'?'.http_build_query($query));
        $contents = $this->getXmlContents($response);
        $commonprefixes = $this->getXmlCommonPrefixes($response);
        $this->assertContains('abc/rst/def/',$commonprefixes->pluck('Prefix'));
        $this->assertNotContains('mno.jpg',$contents->pluck('Key'));
        $this->assertNotContains('jkl.jpg',$contents->pluck('Key'));
        $this->assertNotContains('ghi.jpg',$contents->pluck('Key'));
        $this->assertNotContains('xyz.jpg',$contents->pluck('Key'));

        // prefix = 'jk', delimiter = '/' => jkl.jpg
        // CommonPrefixes = abc/rst/def/
        $query = ['list-type' => 2,'prefix' => 'jk','delimiter' => '/'];
        $path = 'api-s3-v1';
        $headers = $this->createSignedHeaders('GET',$path,$query);
        $response = $this->withHeaders($headers)->get($this->url.'/'.$path.'?'.http_build_query($query));
        $contents = $this->getXmlContents($response);
        $commonprefixes = $this->getXmlCommonPrefixes($response);
        $this->assertContains('abc/',$commonprefixes->pluck('Prefix'));
        $this->assertNotContains('abc/def/',$commonprefixes->pluck('Prefix'));
        $this->assertContains('jkl.jpg',$contents->pluck('Key'));
        $this->assertNotContains('mno.jpg',$contents->pluck('Key'));
        $this->assertNotContains('ghi.jpg',$contents->pluck('Key'));
        $this->assertNotContains('xyz.jpg',$contents->pluck('Key'));
    }




    private function uploadFakeImage($path)
    {
        Storage::fake('files');
        //s$path = urlencode($path;
        $file = UploadedFile::fake()->image(basename($path));
        $this->post($this->url.'/api-s3-v1',
            array_merge($this->awscredentials,[
            'file' => $file,
            'key' => $path,
        ]));
        return $file;
    }


    private function getXmlContents($response)
    {
        $xml = simplexml_load_string($response->getContent());
        $json = json_encode($xml);
        $array = json_decode($json,TRUE);
        $result = collect();
        if ( ! isset($array['Contents']) || ! is_array($array['Contents'])) {
            return $result;
        }
        if (isset($array['Contents'][0])) {
            foreach ($array['Contents'] as $content) {
                $result->push($content);
            }
        } else {
            $result->push($array['Contents']);
        }
        return $result;
    }


    private function getXmlCommonPrefixes($response)
    {
        $xml = simplexml_load_string($response->getContent());
        $json = json_encode($xml);
        $array = json_decode($json,TRUE);
        $result = collect();
        if ( ! isset($array['CommonPrefixes']) || ! is_array($array['CommonPrefixes'])) {
            return $result;
        }
        if (isset($array['CommonPrefixes'][0])) {
            foreach ($array['CommonPrefixes'] as $content) {
                $result->push($content);
            }
        } else {
            $result->push($array['CommonPrefixes']);
        }
        return $result;
    }



}
