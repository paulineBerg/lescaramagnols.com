<?php

declare(strict_types=1);

namespace Caramagnols\Tests\PrivatePortal;

use Caramagnols\PrivatePortal\Http\PrivateResponseHeaders;
use Caramagnols\PrivatePortal\Http\PrivateRouteResolver;
use Caramagnols\PrivatePortal\Operations\PrivateSecurityChecklistService;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use PHPUnit\Framework\TestCase;

final class PrivateSecurityChecklistTest extends TestCase
{
    public function testChecklistIsReadyAndCoversSecurityItems(): void
    {
        $service = new PrivateSecurityChecklistService(
            new PrivateModuleRegistry(),
            new PrivateRouteResolver('private-test')
        );

        $checklist = $service->checklist();

        self::assertTrue($checklist['success']);
        $failed = array_filter(
            $checklist['checks'],
            static fn (array $check): bool => ($check['ok'] ?? false) !== true
        );
        self::assertSame([], $failed, json_encode($failed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        self::assertTrue($checklist['ready']);
        self::assertSame(19, $checklist['summary']['checks']);

        $expectedKeys = [
            'module_threat_model',
            'strict_input_validation',
            'parameterized_sql',
            'escaped_html_output',
            'csrf_cookie_mutations',
            'private_cookie_policy',
            'csp_policy',
            'rate_limits',
            'document_quarantine',
            'upload_limits',
            'sensitive_audit_redaction',
            'tested_backups',
            'secrets_outside_repository',
            'robots_paths',
            'http_error_coherence',
            'dependency_review',
            'manual_auth_flow',
            'manual_suspended_permission_flow',
            'manual_restore_flow',
        ];
        self::assertSame($expectedKeys, array_keys($checklist['checks']));
    }

    public function testPrivateCspUsesNonceForScriptsAndDocumentsInlineStyleException(): void
    {
        $previous = $GLOBALS['csp_nonce'] ?? null;
        $hadPrevious = array_key_exists('csp_nonce', $GLOBALS);
        $GLOBALS['csp_nonce'] = 'testnonce';

        $policy = PrivateResponseHeaders::contentSecurityPolicy();

        if ($hadPrevious) {
            $GLOBALS['csp_nonce'] = $previous;
        } else {
            unset($GLOBALS['csp_nonce']);
        }

        self::assertStringContainsString("script-src 'self' 'nonce-testnonce'", $policy);
        self::assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $policy);
        self::assertStringContainsString("style-src 'self' 'unsafe-inline'", $policy);
    }

    public function testRobotsDoesNotExposeAdminOrPrivatePaths(): void
    {
        $robotsPath = ROOT_PATH . '/public/robots.txt';
        self::assertFileExists($robotsPath);

        $content = strtolower((string) file_get_contents($robotsPath));
        self::assertStringNotContainsString('/private', $content);
        self::assertStringNotContainsString('espace-admin', $content);
        self::assertStringNotContainsString('admin', $content);
    }
}
