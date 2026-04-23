<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\Avatar;
use Tests\TestCase;
use Tests\InitAvatarTests;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class AvatarApiTest extends TestCase
{
    use InitAvatarTests;


    // jwt-token is added in InitAvatarTests as Bearer
    public function test_getting_avatar_with_no_entry()
    {
        $geturl = 'http://files.blikk.test/api/v1/avatar';
        $data = http_build_query([
            'workspace' => 154,
            'userid' => 3,
            'type' => 'avatar'
        ]);
        $response = $this->get($geturl.'?'.$data);

        $response->assertStatus(204);

    }



    // jwt-token is added in InitAvatarTests as Bearer
    public function test_getting_avatar_with_file()
    {
        // create avatar entry in DB
        $avdata = [
            'file' => 1,
            'template' => 2,
            'teint' => 1,
            'color' => 0,
            'face' => 1,
            'top' => 16,
            'left'=> 17,
            'width' => 250,
            'height' => 250
        ];
        Avatar::create([
            'UserID' => 3, 
            'Scope' => 154,
            'Descriptor' => 'avatar', 
            'Content' => json_encode($avdata)
        ]);

        $geturl = 'http://files.blikk.test/api/v1/avatar';
        $data = http_build_query([
            'workspace' => 154,
            'userid' => 3,
            'type' => 'avatar'
        ]);
        $response = $this->get($geturl.'?'.$data);

        $response->assertStatus(200);
        $resp = json_decode($response->getContent(),true);
        $this->assertEquals($avdata,$resp['av']);
    }



    // jwt-token is added in InitAvatarTests as Bearer
    public function test_creating_an_avatar()
    {
        $createurl = 'http://files.blikk.test/api/v1/avatar/create';

        $avdata = json_encode([
            'file' => 0,
            'teint' => 2,
            'template' => 2,
            'color' => 0,
            'face' => 1,
            'top' => 16,
            'left'=> 17,
            'width' => 250,
            'height' => 250
        ]);

        $response = $this->put($createurl, [
            'workspace' => 154,
            'userid' => 3,
            'avdata' => $avdata
        ]);

        $response->assertStatus(200);
        $resp = json_decode($response->getContent(),true);

        // vcard written?
        $vcard = (new Avatar)->where('UserID',3)->get();
        $this->assertEquals(1,$vcard->count());

        // user written?
        $user = \DB::table('users')->find(3);
        $this->assertEquals('/auth/profiles/3.png',$user->UserIcon);

        // file created?
        $this->assertTrue(file_exists(config('avatar.targettestpath.avatar').'3.png'));
    }



    // jwt-token is added in InitAvatarTests as Bearer
    public function test_creating_avatar_with_no_color()
    {
        $createurl = 'http://files.blikk.test/api/v1/avatar/create';

        $avdata = json_encode([
            'file' => 0,
            'teint' => 2,
            'template' => 2,
            'color' => -1,
            'face' => 1,
            'top' => 16,
            'left'=> 17,
            'width' => 250,
            'height' => 250
        ]);

        $response = $this->put($createurl, [
            'workspace' => 154,
            'userid' => 3,
            'avdata' => $avdata
        ]);

        $response->assertStatus(200);
        $resp = json_decode($response->getContent(),true);
    }



    // jwt-token is added in InitAvatarTests as Bearer
    public function test_uploading_and_deleting_avatar_background_image()
    {
        $uploadurl = 'http://files.blikk.test/api/v1/avatar/upload';
        Storage::fake('files');
        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->post($uploadurl, [
            'file' => $file,
            'scope' => 'avataredit.154.background',
            'userid' => 3,
            'username' => 'Tester Test'
        ]);

        $response->assertStatus(200);
        $resp = json_decode($response->getContent());
        Storage::disk('files')->assertExists($resp->filename);
        $this->assertEquals(route('image',['id' => $resp->id]),$resp->url);

        // delete test
        $deleteurl = 'http://files.blikk.test/api/v1/avatar/upload';

        $response = $this->delete($deleteurl, [
            'id' => $resp->id,
            'userid' => 3,
        ]);

        $response->assertStatus(200);
        Storage::disk('files')->assertMissing($resp->filename);

    }



    // jwt-token is added in InitAvatarTests as Bearer
    public function test_creating_avatar_with_missing_or_wrong_parameters()
    {
        $createurl = 'http://files.blikk.test/api/v1/avatar/create';

        $avdata = json_encode([
            'file' => 0,
            'template' => 2,
            'color' => 0,
            'face' => 1,
            'top' => 0,
            'left'=> 0,
            'width' => 0,
            'height' => 0
        ]);

        // wrong userid
        $response = $this->put($createurl, [
            'workspace' => 154,
            'userid' => 4,
            'avdata' => $avdata
        ]);
        $response->assertStatus(415);
        $resp = json_decode($response->getContent(),true);
        $this->assertTrue(array_key_exists('userid', $resp));

        // missing workspace
        $response = $this->put($createurl, [
            'userid' => 3,
            'avdata' => $avdata
        ]);
        $response->assertStatus(415);
        $resp = json_decode($response->getContent(),true);
        $this->assertTrue(array_key_exists('workspace', $resp));

        // missing avdata
        $response = $this->put($createurl, [
            'workspace' => 154,
            'userid' => 3,
        ]);
        $response->assertStatus(415);
        $resp = json_decode($response->getContent(),true);
        $this->assertTrue(array_key_exists('avdata', $resp));
    }






    // jwt-token is added in InitAvatarTests as Bearer
    public function test_uploading_with_wrong_user_id()
    {
        // jwt-tken has uerid 3
        $uploadurl = 'http://files.blikk.test/api/v1/avatar/upload';
        Storage::fake('files');
        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->post($uploadurl, [
            'file' => $file,
            'scope' => 'avataredit.154.background',
            'userid' => 4, // differs from userid in jwt-token
            'username' => 'Tester Test'
        ]);

        $response->assertStatus(415);

    }


    // jwt-token is added in InitAvatarTests as Bearer
    public function test_deleting_with_wrong_user_id()
    {
        // jwt-tken has uerid 3
        $deleteurl = 'http://files.blikk.test/api/v1/avatar/upload';
        Storage::fake('files');

        $response = $this->delete($deleteurl, [
            'id' => 1,
            'userid' => 4, // differs from userid in jwt-token
        ]);

        $response->assertStatus(415);

    }



}
