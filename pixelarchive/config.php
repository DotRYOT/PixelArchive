<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PixelArchive — central configuration
|--------------------------------------------------------------------------
| Runtime-editable settings. Paths are resolved from __DIR__ so nothing
| is hardcoded to a machine. The settings page writes back to this file.
*/

return [
    // Thumbnail extraction
    'frameTime'      => 5,      // seconds into the video to grab a frame
    'thumbWidth'     => 640,
    'thumbHeight'    => 360,

    // Media
    'videoExtension' => 'mp4',
    'maxFiles'       => 100,    // max files per upload request
    'openMediaTab'   => false,  // open media in a new browser tab on click

    // UI
    'accent'         => 'cyan', // single accent color (cyan | emerald | amber)

    // Storage root — resolved relative to this config file
    'storagePath'    => __DIR__ . '/storage',

    // External tools (expected on PATH)
    'ffmpeg'         => 'ffmpeg',
    'ffprobe'        => 'ffprobe',
    'ytdlp'          => 'yt-dlp',
];
