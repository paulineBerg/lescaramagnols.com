<?php
// core/router.php

require_once ROOT_PATH . '/core/content/pages_loader.php';

use Caramagnols\Http\LegacyRouteResolver;

function route_resolver(): LegacyRouteResolver
{
    static $resolver = null;
    static $state = null;

    $availableLanguages = function_exists('site_available_languages')
        ? site_available_languages()
        : ['fr', 'en', 'de'];
    $availableLanguages = array_values(array_filter(
        array_map(static fn ($language): string => strtolower(trim((string) $language)), $availableLanguages),
        static fn (string $language): bool => $language !== ''
    ));
    if ($availableLanguages === []) {
        $availableLanguages = ['fr', 'en', 'de'];
    }

    $currentState = [
        'pages_path' => pages_data_path(),
        'blog_dir' => blog_data_dir(),
        'blog_storage' => blog_storage_mode(),
        'default_lang' => (string) app_config('default_lang', 'fr'),
        'available_languages' => implode('|', $availableLanguages),
    ];

    if (!$resolver instanceof LegacyRouteResolver || $state !== $currentState) {
        $resolver = new LegacyRouteResolver(
            page_repository_for_path($currentState['pages_path']),
            blog_repository(),
            $availableLanguages,
            $currentState['default_lang']
        );
        $state = $currentState;
    }

    return $resolver;
}

function resolve_route(string $uri): string
{
    return route_resolver()->resolve($uri);
}
