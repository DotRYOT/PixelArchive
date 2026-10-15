<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Utils\Config;
use App\Utils\Response;
use InvalidArgumentException;
use RuntimeException;

final class SettingsController
{
    public function __construct(private readonly Config $config)
    {
    }

    public function show(): never
    {
        app_render('settings', [
            'pageTitle' => 'Settings',
            'activeNav' => 'settings',
            'settings' => $this->config->all(),
        ]);
    }

    public function update(): never
    {
        try {
            $input = app_request_data();
            if ($input === []) {
                throw new InvalidArgumentException('Provide at least one setting to save.');
            }
            $this->config->update($input);
            Response::json(['success' => true, 'message' => 'Settings saved.', 'settings' => $this->config->all()]);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
