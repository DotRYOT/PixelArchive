<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * Typed accessor for config.php with runtime persistence support.
 */
final class Config
{
    /** @var array<string,mixed>|null */
    private static ?array $config = null;

    private static ?string $configFile = null;

    public static function init(?string $configFile = null): void
    {
        self::$configFile = $configFile ?? dirname(__DIR__, 2) . '/config.php';
        self::$config = null; // force reload
    }

    /** @return array<string,mixed> */
    public static function all(): array
    {
        if (self::$config === null) {
            if (self::$configFile === null) {
                self::init();
            }
            $loaded = is_file((string) self::$configFile) ? require self::$configFile : [];
            self::$config = is_array($loaded) ? $loaded : [];
        }
        /** @var array<string,mixed> */
        return self::$config;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $v = self::get($key, $default);
        return is_numeric($v) ? (int) $v : $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $v = self::get($key, $default);
        if (is_bool($v)) {
            return $v;
        }
        if (is_string($v)) {
            return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
        }
        return (bool) $v;
    }

    public static function getString(string $key, string $default = ''): string
    {
        $v = self::get($key, $default);
        return is_scalar($v) ? (string) $v : $default;
    }

    /** Absolute storage path, always normalized with no trailing separator. */
    public static function storagePath(string ...$parts): string
    {
        $base = rtrim(self::getString('storagePath', dirname(__DIR__, 2) . '/storage'), '/\\');
        $path = $base;
        foreach ($parts as $p) {
            $path .= DIRECTORY_SEPARATOR . trim($p, '/\\');
        }
        return $path;
    }

    /** Ensure a storage subdirectory exists; returns its absolute path. */
    public static function storageDir(string $sub): string
    {
        $dir = self::storagePath($sub);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /**
     * Persist one or more config values by rewriting config.php safely.
     *
     * @param array<string,mixed> $values
     */
    public static function set(array $values): void
    {
        if (self::$configFile === null) {
            self::init();
        }
        $current = self::all();
        $merged = array_merge($current, $values);

        // Keep storagePath anchored to the project directory regardless of UI edits.
        $merged['storagePath'] = dirname(__DIR__, 2) . '/storage';

        $code = "<?php\n\ndeclare(strict_types=1);\n\n"
              . "// PixelArchive configuration — managed via /settings\n\n"
              . 'return ' . self::exportArray($merged) . ";\n";

        $tmp = (string) self::$configFile . '.tmp';
        file_put_contents($tmp, $code, LOCK_EX);
        rename($tmp, (string) self::$configFile);
        self::$config = $merged;
    }

    /** @param array<string,mixed> $arr */
    private static function exportArray(array $arr, string $indent = ''): string
    {
        $out = "[\n";
        foreach ($arr as $k => $v) {
            $out .= $indent . '    ' . var_export((string) $k, true) . ' => ';
            if (is_array($v)) {
                $out .= self::exportArray($v, $indent . '    ');
            } elseif (is_bool($v)) {
                $out .= $v ? 'true' : 'false';
            } elseif (is_int($v) || is_float($v)) {
                $out .= (string) $v;
            } else {
                $out .= var_export((string) $v, true);
            }
            $out .= ",\n";
        }
        return $out . $indent . ']';
    }
}
