<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PixelArchive — front controller
|--------------------------------------------------------------------------
| Single entry point. All requests route through here (PHP built-in server
| router flag, or Apache/Nginx rewrite to /public/index.php).
*/

error_reporting(E_ALL);
ini_set('display_errors', '0');           // never leak stack traces to the browser
ini_set('log_errors', '1');

/* Minimal polyfills so mbstring is not a hard requirement. */
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $s): string { return strtolower($s); }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $start, ?int $len = null): string
    {
        return $len === null ? substr($s, $start) : substr($s, $start, $len);
    }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s): int { return strlen($s); }
}

define('MH_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $rel = str_replace('\\', '/', substr($class, 4));
    $file = MH_ROOT . '/src/' . $rel . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

use App\Controllers\HomeController;
use App\Controllers\MediaController;
use App\Controllers\PlayerController;
use App\Controllers\SettingsController;
use App\Controllers\UploadController;
use App\Utils\Config;
use App\Utils\Response;
use App\Utils\Router;

Config::init(MH_ROOT . '/config.php');

/* Ensure storage layout exists on first run. */
foreach (['videos', 'images', 'cache', 'temp'] as $dir) {
    Config::storageDir($dir);
}

/**
 * Escape helper for views.
 */
function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Human duration "1:02:03" / "4:05".
 */
function fmt_duration(float $sec): string
{
    $sec = max(0, (int) round($sec));
    $h = intdiv($sec, 3600);
    $m = intdiv($sec % 3600, 60);
    $s = $sec % 60;
    return $h > 0
        ? sprintf('%d:%02d:%02d', $h, $m, $s)
        : sprintf('%d:%02d', $m, $s);
}

/**
 * Render a page view inside the base layout.
 * @param array<string,mixed> $data
 */
function view(string $pageRelative, array $data = [], string $pageTitle = 'PixelArchive'): string
{
    $page = MH_ROOT . '/' . ltrim($pageRelative, '/');
    if (!is_file($page)) {
        throw new \RuntimeException('View not found: ' . $pageRelative);
    }

    extract($data, EXTR_SKIP);

    ob_start();
    require $page;
    $content = ob_get_clean();

    ob_start();
    require MH_ROOT . '/views/layouts/base.php';
    return (string) ob_get_clean();
}

/* ------------------------------ Routes -------------------------------- */

$router = new Router();

$router->get('/', [new HomeController(), 'index']);
$router->get('/gallery', [new MediaController(), 'gallery']);
$router->get('/playlists', [new MediaController(), 'playlists']);
$router->get('/player', [new PlayerController(), 'show']);
$router->get('/upload', [new UploadController(), 'form']);
$router->get('/settings', [new SettingsController(), 'index']);

// File streaming + placeholder (GET, but real payloads, not JSON pages)
$router->get('/media', [new MediaController(), 'serveFile']);
$router->get('/placeholder', [new MediaController(), 'placeholder']);

// API
$router->post('/api/upload', [new UploadController(), 'apiUpload']);
$router->post('/api/download', [new UploadController(), 'apiDownload']);
$router->post('/api/media/update', [new MediaController(), 'apiUpdate']);
$router->post('/api/media/trim', [new MediaController(), 'apiTrim']);
$router->post('/api/media/delete', [new MediaController(), 'apiDelete']);
$router->post('/api/playlists', [new MediaController(), 'apiPlaylistCreate']);
$router->post('/api/playlists/delete', [new MediaController(), 'apiPlaylistDelete']);

$router->post('/settings', [new SettingsController(), 'save']);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
