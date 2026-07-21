<?php

declare(strict_types=1);

add_theme_filter('body_class', static function (string $classes, array $context): string {
    return trim($classes . ' theme-starter');
});

add_theme_action('head', static function (array $context): string {
    return '<meta name="theme-color" content="#080e14">' . "\n";
});
