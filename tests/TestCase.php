<?php

namespace Tests;

use Astrotomic\MediaLibrary\Hls\HlsServiceProvider;
use FFMpeg\FFProbe;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Symfony\Component\Process\ExecutableFinder;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(__DIR__ . '/testfiles/hls');
    }

    protected function getPackageProviders($app): array
    {
        return [
            MediaLibraryServiceProvider::class,
            HlsServiceProvider::class,
        ];
    }
}
