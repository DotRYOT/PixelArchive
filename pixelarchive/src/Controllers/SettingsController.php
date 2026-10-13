<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Utils\Config;
use App\Utils\Response;

/**
 * Runtime settings page — reads and writes config.php through Config::set().
 */
final class SettingsController
{
    private const ACCENTS = ['cyan', 'emerald', 'amber'];

    public function index(): void
    {
        echo view('views/pages/settings.php', [
            'config'  => self::editable(),
            'accents' => self::ACCENTS,
            'saved'   => isset($_GET['saved']),
            'error'   => isset($_GET['error']) ? (string) $_GET['error'] : null,
        ], 'Settings');
    }

    /** POST /settings */
    public function save(): void
    {
        $in = Response::input();

        $frameTime = filter_var($in['frameTime'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 3600]]);
        $thumbW    = filter_var($in['thumbWidth'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 64, 'max_range' => 3840]]);
        $thumbH    = filter_var($in['thumbHeight'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 64, 'max_range' => 2160]]);
        $maxFiles  = filter_var($in['maxFiles'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        $ext       = strtolower(Response::strParam($in, 'videoExtension'));
        $accent    = Response::strParam($in, 'accent');
        $openTab   = array_key_exists('openMediaTab', $in) && filter_var($in['openMediaTab'], FILTER_VALIDATE_BOOLEAN);

        if ($frameTime === false || $thumbW === false || $thumbH === false || $maxFiles === false) {
            Response::redirect('/settings', ['error' => 'Numeric fields are out of range or invalid.']);
        }
        if (!preg_match('/^[a-z0-9]{2,5}$/', $ext)) {
            Response::redirect('/settings', ['error' => 'Video extension must be 2–5 alphanumeric characters.']);
        }
        if (!in_array($accent, self::ACCENTS, true)) {
            $accent = 'cyan';
        }

        Config::set([
            'frameTime'      => $frameTime,
            'thumbWidth'     => $thumbW,
            'thumbHeight'    => $thumbH,
            'maxFiles'       => $maxFiles,
            'videoExtension' => $ext,
            'accent'         => $accent,
            'openMediaTab'   => $openTab,
        ]);

        Response::redirect('/settings', ['saved' => '1']);
    }

    /** @return array<string,mixed> The subset of config exposed to the UI. */
    private static function editable(): array
    {
        return [
            'frameTime'      => Config::getInt('frameTime', 5),
            'thumbWidth'     => Config::getInt('thumbWidth', 640),
            'thumbHeight'    => Config::getInt('thumbHeight', 360),
            'videoExtension' => Config::getString('videoExtension', 'mp4'),
            'maxFiles'       => Config::getInt('maxFiles', 100),
            'openMediaTab'   => Config::getBool('openMediaTab', false),
            'accent'         => Config::getString('accent', 'cyan'),
        ];
    }
}
