<?php

if (!function_exists('app')) {
    function app() {
        return \App\Core\Application::$app;
    }
}