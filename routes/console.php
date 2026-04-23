<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// media conversion command
Artisan::command('media:convert {id} {path} {thumbpath} {file} {disk} {thumbdisk}', function (int $id, string $path, string $thumbpath, string $file, string $disk, string $thumbdisk) {
    $processor = new \App\Custom\MediaProcessor($path, $thumbpath, $file, $disk, $thumbdisk);
    try {
        $processor->run($id);
        $this->info('Media conversion successful.');
    } catch (\Exception $e) {
        $this->error('Media conversion failed: ' . $e->getMessage());
    }
})->purpose('Convert media files using FFMpeg');


