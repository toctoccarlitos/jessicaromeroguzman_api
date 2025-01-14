<?php

if (!function_exists('app')) {
    function app() {
        return \App\Core\Application::$app;
    }
}

if (!function_exists('logger')) {
    function logger(): \App\Service\Logger\AppLogger {
        return \App\Service\Logger\AppLogger::getInstance();
    }
}

if (!function_exists('security')) {
    function security(): \App\Service\SecurityService {
        return $GLOBALS['securityService'];
    }
}