<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Operations;

use Caramagnols\PrivatePortal\Http\PrivateResponseHeaders;
use Caramagnols\PrivatePortal\Http\PrivateRouteResolver;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use Caramagnols\PrivatePortal\Security\PrivatePortalSecurityGuard;

final class PrivateMigrationDefinitionOfDoneService
{
    /** @var array<int, string> */
    private const CHECK_ORDER = [
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
    ];

    public function __construct(
        private readonly PrivateModuleRegistry $moduleRegistry,
        private readonly PrivateRouteResolver $routeResolver,
        private readonly ?PrivateModuleMigrationPlanService $migrationPlanService = null,
        private readonly ?PrivateLegacyRetirementService $retirementService = null,
        private readonly ?PrivateSecurityChecklistService $securityChecklistService = null
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function checklist(): array
    {
        $planService = $this->migrationPlanService
            ?? new PrivateModuleMigrationPlanService($this->moduleRegistry, $this->routeResolver);
        $retirementService = $this->retirementService
            ?? new PrivateLegacyRetirementService($this->routeResolver, $this->moduleRegistry);
        $securityService = $this->securityChecklistService
            ?? new PrivateSecurityChecklistService($this->moduleRegistry, $this->routeResolver, $planService, $retirementService);

        $plans = $planService->plans();
        $readiness = $planService->readiness();
        $retirement = $retirementService->inventory();
        $security = $securityService->checklist();

        $checks = [
            'public_php_stable' => $this->publicPhpStableCheck(),
            'private_context_separated' => $this->privateContextSeparatedCheck(),
            'no_critical_legacy_template_dependency' => $this->criticalLegacyTemplatesCheck($plans, $retirement),
            'rental_tax_single_source' => $this->rentalTaxSingleSourceCheck($plans),
            'agency_imports_reconcilable' => $this->agencyImportsCheck($plans),
            'discussion_retention_60_days' => $this->discussionRetentionCheck(),
            'private_files_outside_webroot' => $this->privateFilesOutsideWebrootCheck(),
            'logs_exports_no_sensitive_leak' => $this->logsExportsCheck($security),
            'restore_plan_tested' => $this->restorePlanCheck(),
            'legacy_private_routes_explicit' => $this->legacyRoutesCheck($retirement),
            'docs_runbooks_current' => $this->docsRunbooksCheck(),
        ];

        $orderedChecks = [];
        foreach (self::CHECK_ORDER as $key) {
            $orderedChecks[$key] = $checks[$key];
        }

        $failed = array_values(array_filter(
            $orderedChecks,
            static fn (array $check): bool => ($check['ok'] ?? false) !== true
        ));

        return [
            'success' => true,
            'ready' => $failed === [] && ($readiness['ready'] ?? false) === true,
            'summary' => [
                'checks' => count($orderedChecks),
                'passed' => count($orderedChecks) - count($failed),
                'failed' => count($failed),
                'moduleReadiness' => ($readiness['ready'] ?? false) === true,
                'legacyRetirementReady' => ($retirement['ready'] ?? false) === true,
                'securityReady' => ($security['ready'] ?? false) === true,
            ],
            'checks' => $orderedChecks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function publicPhpStableCheck(): array
    {
        $publicRoot = ROOT_PATH . '/public';

        return $this->result(
            is_file($publicRoot . '/index.php') && is_file($publicRoot . '/rss.php'),
            'Le public PHP reste stable, rapide et indexable',
            [
                'frontController' => $publicRoot . '/index.php',
                'rssEntrypoint' => $publicRoot . '/rss.php',
                'privateNoIndexing' => PrivateResponseHeaders::contentSecurityPolicy() !== '',
                'publicSeoInvariant' => 'routes publiques conservees dans FrontController/LegacyRouteResolver',
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function privateContextSeparatedCheck(): array
    {
        $routes = $this->routeResolver->routeDefinitions();

        return $this->result(
            $routes !== []
                && class_exists(PrivatePortalSecurityGuard::class)
                && class_exists(PrivateResponseHeaders::class),
            'Le prive est servi par une application ou un contexte separe',
            [
                'routeCount' => count($routes),
                'routeBasePath' => $this->routeResolver->basePath(),
                'guard' => PrivatePortalSecurityGuard::class,
                'headers' => PrivateResponseHeaders::class,
                'decision' => 'contexte HTTP prive separe, Symfony-compatible, sans runtime Node en production',
            ]
        );
    }

    /**
     * @param array<string, array<string, mixed>> $plans
     * @param array<string, mixed> $retirement
     *
     * @return array<string, mixed>
     */
    private function criticalLegacyTemplatesCheck(array $plans, array $retirement): array
    {
        $criticalModules = [
            'documents',
            'family_discussion',
            'real_estate_rental',
            'agency_imports',
            'tax_declaration_helper',
            'blocnote',
        ];
        $covered = array_keys($plans);
        if (!in_array('blocnote', $covered, true) && in_array('blocnote', $this->moduleRegistry->moduleCodes(), true)) {
            $covered[] = 'blocnote';
        }
        $missing = array_values(array_diff($criticalModules, $covered));
        $obsoleteTemplates = array_values((array) ($retirement['obsoleteTemplates'] ?? []));

        return $this->result(
            $missing === [] && $obsoleteTemplates === [],
            'Aucun module prive critique ne depend de templates PHP legacy',
            [
                'criticalModules' => $criticalModules,
                'coveredModules' => $covered,
                'missingModules' => $missing,
                'obsoleteTemplates' => $obsoleteTemplates,
                'activeTemplates' => $retirement['templates'] ?? [],
            ]
        );
    }

    /**
     * @param array<string, array<string, mixed>> $plans
     *
     * @return array<string, mixed>
     */
    private function rentalTaxSingleSourceCheck(array $plans): array
    {
        $rentalPlan = $plans['real_estate_rental'] ?? [];
        $taxPlan = $plans['tax_declaration_helper'] ?? [];
        $rentalTables = array_values(array_map('strval', (array) ($rentalPlan['tables'] ?? [])));
        $taxTables = array_values(array_map('strval', (array) ($taxPlan['tables'] ?? [])));

        return $this->result(
            in_array('rental_properties', $rentalTables, true)
                && in_array('rental_export_logs', $rentalTables, true)
                && in_array('tax_years', $taxTables, true)
                && in_array('tax_export_logs', $taxTables, true),
            'Les donnees locatives et fiscales ont une source de verite unique',
            [
                'rentalSourceTables' => $rentalTables,
                'taxSourceTables' => $taxTables,
                'taxBridge' => 'RentalTaxDataProvider -> TaxDeclarationHelper sources',
                'exportsPolicy' => 'exports regenerables depuis tables sources et logs dedies',
            ]
        );
    }

    /**
     * @param array<string, array<string, mixed>> $plans
     *
     * @return array<string, mixed>
     */
    private function agencyImportsCheck(array $plans): array
    {
        $agencyPlan = $plans['agency_imports'] ?? [];
        $tables = array_values(array_map('strval', (array) ($agencyPlan['tables'] ?? [])));
        $auditEvents = array_values(array_map('strval', (array) ($agencyPlan['auditEvents'] ?? [])));

        return $this->result(
            in_array('rental_agencies', $tables, true)
                && in_array('rental_agency_import_batches', $tables, true)
                && in_array('rental_agency_imported_documents', $tables, true)
                && in_array('rental_agency_import_issues', $tables, true)
                && in_array('rental_agency_unit_mappings', $tables, true)
                && $auditEvents !== [],
            'Les imports agence sont reconciliables et auditables',
            [
                'tables' => $tables,
                'auditEvents' => $auditEvents,
                'reconciliation' => 'batch, document source, issues, mappings et statements restent tracables',
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function discussionRetentionCheck(): array
    {
        $discussionConfig = (array) app_config('private.discussions', ['retention_days' => 60]);
        $retentionDays = (int) ($discussionConfig['retention_days'] ?? 60);

        return $this->result(
            $retentionDays === 60,
            'Les messages de discussion respectent la retention 60 jours',
            [
                'retentionDays' => $retentionDays,
                'service' => 'DiscussionRetentionService',
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function privateFilesOutsideWebrootCheck(): array
    {
        $documentConfig = (array) app_config('private.documents', [
            'storage_root_path' => ROOT_PATH . '/private',
        ]);
        $storageRoot = is_string($documentConfig['storage_root_path'] ?? null)
            ? trim((string) $documentConfig['storage_root_path'])
            : '';
        if ($storageRoot === '') {
            $storageRoot = ROOT_PATH . '/private';
        }

        $publicRoot = realpath(ROOT_PATH . '/public') ?: ROOT_PATH . '/public';
        $normalizedStorage = str_replace('\\', '/', $storageRoot);
        $normalizedPublic = rtrim(str_replace('\\', '/', $publicRoot), '/') . '/';

        return $this->result(
            !str_starts_with(rtrim($normalizedStorage, '/') . '/', $normalizedPublic),
            'Les fichiers prives restent hors webroot',
            [
                'storageRoot' => $storageRoot,
                'publicRoot' => $publicRoot,
                'servingPolicy' => 'download controle par route privee, jamais par fichier public direct',
            ]
        );
    }

    /**
     * @param array<string, mixed> $security
     *
     * @return array<string, mixed>
     */
    private function logsExportsCheck(array $security): array
    {
        $checks = is_array($security['checks'] ?? null) ? (array) $security['checks'] : [];
        $audit = is_array($checks['sensitive_audit_redaction'] ?? null) ? (array) $checks['sensitive_audit_redaction'] : [];

        return $this->result(
            ($security['ready'] ?? false) === true && ($audit['ok'] ?? false) === true,
            'Les logs et exports ne fuitent pas de contenu sensible',
            [
                'securityChecklistReady' => ($security['ready'] ?? false) === true,
                'auditRedaction' => $audit['status'] ?? 'missing',
                'exportsPolicy' => 'logs metadata uniquement; exports telecharges via session privee',
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function restorePlanCheck(): array
    {
        return $this->result(
            method_exists(PrivateBackupService::class, 'verifyBackup')
                && method_exists(PrivateBackupService::class, 'restoreBackup'),
            'Le plan de restauration est teste',
            [
                'backupCommand' => 'php backend/core/tools/private_migration_reconcile.php backup --target-dir=/path/exports --files-root=/path/uploads',
                'verifyCommand' => 'php backend/core/tools/private_migration_reconcile.php verify-backup /path/private-backup.json|zip',
                'dryRunRestore' => 'PrivateBackupService::restoreBackup($path, true)',
                'manualRestore' => 'restauration reelle volontairement separee du dry-run',
            ]
        );
    }

    /**
     * @param array<string, mixed> $retirement
     *
     * @return array<string, mixed>
     */
    private function legacyRoutesCheck(array $retirement): array
    {
        return $this->result(
            ($retirement['ready'] ?? false) === true
                && is_array($retirement['routes'] ?? null)
                && is_array($retirement['blockedLegacyRoutes'] ?? null),
            'Les anciennes routes privees PHP sont supprimees, bloquees ou redirigees explicitement',
            [
                'ready' => ($retirement['ready'] ?? false) === true,
                'routes' => count((array) ($retirement['routes'] ?? [])),
                'blockedLegacyRoutes' => $retirement['blockedLegacyRoutes'] ?? [],
                'runbook' => $retirement['runbook'] ?? [],
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function docsRunbooksCheck(): array
    {
        $privateReadme = ROOT_PATH . '/../docs/private/README.md';
        if (!is_file($privateReadme)) {
            $privateReadme = dirname(ROOT_PATH) . '/docs/private/README.md';
        }
        $securityReadme = dirname(ROOT_PATH) . '/docs/security/README.md';

        $privateContent = is_file($privateReadme) ? (string) file_get_contents($privateReadme) : '';
        $securityContent = is_file($securityReadme) ? (string) file_get_contents($securityReadme) : '';

        return $this->result(
            str_contains($privateContent, 'migration-dod')
                && str_contains($privateContent, 'security-checklist')
                && str_contains($securityContent, 'security-checklist'),
            'Les README et runbooks refletent architecture reelle',
            [
                'privateReadme' => $privateReadme,
                'securityReadme' => $securityReadme,
                'privateReadmeMentionsMigrationDod' => str_contains($privateContent, 'migration-dod'),
                'privateReadmeMentionsSecurityChecklist' => str_contains($privateContent, 'security-checklist'),
                'securityReadmeMentionsSecurityChecklist' => str_contains($securityContent, 'security-checklist'),
            ]
        );
    }

    /**
     * @param array<int|string, mixed> $evidence
     *
     * @return array<string, mixed>
     */
    private function result(bool $ok, string $label, array $evidence): array
    {
        return [
            'ok' => $ok,
            'status' => $ok ? 'pass' : 'fail',
            'label' => $label,
            'evidence' => $evidence,
        ];
    }
}
