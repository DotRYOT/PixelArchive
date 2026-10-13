<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\MediaService;
use App\Utils\Response;

/**
 * Home grid of all media with search & filters.
 */
final class HomeController
{
    public function index(): void
    {
        $media = MediaService::make();
        $q = $_GET;

        Response::setStatusCode(200);
        echo view('views/pages/home.php', [
            'items'   => $media->filter($q),
            'tags'    => $media->allTags(),
            'query'   => [
                'q'        => (string) ($q['q'] ?? ''),
                'type'     => (string) ($q['type'] ?? 'all'),
                'tag'      => (string) ($q['tag'] ?? ''),
                'favorite' => (string) ($q['favorite'] ?? ''),
            ],
        ], 'Library');
    }
}
