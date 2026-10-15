<?php
declare(strict_types=1);

namespace App\Utils;

final class Response
{
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    public static function html(string $content, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $content;
        exit;
    }

    public static function file(string $path, string $mime, ?string $downloadName = null): never
    {
        if (!is_file($path) || !is_readable($path)) {
            self::html('Not found', 404);
        }
        $size = (int)filesize($path);
        $start = 0;
        $length = $size;
        $status = 200;
        $range = $_SERVER['HTTP_RANGE'] ?? '';
        if (is_string($range) && $range !== '') {
            if (!preg_match('/^bytes=(\d*)-(\d*)$/', $range, $matches) || ($matches[1] === '' && $matches[2] === '')) {
                header('Content-Range: bytes */' . $size);
                http_response_code(416);
                exit;
            }
            if ($matches[1] === '') {
                $suffix = (int)$matches[2];
                $start = max(0, $size - $suffix);
            } else {
                $start = (int)$matches[1];
                if ($matches[2] !== '') {
                    $length = (int)$matches[2] - $start + 1;
                } else {
                    $length = $size - $start;
                }
            }
            if ($start >= $size || $length <= 0) {
                header('Content-Range: bytes */' . $size);
                http_response_code(416);
                exit;
            }
            $length = min($length, $size - $start);
            $status = 206;
        }
        http_response_code($status);
        header('Content-Type: ' . $mime);
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . $length);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        if ($status === 206) {
            header('Content-Range: bytes ' . $start . '-' . ($start + $length - 1) . '/' . $size);
        }
        if ($downloadName !== null) {
            header('Content-Disposition: inline; filename="' . str_replace('"', '', basename($downloadName)) . '"');
        }
        $handle = fopen($path, 'rb');
        if ($handle === false || fseek($handle, $start) !== 0) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            exit;
        }
        $remaining = $length;
        while ($remaining > 0 && !feof($handle)) {
            $chunk = fread($handle, min(8192, $remaining));
            if ($chunk === false || $chunk === '') {
                break;
            }
            echo $chunk;
            $remaining -= strlen($chunk);
        }
        fclose($handle);
        exit;
    }
}
