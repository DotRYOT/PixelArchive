# PixelArchive

Local-first media library. Modern PHP 8.x, no framework, no Composer, no build step.
OLED high-contrast UI (pure black + one cyan accent) built with Tailwind CSS and Alpine.js via CDN.

## Requirements

- PHP 8.1+ with `fileinfo` enabled (bundled on Windows and Arch/CachyOS)
- `ffmpeg`, `ffprobe`, and `yt-dlp` available on the system `PATH`
- Internet access for the Tailwind/Alpine CDNs (UI only — media stays local)

## Run

```bash
cd pixelarchive
php -S localhost:8000 -t public public/index.php
```

Open http://localhost:8000

Works identically on Windows 10 and Linux. For Apache/Nginx, point the document root at
`public/` and rewrite all requests to `public/index.php`.

## Features

- **Library home** — responsive grid of all videos & images, search by title/tag, filters by type / tag / favorites
- **Uploads** — multiple videos + images in one request, MIME-verified, safe generated filenames, `maxFiles` respected
- **yt-dlp downloads** — paste a URL, it lands in the library with metadata + thumbnail
- **Thumbnails** — ffmpeg frame extraction at a configurable offset, letterboxed to config dimensions, SVG placeholder fallback
- **Custom video player** — play/pause, seek, volume, fullscreen, time/duration, keyboard shortcuts (Space, ←/→, F, M)
- **Trim sections** — remove [start,end] from a video (seconds or HH:MM:SS); temp-file write, atomic replace, thumbnail regen
- **Autoplay next** — "Up next" overlay with thumbnail, title, 5-second countdown, Cancel / Play Now; queue follows playlist/tag context
- **Tags & playlists** — clickable tag chips, playlists are named tag collections that filter and feed the autoplay queue
- **Favorites** — id-only favorite lists joined against master metadata (no duplication, no sync errors)
- **Image gallery** — grid + lightbox viewer with prev/next and keyboard navigation; tagging & favoriting like videos
- **Settings page** — runtime editing of `config.php` (frame time, thumb size, extension, max files, accent, open-in-tab)
- **JSON data layer** — locked, atomic writes; graceful init of missing/empty files; UUID ids with traversal rejection
- **Security** — every shell argument escaped, all `/api/*` routes return JSON, no stack traces exposed

## Layout

```
pixelarchive/
├── public/        front controller + assets
├── src/           Controllers / Services / Utils (App\* namespace)
├── views/         layouts / partials / pages (plain PHP templates)
├── storage/       videos, images, cache (thumbnails), temp, *.json databases
└── config.php     central, runtime-editable configuration
```

## TODO (optional extensions)

- Per-tool path overrides (ffmpeg/ffprobe/yt-dlp binaries) on the settings page
- Bulk-select multi-delete from the library grid
- Import/export of the JSON store
