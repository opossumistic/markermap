<?php

use App\Kernel;

// Defensive: keep project_dir on the release even if vendor layout changes.
$_SERVER['APP_RUNTIME_OPTIONS'] ??= [];
$_SERVER['APP_RUNTIME_OPTIONS']['project_dir'] ??= dirname(__DIR__);

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
