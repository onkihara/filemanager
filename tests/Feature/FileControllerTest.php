<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\InitFileTests;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class FileControllerTest extends TestCase
{
    use InitFileTests;


    public function test_refresh_media()
    {
        Storage::fake('testfiles');
        $file = UploadedFile::fake()->image('testimage.jpg');

        $response = $this->json('POST', $this->url.'/request/upload', [
            'file' => $file
        ]);
        //$response->dump();die();
        $data = $response->json();

        $response = $this->json('GET', $this->url.'/request/filedata/'.$data['id']);
        //$response->dump();die();
        $data = $response->json();

        // assert result
        $this->assertEquals(1, $data['State']);
    }


    public function test_audio_upload()
    {
        $scope = 'groups.153.profile';
        $path = storage_path('app/sleep.wav');
        $uploadedfile = new UploadedFile($path, 'sleep.wav', 'video/mp4', null, true);

        $response = $this->json('POST', $this->url.'/request/upload', [
            'file' => $uploadedfile,
            'scope' => $scope
        ]);
        //$response->dump();die();
        $data = $response->json();
        $file = (new \App\Models\File)->find($data['id']);

        // assert conversion result
        $this->assertEquals(config('filemanager.conversions.audio.extension'), pathinfo($file->Filename, PATHINFO_EXTENSION));
        $this->assertEquals(config('filemanager.conversions.audio.mime_type'), $file->MimeType);
        $this->assertEquals(config('filemanager.conversions.audio.extension'), $file->Extension);

        // assert meta data
        $this->assertArrayHasKey('filesize', json_decode($file->Meta, true));

        // delete file
        $response = $this->json('DELETE', $this->url.'/request/delete/'.$data['id'].'?ays=1');
        $response->assertOk();

    }



    public function test_video_upload()
    {
        $scope = 'groups.153.profile';
        $path = storage_path('app/wildlife.mp4');
        $uploadedfile = new UploadedFile($path, 'wildlife.mp4', 'video/mp4', null, true);

        $response = $this->json('POST', $this->url.'/request/upload', [
            'file' => $uploadedfile,
            'scope' => $scope
        ]);
        //$response->dump();die();
        $data = $response->json();
        $file = (new \App\Models\File)->find($data['id']);

        // assert conversion result
        $this->assertEquals(config('filemanager.conversions.video.extension'), pathinfo($file->Filename, PATHINFO_EXTENSION));
        $this->assertEquals(config('filemanager.conversions.video.mime_type'), $file->MimeType);
        $this->assertEquals(config('filemanager.conversions.video.extension'), $file->Extension);

        // assert meta data
        $this->assertArrayHasKey('width', json_decode($file->Meta, true));
        $this->assertArrayHasKey('height', json_decode($file->Meta, true));

        // delete file
        $response = $this->json('DELETE', $this->url.'/request/delete/'.$data['id'].'?ays=1');
        $response->assertOk();
    }




    public function test_scope_filter_for_index()
    {
        $this->reinitDatabase();

        $scope = 'groups.153.profile';

        // with scope set
        $response = $this->get($this->url.'/files?scope='.$scope);
        $response->assertOk();
        $response->assertSee('640px-Elvas_(Brixen).JPG');
        $response->assertDontSee('lineman.jpg');

        // without scope set
        $response = $this->get($this->url.'/files');
        $response->assertOk();
        $response->assertSee('640px-Elvas_(Brixen).JPG');
        $response->assertSee('lineman.jpg');

    }


    public function test_delete_scope_set_via_index()
    {
        $scope = 'groups.153.profile';

        // set scope via index
        $response = $this->get($this->url.'/files?scope='.$scope);
        $response->assertOk();
        $response->assertSessionHas('scope');
        
        // set target via index
        $response = $this->get($this->url.'/files?target='.$scope);
        $response->assertOk();
        $response->assertSessionHas('target');
        $response->assertSessionMissing('scope');

    }


    public function test_upload_with_scope_set_via_index()
    {
        $scope = 'groups.153.profile';

        // set scope via index
        $response = $this->get($this->url.'/files?scope='.$scope);
        $response->assertOk();

        Storage::fake('testfiles');
        $file = UploadedFile::fake()->image('testimage.jpg');

        $response = $this->json('POST', $this->url.'/request/upload', [
            'file' => $file
        ]);
        //$response->dump();die();
        $data = $response->json();
        $filedata = (new \App\Models\File)->find($data['id']);
        $this->assertEquals($scope,$filedata->Scope);

        // delete file
        $response = $this->json('DELETE', $this->url.'/request/delete/'.$data['id'].'?ays=1');
        $response->assertOk();
    }


    public function test_upload_with_scope()
    {
        $scope = 'groups.153.profile';
        Storage::fake('testfiles');
        $file = UploadedFile::fake()->image('testimage.jpg');

        $response = $this->json('POST', $this->url.'/request/upload', [
            'file' => $file,
            'scope' => $scope
        ]);
        //$response->dump();die();
        $data = $response->json();
        $filedata = (new \App\Models\File)->find($data['id']);
        $this->assertEquals($scope,$filedata->Scope);

        // delete file
        $response = $this->json('DELETE', $this->url.'/request/delete/'.$data['id'].'?ays=1');
        $response->assertOk();

    }


    public function test_fileinstance_filter_active()
    {
        $this->reinitDatabase(); 
        // set an instance for test.scope
        $scope = 'groups.153.profile';

        // index-page with scope should return to items
        $response = $this->get($this->url.'/files?scope='.$scope);
        $response->assertSee('640px-Elvas_(Brixen).JPG');
        $response->assertDontSee('1617204371_606494931c57e.jpg');
        $response->assertStatus(200);
    }

   
    public function test_reaching_index_file()
    {
        $this->reinitDatabase(); 

        // index-page for auth-user
        $response = $this->get($this->url.'/files');
        $response->assertStatus(200);

        // index-page for non-auth-asuer
        $this->logout();
        $response = $this->get($this->url.'/files');
        $response->assertStatus(302);
    }

   
    public function test_setting_session_params()
    {
        // index-page with allowed session vars or null
        $this->login();
        $data = ['target' => 'somescope', 'width' => '300', 'notallowed' => 1];
        $urlwithquery = $this->url.'/files?'.http_build_query($data);
        $response = $this->get($urlwithquery);
        $response->assertStatus(200);
        $response->assertSessionHas('width', '300');
        $response->assertSessionHas('target', 'somescope');
        $response->assertSessionMissing('notallowed');
    }
}
