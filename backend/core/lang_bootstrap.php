<?php

use Caramagnols\Http\Request;
use Caramagnols\I18n\LanguageResolver;
use Caramagnols\Security\Cookies;

function site_available_languages(): array
{
    return ['fr', 'en', 'de'];
}

function persist_language_cookie(string $lang): void
{
    $_COOKIE['lang'] = $lang;

    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }

    setcookie('lang', $lang, array_merge(Cookies::secureOptions(), [
        'expires' => time() + 365 * 24 * 3600,
    ]));
}

function bootstrap_language_context(?Request $request = null): string
{
    static $resolved = null;

    if (is_string($resolved) && $resolved !== '') {
        return $resolved;
    }

    $request ??= Request::fromGlobals();

    $resolver = new LanguageResolver(
        site_available_languages(),
        defined('DEFAULT_LANG') ? DEFAULT_LANG : 'fr'
    );

    $lang = $resolver->resolve($request);
    persist_language_cookie($lang);

    if (!defined('CURRENT_LANG')) {
        define('CURRENT_LANG', $lang);
    }

    $GLOBALS['langTranslations'] = load_translations_cached($lang);
    $resolved = $lang;

    return $lang;
}

bootstrap_language_context();
