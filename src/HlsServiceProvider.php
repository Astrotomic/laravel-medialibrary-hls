<?php

namespace Astrotomic\MediaLibrary\Hls;

use Astrotomic\MediaLibrary\Hls\Listeners\ConvertVideoToHls;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAdded;

class HlsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Event::listen(
            MediaHasBeenAdded::class,
            ConvertVideoToHls::class
        );
    }
}