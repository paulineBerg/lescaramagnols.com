<?php

declare(strict_types=1);

namespace Caramagnols\Tests\PrivatePortal;

use Caramagnols\PrivatePortal\Http\PrivateRouteResolver;
use Caramagnols\PrivatePortal\Operations\PrivateMigrationDefinitionOfDoneService;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use PHPUnit\Framework\TestCase;

final class PrivateMigrationDefinitionOfDoneTest extends TestCase
{
    public function testDefinitionOfDoneIsReadyAndCoversMigrationCriteria(): void
    {
        $service = new PrivateMigrationDefinitionOfDoneService(
            new PrivateModuleRegistry(),
            new PrivateRouteResolver('private-test')
        );

        $checklist = $service->checklist();
        $failed = array_filter(
            $checklist['checks'],
            static fn (array $check): bool => ($check['ok'] ?? false) !== true
        );

        self::assertTrue($checklist['success']);
        self::assertSame([], $failed, json_encode($failed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        self::assertTrue($checklist['ready']);
        self::assertSame(11, $checklist['summary']['checks']);
        self::assertSame([
            'public_php_stable',
            'private_context_separated',
            'no_critical_legacy_template_dependency',
            'rental_tax_single_source',
            'agency_imports_reconcilable',
            'discussion_retention_60_days',
            'private_files_outside_webroot',
            'logs_exports_no_sensitive_leak',
            'restore_plan_tested',
            'legacy_private_routes_explicit',
            'docs_runbooks_current',
        ], array_keys($checklist['checks']));
    }

    public function testDefinitionOfDoneFailsWhenDiscussionRetentionIsNotSixtyDays(): void
    {
        global $appConfig;

        $previousPrivateConfig = is_array($appConfig['private'] ?? null) ? $appConfig['private'] : null;
        $appConfig['private']['discussions']['retention_days'] = 30;

        try {
            $service = new PrivateMigrationDefinitionOfDoneService(
                new PrivateModuleRegistry(),
                new PrivateRouteResolver('private-test')
            );
            $checklist = $service->checklist();

            self::assertFalse($checklist['ready']);
            self::assertFalse($checklist['checks']['discussion_retention_60_days']['ok']);
            self::assertSame(30, $checklist['checks']['discussion_retention_60_days']['evidence']['retentionDays']);
        } finally {
            if ($previousPrivateConfig === null) {
                unset($appConfig['private']);
            } else {
                $appConfig['private'] = $previousPrivateConfig;
            }
        }
    }
}
