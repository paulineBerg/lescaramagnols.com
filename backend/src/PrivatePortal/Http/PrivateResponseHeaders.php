<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Http;

use Caramagnols\Http\Response;

final class PrivateResponseHeaders
{
    public static function apply(Response $response): Response
    {
        $response->headers['X-Robots-Tag'] = 'noindex, nofollow, noarchive';
        $response->headers['Cache-Control'] = 'private, no-store, no-cache, must-revalidate';
        $response->headers['Pragma'] = 'no-cache';
        $response->headers['Expires'] = '0';
        $response->headers['X-Frame-Options'] = 'DENY';
        $response->headers['X-Content-Type-Options'] = 'nosniff';
        $response->headers['Referrer-Policy'] = 'no-referrer';
        $response->headers['Permissions-Policy'] = 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), browsing-topics=()';
        $response->headers['Content-Security-Policy'] = self::contentSecurityPolicy();

        if (!isset($response->headers['Content-Type'])) {
            $response->headers['Content-Type'] = 'text/html; charset=UTF-8';
        }

        return $response;
    }

    public static function contentSecurityPolicy(): string
    {
        $nonce = is_string($GLOBALS['csp_nonce'] ?? null) ? (string) $GLOBALS['csp_nonce'] : '';
        $scriptSrc = $nonce !== '' ? "'self' 'nonce-{$nonce}'" : "'self'";

        return "default-src 'self'; script-src {$scriptSrc}; style-src 'self'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; media-src 'self' blob:; frame-src 'none'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none';";
    }
}
