<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\DownloadService;
use App\Services\MediaService;
use App\Utils\Config;
use App\Utils\Response;
use InvalidArgumentException;
use RuntimeException;

final class UploadController
{
    public function __construct(
        private readonly MediaService $media,
        private readonly DownloadService $downloads,
        private readonly Config $config
    ) {
    }

    public function page(): never
    {
        app_render('upload', [
            'pageTitle' => 'Add media',
            'activeNav' => 'upload',
            'maxFiles' => $this->config->int('maxFiles'),
        ]);
    }

    public function upload(): never
    {
        try {
            if ($_POST !== [] || array_diff(array_keys($_FILES), ['videos', 'images', 'video', 'image', 'files']) !== []) {
                throw new InvalidArgumentException('Unsupported upload request field.');
            }
            $result = $this->media->upload($_FILES);
            Response::json([
                'success' => true,
                'message' => count($result['items']) . ' file(s) added to your library.',
                'items' => $result['items'],
                'errors' => $result['errors'],
            ]);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function download(): never
    {
        try {
            $input = app_request_data();
            if (array_diff(array_keys($input), ['url']) !== []) {
                throw new InvalidArgumentException('Unsupported download request field.');
            }
            if (!is_string($input['url'] ?? null)) {
                throw new InvalidArgumentException('A video URL is required.');
            }
            $item = $this->downloads->download($input['url']);
            Response::json(['success' => true, 'message' => 'Video downloaded and added to your library.', 'item' => $item]);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
