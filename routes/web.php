<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FilesController;
use App\Http\Controllers\OauthController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::get('/', function () {
    return redirect()->route('list');
});

// oauth-routes
Route::get('login', [OauthController::class,'login'])->name('login');
Route::get('/logout', [OauthController::class,'logout']);
Route::get('/register', [OauthController::class,'register']);
Route::get('/oauth/login', [OauthController::class,'oauthLogin']);
Route::get('/oauth/callback', [OauthController::class,'oauthAuthorize']);


// filemanager-Routes adjusted for testing
$prefix = app()->runningUnitTests() ? '' : LaravelLocalization::setLocale();
$middleware = app()->runningUnitTests() ? ['auth.simple'] : ['auth.simple', 'localeSessionRedirect', 'localizationRedirect'];

Route::group([
    'prefix' => $prefix,
    'middleware' => $middleware,
], function() {

    /**
     * Request: scope (optional)
     */
    Route::get('files', [FilesController::class,'index'])->name('list');

    Route::delete('delete', [FilesController::class,'delete']);

});

// filemanager-requests

Route::group([
    'prefix' => 'request',
    'middleware' => 'auth.simple',
],function() {

    /**
     * Request: scope (optional), dimensions (optional [width,height,fill(=cover|contain)])
     */
    Route::post('/upload',[FilesController::class,'upload']);

    Route::delete('/delete/{id}',[FilesController::class,'delete']);
    Route::get('/type/{type}',[FilesController::class,'getFilemanager']); // @TODO
    Route::get('/filedata/{id}',[FilesController::class,'fileData']);
});


Route::get('thumbnail/{id}',[FilesController::class,'getFile'])->name('thumbnail');
Route::get('image/{id}',[FilesController::class,'getFile'])->name('image');
Route::get('media/{id}',[FilesController::class,'getFile'])->name('media');
Route::get('download/{id}',[FilesController::class,'getFile'])->name('download');
Route::get('file/{id}',[FilesController::class,'getFile'])->name('file'); // @TODO
