<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\InitFileTests;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class B3ControllerTest extends TestCase
{

    use InitFileTests;


    public function test_b3_route_directory_exists()
    {
        // test with valid path
        $direxists = 'virt1/virt2/';
        $params = http_build_query(['path' => urlencode($direxists)]);
        $response = $this->get($this->url.'/api/b3/v1/isdir'.'?'.$params);
        $response->assertStatus(200);
        $result = json_decode($response->getContent(),true);
        $this->assertTrue($result['success']);

       // test with not path
        $direxists = 'virt1/notvalid/';
        $params = http_build_query(['path' => urlencode($direxists)]);
        $response = $this->get($this->url.'/api/b3/v1/isdir'.'?'.$params);
        $response->assertStatus(200);
        $result = json_decode($response->getContent(),true);
        $this->assertFalse($result['success']);

        // test with incomplete path (missing trailing slash)
        $direxists = 'virt1/virt2';
        $params = http_build_query(['path' => urlencode($direxists)]);
        $response = $this->get($this->url.'/api/b3/v1/isdir'.'?'.$params);
        $response->assertStatus(200);
        $result = json_decode($response->getContent(),true);
        $this->assertTrue($result['success']);


    }


    public function test_b3_route_file_exists()
    {
        // test with valid path
        $pathexists = 'virt1/virt2/Patrick Süßkind.jpg';
        $params = http_build_query(['path' => urlencode($pathexists)]);
        $response = $this->get($this->url.'/api/b3/v1/exists'.'?'.$params);
        $response->assertStatus(200);
        $result = json_decode($response->getContent(),true);
        $this->assertTrue($result['success']);

        // test with not valid path
        $pathexists = 'nonvalidpath/Patrick Süßkind.jpg';
        $params = http_build_query(['path' => urlencode($pathexists)]);
        $response = $this->get($this->url.'/api/b3/v1/exists'.'?'.$params);
        $response->assertStatus(200);
        $result = json_decode($response->getContent(),true);
        $this->assertFalse($result['success']);
    }


    public function test_b3_route_retreive_file()
    {
        // create test file in storage
        $file = Storage::disk('files')->path('raws/2021/03/1617204369_6064949155f13.jpg');
        if (!file_exists($file)) {
            if (!is_dir(dirname($file))) {
                mkdir(dirname($file), 0755, true); 
            }
            copy(base_path('tests/files/1617204369_6064949155f13.jpg'), $file);
        }     
   
        // test with valid path should get File
        $pathexists = 'virt1/virt2/Patrick Süßkind.jpg';
        $params = http_build_query(['path' => urlencode($pathexists)]);
        $response = $this->get($this->url.'/api/b3/v1/retreive'.'?'.$params);
        $response->assertStatus(200);
        $this->assertEquals('image/jpeg',$response->headers->get('content-type'));

        // delete test file
        if (file_exists($file)) {
            unlink($file);  
        }
    }


    public function test_b3_route_download_urls()
    {
        // test with no result should get empty files
        $pathdoesnotexist = 'virt1/doesnotexist/Patrick Süßkind.jpg';
        $params = http_build_query(['path' => urlencode($pathdoesnotexist)]);
        $response = $this->get($this->url.'/api/b3/v1/download-urls'.'?'.$params);
        $result = json_decode($response->getContent(),true);
        $response->assertStatus(200);
        $this->assertEquals([],$result['files']);

        // test with valid path should get array of download-urls
        $pathexists = 'virt1/virt2/Patrick Süßkind.jpg';
        $params = http_build_query(['path' => urlencode($pathexists)]);
        $response = $this->get($this->url.'/api/b3/v1/download-urls'.'?'.$params);
        $result = json_decode($response->getContent(),true);
        $response->assertStatus(200);
        $this->assertEquals('http://files.blikk.test/download/2',$result['files'][0]);
    }


    // should create a db entry and the file entries
    // needs authentication
    public function test_b3_route_upload()
    {
        Storage::fake('files');
        $path = urlencode('test1/test2/test3/Süße Kartoffeln.png');
        $file = UploadedFile::fake()->image('Süße Kartoffeln.png');

        $response = $this->post($this->url.'/api/b3/v1/upload', [
            'file' => $file,
            'path' => $path
        ]);

        $response->assertStatus(200);
        $resp = json_decode($response->getContent());
        $this->assertTrue($resp->success);
        Storage::disk('files')->assertExists($resp->path);
        $this->assertEquals(route('image',['id' => $resp->id]),$resp->url);
        $file_id = $resp->id;

        // test replace file (default): there should be only one file
        $response = $this->post($this->url.'/api/b3/v1/upload', [
            'file' => $file,
            'path' => $path
        ]);
        $response->assertStatus(200);
        $resp = json_decode($response->getContent());
        // file-ids should match
        $this->assertEquals($file_id,$resp->id);

       // test do not replace: there should be another file
        $response = $this->post($this->url.'/api/b3/v1/upload', [
            'file' => $file,
            'path' => $path,
            'replace' => false
        ]);
        $response->assertStatus(200);
        $resp = json_decode($response->getContent());
        // file-ids shouldn't match
        $this->assertNotEquals($file_id,$resp->id);


    }
}
