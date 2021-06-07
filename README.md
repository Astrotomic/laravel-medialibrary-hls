# Laravel Medialibrary HLS Converter

## Installation

```
composer require astrotomic/laravel-medialibrary-hls
```

## Usage

You can listen for `\Astrotomic\MediaLibrary\Hls\Events\HlsHasBeenGenerated` event to do anything after the HLS files have been generated and stored.

The files will be stored in a structure like the following:
```
conversions/hls/
├── 1080p
│   ├── 0000.ts
│   ├── 0001.ts
│   └── playlist.m3u8
├── 360p
│   ├── 0000.ts
│   ├── 0001.ts
│   └── playlist.m3u8
├── 720p
│   ├── 0000.ts
│   ├── 0001.ts
│   └── playlist.m3u8
└── playlist.m3u8
```

To play the video you should pass the `playlist.m3u8` URL to your video player. The lowest one contains a reference to all explicit playlists so the user can pick the favorite resolution. In case you want to predefine this you can also pass an explicit playlist file to your frontend.