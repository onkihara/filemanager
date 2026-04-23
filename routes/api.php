<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\Jwt;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group([
    'prefix' => 'v1',
    'middleware' => Jwt::class,
    'namespace' => '\App\Http\Controllers'
],function() {

    /**
     * Fileinstance-Routes
     */

    /**
     * Getting the instances of a file
     * Json-Request: file: FileID
     * json-Response: Fileinstance-Collection, 200, 404, 422
     */
    Route::get('/fileinstances/{file}','FilesController@getFileinstances');

    /**
     * putting the instances of a file
     * Json-Request: Data to put
     * json-Response: Fileinstance-Object, 201, 422
     */
    Route::put('/fileinstance','FilesController@putFileinstance');

    /**
     * deleting the instances of a file by id
     * Json-Request: Fileinstance-id
     * json-Response: ['success' => true, 'message' => 'deleted'] 200, 404, 422
     */
    Route::delete('/fileinstance/{fileinstance}','FilesController@deleteFileinstance');

    /**
     * deleting the instances of a file by params
     * Json-Request: Params to indentify fileinstances to delete (be carefull!)
     * json-Response: ['success' => true, 'message' => 'deleted', 'count' => numberdeletedentries] 200, 422
     */
    Route::delete('/fileinstance','FilesController@deleteFileinstanceByParams');



    /**
     * Avatar-Routes
     * all jwt-tokens must contain the same userid as in parameters
     */

    /**
     * get the avatar 
     * GET Request: ['userid' => userid, 'workspace' => 154, type => avatar|team ]
     * json-Response: ['result' => 'success', 'file' => url to background file, 'av' => [avdata]] 200
     *                ['result' => 'error', 'message' => error message] 415
     */
    Route::get('/avatar', 'AvatarController@getAvatarData');

    /**
     * create the avatar and store in profiles
     * PUT Request: ['userid' => userid, 'workspace' => 154, 'avdata' => json_encode(av)]
     * json-Response: ['result' => 'success', 'avatarpath' => path to profile, 'avatarid' => id of vcard] 200
     *                ['result' => 'error', 'message' => error message] 415
     */
    Route::put('/avatar/create', 'AvatarController@create');

    /**
     * delete the avatardata and -image
     * DELETE Request: ['avatarid' => id, 'userid' => userid]
     * json-Response: ['result' => 'success'] 200
     *                ['result' => 'error', 'message' => error message] 415
     */
    Route::delete('/avatar/delete', 'AvatarController@deleteAvatar');

    /**
     * store the avatar background image in files
     * POST Request: ['file' => filedata, 'scope' => application.instance.background, 'userid' => userid, 'username' => username]
     * json-Response: ['result' => 'success', 'url' => path to file, 'id' => FileId] 200, 415
     *            or: ['result' => 'error', 'message' => error message] 200
     */
    Route::post('/avatar/upload', 'AvatarController@upload');

    /**
     * delete a background image for avatars
     * DELETE Request: ['id' => ID, 'userid' => userid]
     * json-Response: ['result' => 'success', 'id' => FileId] 200, 415, 422
     *            or: ['result' => 'error', 'message' => error message] 200
     */
    Route::delete('/avatar/upload', 'AvatarController@delete');

    

});
