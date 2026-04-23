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
    'namespace' => '\App\Http\Controllers'
],function() {


    /**
     * Method: GET,
     * Params: ['path' => to match virtualpath and original in files table (url-encoded)
     * Response: JSON ['success' => true | false]
     */
    Route::get('exists','B3Controller@exists');


    /**
     * Method: GET,
     * Params: ['path' => to match virtualpath in files table (url-encoded)
     * Response: JSON ['success' => true | false]
     */
    Route::get('isdir','B3Controller@isdir');

    /**
     * Method: GET,
     * Params: ['path' => to match virtualpath and original in files table (url-encoded)
     * Response: JSON ['files' => Array of download-URL for files]
     */
    Route::get('download-urls','B3Controller@downloadUrls');


    /**
     * Method: GET,
     * Params: ['path' => to match virtualpath and original in files table (url-encoded)
     * Response: File
     */
    Route::get('retreive','B3Controller@retreive');



    // JWT-Authentication required
    Route::group([
        'middleware' => Jwt::class
    ], function() {

       /**
         * Method: POST,
         * Params: ['path' => to match virtualpath and original in files table (url-encoded),
                    'file' => Uploaded-File,
                    'replace' => boolean (default: true) // replaces file with identical path
         * Response: JSON
         */
        Route::post('upload','B3Controller@upload');


    });
});
