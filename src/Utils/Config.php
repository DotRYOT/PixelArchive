<?php
declare(strict_types=1);

namespace App\Utils;

use RuntimeException;

final class Config
{
    private const EDITABLE_KEYS = [
        'frameTime',
        'thumbWidth',
        'thumbHeight',
        'videoExtension',
        'maxFiles',
        'openMediaTab',
        'accent',
    ];

    private array $values;

    public function __construct(private readonly string $file)
    {
        $this->reload();
    }

    public function reload(): void
    {
        $values = require $this->file;
        if (!is_array($values)) {
            throw new RuntimeException('The configuration file must return an array.');
        }
        $this->values = $values;
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->file, true);
        }
    }

    public function int(string $key): int
    {
        $value = $this->values[$key] ?? null;
        if (!is_int($value)) {
            throw new RuntimeException('Invalid integer configuration value: ' . $key);
        }
        return $value;
    }

    public function bool(string $key): bool
    {
        $value = $this->values[$key] ?? null;
        if (!is_bool($value)) {
            throw new RuntimeException('Invalid boolean configuration value: ' . $key);
        }
        return $value;
    }

    public function string(string $key): string
    {
        $value = $this->values[$key] ?? null;
        if (!is_string($value)) {
            throw new RuntimeException('Invalid string configuration value: ' . $key);
        }
        return $value;
    }

    public function storagePath(string $segment = ''): string
    {
        $root = $this->string('storagePath');
        return $segment === ''
            ? $root
            : $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $segment);
    }

    public function all(): array
    {
        $values = $this->values;
        unset($values['storagePath']);
        return $values;
    }

    public function update(array $input): void
    {
        $values = $this->values;
        foreach ($input as $key => $value) {
            if (!in_array($key, self::EDITABLE_KEYS, true)) {
                throw new RuntimeException('Unsupported setting: ' . (string)$key);
            }
            switch ($key) {
                case 'frameTime':
                    $values[$key] = $this->boundedInt($value, 0, 3600, $key);
                    break;
                case 'thumbWidth':
                case 'thumbHeight':
                    $values[$key] = $this->boundedInt($value, 64, 4096, $key);
                    break;
                case 'maxFiles':
                    $values[$key] = $this->boundedInt($value, 1, 1000, $key);
                    break;
                case 'openMediaTab':
                    if (!is_bool($value)) {
                        throw new RuntimeException('openMediaTab must be a boolean.');
                    }
                    $values[$key] = $value;
                    break;
                case 'videoExtension':
                    if (!is_string($value) || !in_array(strtolower($value), ['mp4', 'webm', 'mkv'], true)) {
                        throw new RuntimeException('Video extension must be mp4, webm, or mkv.');
                    }
                    $values[$key] = strtolower($value);
                    break;
                case 'accent':
                    if ($value !== 'cyan') {
                        throw new RuntimeException('Only the cyan accent is supported.');
                    }
                    $values[$key] = 'cyan';
                    break;
            }
        }

        $values['storagePath'] = $this->values['storagePath'];
        $persistedValues = $values;
        unset($persistedValues['storagePath']);
        $source = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($persistedValues, true) .
            " + ['storagePath' => __DIR__ . DIRECTORY_SEPARATOR . 'storage'];\n";
        $lock = fopen($this->file, 'c+');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to lock the configuration file.');
        }

        try {
            if (!ftruncate($lock, 0) || rewind($lock) === false ||
                fwrite($lock, $source) === false || !fflush($lock)) {
                throw new RuntimeException('Unable to save configuration.');
            }
            $this->values = $values;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function boundedInt(mixed $value, int $min, int $max, string $key): int
    {
        if (is_string($value) && preg_match('/^\d+$/', $value)) {
            $value = (int)$value;
        }
        if (!is_int($value) || $value < $min || $value > $max) {
            throw new RuntimeException($key . ' must be an integer between ' . $min . ' and ' . $max . '.');
        }
        return $value;
    }
}
