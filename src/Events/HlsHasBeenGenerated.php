<?php

namespace Astrotomic\MediaLibrary\Hls\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class HlsHasBeenGenerated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Media $media;
    public string $disk;
    public string $filepath;

    public function __construct(
        Media $media,
        string $disk,
        string $filepath
    ) {
        $this->media = $media;
        $this->disk = $disk;
        $this->filepath = $filepath;
    }
}