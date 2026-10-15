# Pixl Archive

## Requirements

- PHP 8.1+ with the `fileinfo` extension
- `ffmpeg`, `ffprobe`, and `yt-dlp` available in `PATH`
- Browser access to the Tailwind CSS and Alpine.js CDNs

## Run

```sh
cd media-hoard
php -S 127.0.0.1:8000 -t public public/index.php
```

## Features

- Local video and multi-image uploads, yt-dlp downloads, thumbnails, and JSON metadata
- Search, tag/type/favorite filters, tag playlists, and favorites
- Custom video player with contextual autoplay, keyboard controls, and section trimming
- Image gallery with full-size viewer, tags, favorites, and previous/next navigation
- Runtime thumbnail, upload-limit, and download-format settings
