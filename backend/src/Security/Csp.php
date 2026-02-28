<?php

declare(strict_types=1);

namespace Caramagnols\Security;

/**
 * Génère et applique une CSP moderne avec nonce par requête.
 * Permet de retirer 'unsafe-inline' et de donner un chemin de migration.
 */
class Csp
{
    private string $nonce;

    public function __construct(?string $nonce = null)
    {
        $this->nonce = $nonce ?? bin2hex(random_bytes(12));
    }

    public function nonce(): string
    {
        return $this->nonce;
    }

    /**
     * Retourne la directive CSP prête à être envoyée en header.
     * Les scripts inline devront recevoir l'attribut nonce="...".
     */
    public function headerValue(bool $allowInlineStyles = true): string
    {
        $styleSrc = $allowInlineStyles ? "'self' 'unsafe-inline'" : "'self'";

        return implode(' ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$this->nonce}'",
            "style-src {$styleSrc}",
            "img-src 'self' data: https: http:",
            "connect-src 'self'",
            "font-src 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
