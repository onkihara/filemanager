<?php

namespace App\Providers;

use App\Listeners\MediaConvertedListener;
use App\Events\MediaConvertedEvent;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Event::listen(MediaConvertedEvent::class, MediaConvertedListener::class);
    }
}
