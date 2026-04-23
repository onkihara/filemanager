<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\S3SignedPolicy;
use App\Http\Controllers\S3Controller;

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

    'middleware' => S3SignedPolicy::class

],function() {


    Route::controller(S3Controller::class)->group(function () {

        Route::get('/', 'process');
        Route::get('/{key}', 'process')->where('key', '.*');

        Route::post('/', 'upload');

        // allowing even '/' as key-content
        Route::delete('/{key}', 'delete')->where('key', '.*');

    });
    
});
