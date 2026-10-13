<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * HTTP response helpers: JSON for API routes, redirects, view rendering.
 */
final class Response
{
    /** @param array<string,mixed>|list<mixed> $data */
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** @param array<string,mixed> $data */
    public static function ok(array $data = []): never
    {
        self::json(array_merge(['ok' => true], $data));
    }

    public static function error(string $message, int $status = 400): never
    {
        self::json(['ok' => false, 'error' => $message], $status);
    }

    /** @param array<string,string> $params */
    public static function redirect(string $path, array $params = []): never
    {
        $qs = $params !== [] ? '?' . http_build_query($params) : '';
        header('Location: ' . $path . $qs);
        exit;
    }

    public static function setStatusCode(int $code): void
    {
        http_response_code($code);
    }

    /** Read the raw POST body as JSON (used by API fetch calls). */
    public static function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Merged input from form POST and JSON body, always strings/arrays only.
     * @return array<string,mixed>
     */
    public static function input(): array
    {
        $body = $_POST;
        if ($body === []) {
            $body = self::jsonBody();
        }
        return $body;
    }

    public static function strParam(array $src, string $key, string $default = ''): string
    {
        $v = $src[$key] ?? $default;
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public static function idParam(array $src, string $key = 'id'): string
    {
        $id = self::strParam($src, $key);
        if (!Database::isValidId($id)) {
            self::error('Invalid or missing media id.', 422);
        }
        return $id;
    }
}
