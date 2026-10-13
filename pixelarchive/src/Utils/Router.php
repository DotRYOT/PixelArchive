<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * Minimal router: exact-path matching with method checks.
 * Handlers receive the Request-ish globals via parameters.
 */
final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = ['GET' => [], 'POST' => []];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$this->normalize($path)] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$this->normalize($path)] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path = $this->normalize(parse_url($uri, PHP_URL_PATH) ?: '/');

        // Serve real files under /assets directly (app.css, app.js).
        if ($method === 'GET' && str_starts_with($path, '/assets/')) {
            $file = dirname(__DIR__, 2) . '/public' . $path;
            $real = realpath($file);
            $root = realpath(dirname(__DIR__, 2) . '/public');
            if ($real !== false && $root !== false && str_starts_with($real, $root) && is_file($real)) {
                $types = ['css' => 'text/css', 'js' => 'application/javascript'];
                $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
                header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
                header('Cache-Control: no-cache');
                readfile($real);
                return;
            }
        }

        if ($method === 'POST' && !isset($this->routes['POST'][$path]) && isset($this->routes['GET'][$path])) {
            Response::json(['error' => 'Method Not Allowed: this endpoint requires POST.'], 405);
            return;
        }

        $handler = $this->routes[$method][$path] ?? null;
        if ($handler === null) {
            if ($method === 'GET') {
                Response::setStatusCode(404);
                echo view('views/pages/404.php', [], 'PixelArchive — Not found');
                return;
            }
            Response::json(['error' => 'Not Found'], 404);
            return;
        }

        try {
            $handler();
        } catch (\Throwable $e) {
            error_log('[PixelArchive] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            if (str_starts_with($path, '/api/')) {
                Response::json(['error' => 'Internal server error. Check the PHP error log for details.'], 500);
            } else {
                http_response_code(500);
                echo '<!doctype html><meta charset="utf-8"><title>Server error</title>'
                   . '<body style="background:#000;color:#fff;font-family:system-ui;display:grid;'
                   . 'place-items:center;height:100vh;margin:0"><div style="text-align:center">'
                   . '<h1 style="color:#22d3ee">Something went wrong</h1>'
                   . '<p style="color:#a3a3a3">An internal error occurred. Details were written to the server log.</p>'
                   . '<a href="/" style="color:#22d3ee">Back to library</a></div></body>';
            }
        }
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim(rawurldecode($path), '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
