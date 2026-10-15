<?php
declare(strict_types=1);

use App\Controllers\HomeController;
use App\Controllers\MediaController;
use App\Controllers\PlayerController;
use App\Controllers\SettingsController;
use App\Controllers\UploadController;
use App\Services\DownloadService;
use App\Services\FFmpegService;
use App\Services\MediaService;
use App\Services\TagService;
use App\Utils\Config;
use App\Utils\Database;
use App\Utils\Response;
use App\Utils\Router;

ini_set('display_errors', '0');

if (PHP_SAPI === 'cli-server') {
    $requestPath = rawurldecode((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
    $publicFile = __DIR__ . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $requestPath), DIRECTORY_SEPARATOR);
    $publicRoot = realpath(__DIR__);
    $resolvedFile = realpath($publicFile);
    if ($resolvedFile !== false && $publicRoot !== false &&
        str_starts_with($resolvedFile, $publicRoot . DIRECTORY_SEPARATOR) && is_file($resolvedFile)) {
        return false;
    }
}

require_once dirname(__DIR__) . '/src/Utils/Response.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function format_duration(float $seconds): string
{
    $seconds = max(0, (int)floor($seconds));
    $minutes = intdiv($seconds % 3600, 60);
    $remainingSeconds = $seconds % 60;
    return $seconds >= 3600
        ? intdiv($seconds, 3600) . ':' . str_pad((string)$minutes, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string)$remainingSeconds, 2, '0', STR_PAD_LEFT)
        : str_pad((string)$minutes, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string)$remainingSeconds, 2, '0', STR_PAD_LEFT);
}

function app_request_data(): array
{
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        try {
            $decoded = json_decode($raw === false ? '' : $raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('The request body contains invalid JSON.');
        }
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('The request body must be a JSON object.');
        }
        return $decoded;
    }
    return $_POST;
}

function app_render(string $page, array $data = [], int $status = 200): never
{
    global $appConfig;
    $pagePath = dirname(__DIR__) . '/views/pages/' . basename($page) . '.php';
    if (!is_file($pagePath)) {
        throw new RuntimeException('Page template not found.');
    }
    $data += [
        'pageTitle' => 'Pixl Archive',
        'activeNav' => '',
        'openMediaTab' => $appConfig->bool('openMediaTab'),
    ];
    extract($data, EXTR_SKIP);
    ob_start();
    require $pagePath;
    $content = (string)ob_get_clean();
    ob_start();
    require dirname(__DIR__) . '/views/layouts/base.php';
    Response::html((string)ob_get_clean(), $status);
}

set_exception_handler(static function (Throwable $exception): never {
    error_log($exception::class . ': ' . $exception->getMessage());
    $path = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    if (str_starts_with($path, '/api/')) {
        Response::json(['success' => false, 'message' => 'The request could not be completed.'], 500);
    }
    Response::html('<!doctype html><html lang="en"><meta charset="utf-8"><title>Error · Pixl Archive</title>' .
        '<body style="margin:0;background:#000;color:#f5f5f5;font:16px sans-serif;padding:3rem">' .
        '<h1>Something went wrong</h1><p>The request could not be completed. Check the application log for details.</p>' .
        '<a style="color:#22d3ee" href="/">Return to the library</a></body></html>', 500);
});

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = dirname(__DIR__) . '/src/' . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$appConfig = new Config(dirname(__DIR__) . '/config.php');
foreach (['videos', 'images', 'cache', 'temp'] as $directory) {
    $path = $appConfig->storagePath($directory);
    if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to initialize storage.');
    }
}

$database = new Database($appConfig->storagePath());
$ffmpeg = new FFmpegService($appConfig);
$tags = new TagService();
$media = new MediaService($database, $appConfig, $ffmpeg, $tags);
$downloads = new DownloadService($appConfig, $media);
$homeController = new HomeController($media, $database);
$mediaController = new MediaController($media, $database);
$playerController = new PlayerController($media, $database);
$uploadController = new UploadController($media, $downloads, $appConfig);
$settingsController = new SettingsController($appConfig);

$router = new Router();
$router->get('/', [$homeController, 'index']);
$router->get('/gallery', [$homeController, 'gallery']);
$router->get('/playlists', [$homeController, 'playlists']);
$router->get('/player', [$playerController, 'show']);
$router->get('/upload', [$uploadController, 'page']);
$router->get('/settings', [$settingsController, 'show']);
$router->get('/media', [$mediaController, 'serve']);
$router->post('/api/upload', [$uploadController, 'upload']);
$router->post('/api/download', [$uploadController, 'download']);
$router->post('/api/media/update', [$mediaController, 'update']);
$router->post('/api/media/trim', [$mediaController, 'trim']);
$router->post('/api/media/delete', [$mediaController, 'delete']);
$router->post('/api/settings', [$settingsController, 'update']);

$path = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
