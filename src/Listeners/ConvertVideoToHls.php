<?php

namespace Astrotomic\MediaLibrary\Hls\Listeners;

use Astrotomic\MediaLibrary\Hls\HlsConverter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAdded;

class ConvertVideoToHls implements ShouldQueue
{
    use InteractsWithQueue;

    public bool $afterCommit = true;

    protected HlsConverter $hls;

    public function __construct(HlsConverter $hls)
    {
        $this->hls = $hls;
    }

    public function handle(MediaHasBeenAdded $event): void
    {
        $this->hls->execute($event->media);
    }
}