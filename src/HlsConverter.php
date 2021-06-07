<?php

namespace Astrotomic\MediaLibrary\Hls;

use Astrotomic\MediaLibrary\Hls\Events\HlsHasBeenGenerated;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\FFProbe;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Conversions\ImageGenerators\Video;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;
use Spatie\MediaLibrary\Support\TemporaryDirectory;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class HlsConverter
{
    public const RES_360P = '360p';
    public const RES_720P = '720p';
    public const RES_1080P = '1080p';
    public const RES_1440P = '1440p';
    public const RES_2160P = '2160p';

    public const RESOLUTIONS = [
        // name => [width, height, video-bitrate, audio-bitrate]
        self::RES_360P => [-2, 360, 900, 64],
        self::RES_720P => [-2, 720, 3200, 128],
        self::RES_1080P => [-2, 1080, 5300, 192],
        self::RES_1440P => [-2, 1440, 11000, 192],
        self::RES_2160P => [-2, 2160, 18200, 192],
    ];

    protected Filesystem $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function execute(Media $media): ?string
    {
        if(!$this->canConvert($media)) {
            return null;
        }

        $temporaryDirectory = TemporaryDirectory::create();

        $copiedOriginalFile = $this->filesystem->copyFromMediaLibrary(
            $media,
            $temporaryDirectory->path(Str::random(32) . '.' . $media->extension)
        );

        $filepath = $this->convert($copiedOriginalFile);
        $directory = dirname($filepath);

        foreach(File::allFiles($directory) as $file) {
            $this->filesystem->copyToMediaLibrary(
                $file->getPathname(),
                $media,
                'conversions',
                "hls/{$file->getRelativePathname()}"
            );
        }

        $media->markAsConversionGenerated('hls');

        $diskRelativePath = $this->filesystem->getConversionDirectory($media).'hls/playlist.m3u8';

        HlsHasBeenGenerated::dispatch($media, $media->conversions_disk, $diskRelativePath);

        return $diskRelativePath;
    }

    public function convert(string $file): string
    {
        $output = dirname($file).'/hls';
        @mkdir($output, 0777, true);

        $ffprobe = FFProbe::create([
            'ffprobe.binaries' => $this->ffprobe(),
        ]);

        if(!$ffprobe->isValid($file)) {
            throw new InvalidArgumentException();
        }

        $resolutions = $this->getResolutions(
            $ffprobe->streams($file)->videos()->first()->getDimensions()
        );

        // https://gist.github.com/Andrey2G/78d42b5c87850f8fbadd0b670b0e6924
        $command = implode(' ', [
            $this->ffmpeg(),
            "-n -i \"{$file}\"",
            $resolutions->map(fn() => '-map 0:v:0 -map 0:a:0')->implode(' '),
            '-c:v h264 -crf 20 -c:a aac -ar 48000',
            $resolutions
                ->values()
                ->map(fn(array $r, int $i) => "-filter:v:{$i} scale=w={$r[0]}:h={$r[1]}:force_original_aspect_ratio=decrease -maxrate:v:{$i} {$r[2]}k -b:a:{$i} {$r[3]}k")
                ->implode(' '),
            Str::of(
                $resolutions
                    ->keys()
                    ->map(fn(string $name, int $i) => "v:{$i},a:{$i},name:{$name}")
                    ->implode(' ')
            )->prepend('-var_stream_map "')->append('"'),
            '-preset slow -hls_list_size 0 -threads 0 -f hls -hls_playlist_type event -hls_time 4 -hls_flags independent_segments -master_pl_name "playlist.m3u8"',
            "-hls_segment_filename \"{$output}/%v/%04d.ts\"",
            "\"{$output}/%v/playlist.m3u8\"",
        ]);

        Process::fromShellCommandline($command)
            ->setTimeout(0)
            ->mustRun();

        return $output.'/playlist.m3u8';
    }

    protected function canConvert(Media $media): bool
    {
        if (! $this->requirementsAreInstalled()) {
            return false;
        }

        return $this->canHandleMimeType(Str::lower($media->mime_type));
    }

    protected function canHandleMimeType(string $mime): bool
    {
        return collect(['video/mp4'])->contains($mime);
    }

    protected function requirementsAreInstalled(): bool
    {
        return class_exists(FFProbe::class)
            && file_exists($this->ffprobe())
            && file_exists($this->ffmpeg());
    }

    protected function ffmpeg(): string
    {
        return (new ExecutableFinder)->find('ffmpeg', config('media-library.ffmpeg_path', 'ffmpeg'));
    }

    protected function ffprobe(): string
    {
        return (new ExecutableFinder)->find('ffprobe', config('media-library.ffprobe_path', 'ffprobe'));
    }

    protected function getResolutions(Dimension $dimensions): Collection
    {
        return collect(self::RESOLUTIONS)
            ->filter(fn(array $resolution): bool => $resolution[1] <= $dimensions->getHeight());
    }
}
