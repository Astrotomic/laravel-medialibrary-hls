<?php

namespace Tests;

use Astrotomic\MediaLibrary\Hls\Events\HlsHasBeenGenerated;
use Astrotomic\MediaLibrary\Hls\HlsConverter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Models\Lesson;

class IntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        File::deleteDirectories(__DIR__.'/disks/public');

        $this->setUpDatabase();
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        config()->set('filesystems.disks.public', [
            'driver' => 'local',
            'root' => __DIR__.'/disks/public',
        ]);
    }

    protected function setUpDatabase(): void
    {
        $this->app['db']->connection()->getSchemaBuilder()->create('lessons', function (Blueprint $table) {
            $table->id('id');
            $table->string('title');
            $table->timestamps();
        });

        include_once(__DIR__  . '/../vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub');

        (new \CreateMediaTable())->up();
    }

    /** @test */
    public function it_converts_attached_video(): void
    {
        Event::fake(HlsHasBeenGenerated::class);

        $lesson = Lesson::create([
            'title' => 'First Lesson',
        ]);

        /** @var \Spatie\MediaLibrary\MediaCollections\Models\Media $media */
        $media = $lesson
            ->addMedia( __DIR__ . '/testfiles/Rainbow_Nebula_Background.360p.mp4')
            ->preservingOriginal()
            ->toMediaCollection();

        $this->assertInstanceOf(Media::class, $media);

        Event::assertDispatched(HlsHasBeenGenerated::class, function (HlsHasBeenGenerated $event): bool {
            return $event->media->hasGeneratedConversion('hls')
                && Storage::disk($event->disk)->exists($event->filepath);
        });
    }

    /** @test */
    public function it_does_not_do_anything_with_unsupported_files(): void
    {
        Event::fake(HlsHasBeenGenerated::class);

        $lesson = Lesson::create([
            'title' => 'First Lesson',
        ]);

        /** @var \Spatie\MediaLibrary\MediaCollections\Models\Media $media */
        $media = $lesson
            ->addMedia( __DIR__ . '/testfiles/test.jpg')
            ->preservingOriginal()
            ->toMediaCollection();

        $this->assertInstanceOf(Media::class, $media);

        Event::assertNotDispatched(HlsHasBeenGenerated::class);

        $this->assertFalse($media->fresh()->hasGeneratedConversion('hls'));

        $this->assertEquals(["{$media->getKey()}/test.jpg"], Storage::disk($media->conversions_disk)->allFiles($media->getKey()));
    }
}
