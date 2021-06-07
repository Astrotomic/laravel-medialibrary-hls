<?php

namespace Tests;

use Astrotomic\MediaLibrary\Hls\HlsConverter;
use FFMpeg\FFProbe;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\ExecutableFinder;

class VideoHlsTest extends TestCase
{
    protected FFProbe $ffprobe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ffprobe = FFProbe::create([
            'ffprobe.binaries' => (new ExecutableFinder)->find('ffprobe'),
        ]);
    }

    /**
     * @test
     * @dataProvider videoFiles
     *
     * @param string $videoFilePath
     * @param string[] $resolutions
     */
    public function it_converts_video_files(string $videoFilePath, array $resolutions): void
    {
        $dimensions = $this->ffprobe->streams($videoFilePath)->videos()->first()->getDimensions();

        $generator = app(HlsConverter::class);
        $playlistFilePath = $generator->convert($videoFilePath);
        $directory = dirname($playlistFilePath);

        $this->assertStringEndsWith('/hls/playlist.m3u8', $playlistFilePath);
        $this->assertFileExists($playlistFilePath);
        $this->assertTrue($this->ffprobe->isValid($playlistFilePath));
        $duration = (float)$this->ffprobe->format($playlistFilePath)->get('duration');
        $this->assertGreaterThan(0, $duration);
        $this->assertSame($dimensions->getRatio()->getValue(), $this->ffprobe->streams($playlistFilePath)->videos()->first()->getDimensions()->getRatio()->getValue());

        $m3u8 = File::get($playlistFilePath);

        foreach ($resolutions as $resolution) {
            $this->assertStringContainsString("{$resolution}/playlist.m3u8", $m3u8);
            $this->assertDirectoryExists("{$directory}/{$resolution}");
            $this->assertFileExists("{$directory}/{$resolution}/playlist.m3u8");
            $this->assertTrue($this->ffprobe->isValid("{$directory}/{$resolution}/playlist.m3u8"));
            $this->assertSame(
                $duration,
                (float)$this->ffprobe->format("{$directory}/{$resolution}/playlist.m3u8")->get('duration')
            );
            $this->assertSame(
                $dimensions->getRatio()->getValue(),
                $this->ffprobe->streams("{$directory}/{$resolution}/playlist.m3u8")->videos()->first()->getDimensions()->getRatio()->getValue()
            );
            if(HlsConverter::RESOLUTIONS[$resolution][0] > 0) {
                $this->assertLessThanOrEqual(
                    HlsConverter::RESOLUTIONS[$resolution][0],
                    $this->ffprobe->streams("{$directory}/{$resolution}/playlist.m3u8")->videos()->first()->getDimensions()->getWidth()
                );
            }
            $this->assertGreaterThan(
                0,
                $this->ffprobe->streams("{$directory}/{$resolution}/playlist.m3u8")->videos()->first()->getDimensions()->getWidth()
            );
            $this->assertSame(
                HlsConverter::RESOLUTIONS[$resolution][1],
                $this->ffprobe->streams("{$directory}/{$resolution}/playlist.m3u8")->videos()->first()->getDimensions()->getHeight()
            );
        }

        foreach (array_diff(array_keys(HlsConverter::RESOLUTIONS), $resolutions) as $resolution) {
            $this->assertStringNotContainsString("{$resolution}/playlist.m3u8", $m3u8);
            $this->assertDirectoryDoesNotExist("{$directory}/{$resolution}");
        }
    }

    public function videoFiles(): array
    {
        return [
            HlsConverter::RES_360P => [
                __DIR__ . '/testfiles/Rainbow_Nebula_Background.360p.mp4',
                [HlsConverter::RES_360P],
            ],
            HlsConverter::RES_720P => [
                __DIR__ . '/testfiles/Rainbow_Nebula_Background.720p.mp4',
                [HlsConverter::RES_360P, HlsConverter::RES_720P],
            ],
            HlsConverter::RES_1080P => [
                __DIR__ . '/testfiles/Rainbow_Nebula_Background.1080p.mp4',
                [HlsConverter::RES_360P, HlsConverter::RES_720P, HlsConverter::RES_1080P],
            ],
            HlsConverter::RES_1440P => [
                __DIR__ . '/testfiles/Rainbow_Nebula_Background.1440p.mp4',
                [HlsConverter::RES_360P, HlsConverter::RES_720P, HlsConverter::RES_1080P, HlsConverter::RES_1440P],
            ],
            HlsConverter::RES_2160P => [
                __DIR__ . '/testfiles/Rainbow_Nebula_Background.2160p.mp4',
                [HlsConverter::RES_360P, HlsConverter::RES_720P, HlsConverter::RES_1080P, HlsConverter::RES_1440P, HlsConverter::RES_2160P],
            ],
        ];
    }
}
