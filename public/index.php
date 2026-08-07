<?php

use App\Kernel;

// Shared vendor is symlinked from outside the release. Without an explicit
// project_dir, SymfonyRuntime realpath()'s vendor and boots dotenv from shared/.
$_SERVER['APP_RUNTIME_OPTIONS'] ??= [];
$_SERVER['APP_RUNTIME_OPTIONS']['project_dir'] ??= dirname(__DIR__);

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
