<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    date_default_timezone_set('Africa/Lagos');

    if (($context['APP_DEBUG'] ?? false) && PHP_SAPI !== 'cli') {
        $currentLimit = (int) ini_get('max_execution_time');
        if ($currentLimit > 0 && $currentLimit < 120) {
            @set_time_limit(120);
        }
    }

    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
