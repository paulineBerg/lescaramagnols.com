<?php
// public/index.php

// Front-controller modernisé : tente FastRoute puis fallback legacy router
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Caramagnols\Http\MiddlewareStack;
use Caramagnols\Http\Request;
use Caramagnols\Http\Response;
use Caramagnols\I18n\LanguageResolver;
use Caramagnols\I18n\Translator;
use FastRoute\Dispatcher;

$request = Request::fromGlobals();

// === Middlewares de base ===
$stack = new MiddlewareStack();

// 1) Langue (définit CURRENT_LANG)
$stack->add(function (Request $req, callable $next) {
    $resolver = new LanguageResolver();
    $lang = $resolver->resolve($req);
    if (!defined('CURRENT_LANG')) {
        define('CURRENT_LANG', $lang);
    }

    $translator = new Translator(ROOT_PATH . '/lang', DEFAULT_LANG);
    $GLOBALS['langTranslations'] = $translator->load($lang);

    return $next($req);
});

// 2) Sécurité (headers + nonce via core/security.php déjà appelé dans bootstrap)

// === Router FastRoute ===
$dispatcher = FastRoute\simpleDispatcher(function (FastRoute\RouteCollector $r) {
    // API de langue (remplace le fichier direct) mais garde fallback
    $r->addRoute('GET', '/core/api/lang.php', ['type' => 'api-lang']);
});

$response = $stack->handle($request, function (Request $req) use ($dispatcher): Response {
    $routeInfo = $dispatcher->dispatch($req->method(), $req->uri());

    if ($routeInfo[0] === Dispatcher::FOUND) {
        $handler = $routeInfo[1];
        if (is_array($handler) && $handler['type'] === 'api-lang') {
            require_once __DIR__ . '/../core/api/lang.php';
            exit; // core/api/lang.php gère l'output
        }
    }

    // Fallback legacy : mapping fichier
    $pageFile = resolve_route($req->uri());
    $pagePath = TEMPLATES_PATH . '/' . $pageFile;

    if (!file_exists($pagePath)) {
        $pagePath = TEMPLATES_PATH . '/pages/404.php';
        $pageTitle = 'Page introuvable';
    } else {
        $pageTitle = ucfirst(str_replace('-', ' ', basename($pageFile, '.php')));
    }

    ob_start();
    include $pagePath;
    $content = ob_get_clean();

    require TEMPLATES_PATH . '/partials/layout.php';
    return new Response();
});

$response->send();
