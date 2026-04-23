<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\InitFileTests;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class FileApiFileinstanceTest extends TestCase
{
    use InitFileTests;

    public function test_deleting_a_fileinstance_by_params()
    {
        // creating
        $response = $this->putJson($this->url.'/api/v1/fileinstance',[
            'UserID' => 3, 'FileID' => 2, 'Scope' => 'groups.153.groupprofile', 'Target'=> 'test_deleting_a_fileinstance_by_params','Link' => 'http://www.blikk.it'
        ]);
        //$response->dump();
        $response->assertStatus(201); // created
        // deleting with no params
        $response = $this->deleteJson($this->url.'/api/v1/fileinstance',[]);
        //$response->dump();
        $response->assertStatus(422); // no params
        // deleting with right params
        $response = $this->deleteJson($this->url.'/api/v1/fileinstance',[
            'Scope' => 'groups.153.groupprofile',
            'Target' => 'test_deleting_a_fileinstance_by_params'
        ]);
        $response->assertStatus(200); // deleted
        $response->assertJson(['success' => true, 'message' => 'deleted', 'count' => 1]);
    }

    public function test_deleting_a_fileinstance_by_id()
    {
        // creating
        $response = $this->putJson($this->url.'/api/v1/fileinstance',[
            'UserID' => 3, 'FileID' => 2, 'Scope' => 'groups.153.groupprofile', 'Link' => 'http://www.blikk.it'
        ]);
        //$response->dump();
        $fileinstande_id = $response->json()['ID'];
        $response->assertStatus(201); // created
        // deleting wrong fileinstance
        $response = $this->deleteJson($this->url.'/api/v1/fileinstance/1023');
        $response->assertStatus(404); // no model found
        // deleting right fileinstance
        $response = $this->deleteJson($this->url.'/api/v1/fileinstance/'.$fileinstande_id);
        $response->assertStatus(200); // deleted
        $response->assertJson(['success' => true, 'message' => 'deleted']);
    }

    


    /**
     * Fileinstances tagged "Unique=1" should be uniqe regarding Scope and Target
     */
    public function test_putting_a_new_file_instances_with_wrong_or_missing_parameters()
    {
        $file_id = 3;
      
        // Unique
        $response = $this->putJson($this->url.'/api/v1/fileinstance', [
            'UserID' => 3, 'FileID' => $file_id // Scope is missing
        ]);
        //$response->dump();
        $response->assertStatus(422);
    }



    /**
     * Fileinstances tagged "Unique=1" should be uniqe regarding Scope and Target
     */
    public function test_putting_a_new_unique_file_instances()
    {
        $file_id = 3;
      
        // Unique
        $response = $this->putJson($this->url.'/api/v1/fileinstance', [
            'UserID' => 3, 'FileID' => $file_id, 'Scope' => 'groups.153.groupprofile', 'Unique' => 1, 'Target' => 1, 'Link' => 'firstlink'
        ]);
        //$response->dump();
        $fileinstance1_id = $response->json()['ID'];
        $response->assertStatus(201);

        // Put fileinstance with the same values
        $response = $this->putJson($this->url.'/api/v1/fileinstance',[
            'UserID' => 3, 'FileID' => $file_id, 'Scope' => 'groups.153.groupprofile', 'Unique' => 1, 'Target' => 1, 'Link' => 'secondlink'
        ]);
        //$response->dump();
        $fileinstance2_id = $response->json()['ID'];
        $response->assertStatus(201); // created

        // assert same entry
        $this->assertEquals($fileinstance1_id,$fileinstance2_id);

        // assert updated
        $response = $this->getJson($this->url."/api/v1/fileinstances/$file_id");
        // there should be only one entry for $file_id
        $this->assertEquals(1,count($response->json()));
        $response->assertJsonFragment(['Link' => 'secondlink']);
        $response->assertStatus(200);
 
    }

    public function test_putting_a_new_file_instances()
    {
        // with faulty values (Validation error)
        $response = $this->putJson($this->url.'/api/v1/fileinstance', [
            // no coentent
        ]);
        $response->assertStatus(422);

        // wrong format
        $response = $this->putJson($this->url.'/api/v1/fileinstance', [
            'UserID' => 'wrongformat', 'FileID' => 2, 'Scope' => 'groups.153.groupprofile'
        ]);
        //$response->dump();
        $response->assertStatus(422);

        // with correct values
        $response = $this->putJson($this->url.'/api/v1/fileinstance',[
            'UserID' => 3, 'FileID' => 2, 'Scope' => 'groups.153.groupprofile', 'Link' => 'http://www.blikk.it'
        ]);
        //$response->dump();
        $response->assertStatus(201); // created

 
    }

    public function test_getting_file_instances_by_id()
    {
        $response = $this->getJson($this->url.'/api/v1/fileinstances/1');
        $response->assertStatus(200);
    }

}
