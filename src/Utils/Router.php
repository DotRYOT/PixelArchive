<?php
declare(strict_types=1);

namespace App\Utils;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void { $this->add('GET', $path, $handler); }
    public function post(string $path, callable $handler): void { $this->add('POST', $path, $handler); }

    public function dispatch(string $method, string $path): mixed
    {
        foreach ($this->routes as [$routeMethod, $routePath, $handler]) {
            if ($routeMethod === $method && $routePath === $path) {
                return $handler();
            }
        }
        if (str_starts_with($path, '/api/')) {
            return Response::json(['success' => false, 'message' => 'Route not found.'], 404);
        }
        return Response::html('<h1>404 — Not found</h1>', 404);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [$method, $path, $handler];
    }
}
