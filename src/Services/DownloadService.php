<?php
declare(strict_types=1);

namespace App\Services;

use App\Utils\Config;
use RuntimeException;

final class DownloadService
{
    public function __construct(
        private readonly Config $config,
        private readonly MediaService $media
    ) {
    }

    public function download(string $url): array
    {
        $url = trim($url);
        if (strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Enter a valid video URL.');
        }
        if (strpbrk($url, "\r\n\0") !== false) {
            throw new RuntimeException('Video URLs cannot contain line breaks.');
        }
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true) ||
            empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Only public HTTP and HTTPS video URLs are accepted.');
        }

        $tempDir = $this->config->storagePath('temp');
        $id = bin2hex(random_bytes(16));
        $outputBase = $tempDir . DIRECTORY_SEPARATOR . $id;
        $urlFile = $tempDir . DIRECTORY_SEPARATOR . $id . '.url';
        $extension = $this->config->string('videoExtension');
        if (!in_array($extension, ['mp4', 'webm', 'mkv'], true)) {
            throw new RuntimeException('Downloaded video extension must be mp4, webm, or mkv.');
        }
        if (file_put_contents($urlFile, $url . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Unable to prepare the video URL for download.');
        }
        $titleCommand = 'yt-dlp ' . implode(' ', array_map('escapeshellarg', [
            '--no-playlist', '--no-warnings', '--get-title', '--batch-file', $urlFile,
        ]));
        $command = 'yt-dlp ' . implode(' ', array_map('escapeshellarg', [
            '--no-playlist', '--no-warnings', '--no-simulate', '--merge-output-format', $extension,
            '--recode-video', $extension, '--output', $outputBase,
            '--batch-file', $urlFile,
        ]));
        try {
            $titleResult = $this->run($titleCommand);
            if ($titleResult['code'] !== 0) {
                throw new RuntimeException('Unable to read the video title. ' . $this->message($titleResult['stderr']));
            }
            $result = $this->run($command);
        } catch (\Throwable $exception) {
            $this->removeTemporaryFiles($tempDir, $id);
            throw $exception;
        } finally {
            if (is_file($urlFile) && !unlink($urlFile)) {
                error_log('Unable to remove the temporary download URL file.');
            }
        }
        if ($result['code'] !== 0) {
            $this->removeTemporaryFiles($tempDir, $id);
            throw new RuntimeException('Download failed. ' . $this->message($result['stderr']));
        }

        $candidate = $tempDir . DIRECTORY_SEPARATOR . $id . '.' . $extension;
        if (!is_file($candidate)) {
            $this->removeTemporaryFiles($tempDir, $id);
            throw new RuntimeException('yt-dlp completed without producing a video file.');
        }
        try {
            $title = trim(explode(PHP_EOL, trim($titleResult['stdout']))[0] ?? '');
            return $this->media->createVideoFromFile($candidate, $title, $this->config->string('videoExtension'));
        } finally {
            if (is_file($candidate)) {
                @unlink($candidate);
            }
        }
    }

    private function run(string $command): array
    {
        $errorFile = tempnam($this->config->storagePath('temp'), 'yt-dlp-');
        if ($errorFile === false) {
            throw new RuntimeException('Unable to create download log.');
        }
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $errorFile, 'w']];
        $process = proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            @unlink($errorFile);
            throw new RuntimeException('Unable to start yt-dlp.');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $code = proc_close($process);
        $stderr = file_get_contents($errorFile);
        @unlink($errorFile);
        return [
            'code' => $code,
            'stdout' => is_string($stdout) ? $stdout : '',
            'stderr' => is_string($stderr) ? $stderr : '',
        ];
    }

    private function message(string $error): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $error) ?? '');
        return $message === '' ? 'Check the URL and that yt-dlp is available.' : substr($message, 0, 400);
    }

    private function removeTemporaryFiles(string $directory, string $id): void
    {
        foreach (glob($directory . DIRECTORY_SEPARATOR . $id . '*') ?: [] as $path) {
            if (is_file($path) && !unlink($path)) {
                error_log('Unable to remove a temporary download file: ' . basename($path));
            }
        }
    }
}
