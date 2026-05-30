<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\Support;

use Caramagnols\Database\DatabaseConfig;
use Caramagnols\Database\EditorialDatabase;
use Caramagnols\Database\EditorialSchemaManager;

trait EditorialSqlTestTrait
{
    private ?EditorialDatabase $editorialSqlDatabase = null;
    private string $editorialSqlPrefix = '';

    protected function editorialSqlDatabase(): EditorialDatabase
    {
        if ($this->editorialSqlDatabase instanceof EditorialDatabase) {
            return $this->editorialSqlDatabase;
        }

        $databaseConfig = (array) \app_config('database', []);
        if ($databaseConfig === []) {
            $overridePath = ROOT_PATH . '/config/database.override.php';
            if (file_exists($overridePath)) {
                $override = require $overridePath;
                $databaseConfig = is_array($override) ? $override : [];
            }
        }

        if ($databaseConfig === []) {
            $databaseConfig = [
                'host' => \env('DB_HOST', '127.0.0.1'),
                'port' => \env('DB_PORT', 3306),
                'name' => \env('DB_NAME', ''),
                'user' => \env('DB_USER', ''),
                'password' => \env('DB_PASSWORD', ''),
                'charset' => \env('DB_CHARSET', 'utf8mb4'),
            ];
        }

        $config = DatabaseConfig::fromArray($databaseConfig);
        if (!$config->isConfigured()) {
            $this->markTestSkipped('Configuration SQL absente pour les tests éditoriaux.');
        }

        try {
            $this->editorialSqlPrefix = 'test_' . bin2hex(random_bytes(6)) . '_';
            $this->editorialSqlDatabase = new EditorialDatabase(
                $config,
                $this->editorialSqlPrefix,
                new EditorialSchemaManager($this->editorialSqlPrefix)
            );
            $this->editorialSqlDatabase->ensureReady();
        } catch (\Throwable $exception) {
            $this->markTestSkipped('Base SQL indisponible pour les tests éditoriaux : ' . $exception->getMessage());
        }

        return $this->editorialSqlDatabase;
    }

    protected function cleanupEditorialSqlDatabase(): void
    {
        if (!$this->editorialSqlDatabase instanceof EditorialDatabase || $this->editorialSqlPrefix === '') {
            return;
        }

        try {
            $pdo = $this->editorialSqlDatabase->pdo();
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

            foreach (
                [
                    'cron_runs',
                    'cron_jobs',
                    'cron_scheduler_state',
                    'tax_export_logs',
                    'tax_summary_lines',
                    'tax_annual_summaries',
                    'tax_manual_income_entries',
                    'tax_source_activations',
                    'tax_income_sources',
                    'tax_years',
                    'discussion_retention_runs',
                    'discussion_conversation_keys',
                    'discussion_crypto_devices',
                    'discussion_message_reads',
                    'discussion_message_attachments',
                    'discussion_messages',
                    'discussion_conversation_members',
                    'discussion_conversations',
                    'rental_agency_import_issues',
                    'rental_agency_statement_lines',
                    'rental_agency_statements',
                    'rental_agency_imported_documents',
                    'rental_agency_import_batches',
                    'rental_agency_line_mappings',
                    'rental_export_logs',
                    'rental_documents',
                    'rental_expenses',
                    'rental_payments',
                    'rental_rents',
                    'rental_leases',
                    'rental_tenants',
                    'rental_property_members',
                    'rental_units',
                    'rental_properties',
                    'private_blocnote_notes',
                    'private_blocnote_categories',
                    'private_module_migrations',
                    'private_mfa_backup_codes',
                    'private_sessions',
                    'private_user_module_permissions',
                    'private_modules',
                    'private_documents',
                    'private_document_categories',
                    'private_password_resets',
                    'private_user_invites',
                    'private_users',
                    'blog_discussions',
                    'blog_articles',
                    'log_entries',
                    'navigation_items',
                    'navigation_sets',
                    'page_tile_item_overrides',
                    'page_tile_placements',
                    'page_translation_sections',
                    'page_translations',
                    'page_sections',
                    'pages',
                    'tile_group_item_translations',
                    'tile_group_items',
                    'tile_groups',
                    'editorial_schema_meta',
                ] as $table
            ) {
                $pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $this->editorialSqlPrefix . $table));
            }

            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        } catch (\Throwable) {
            // Les tests ne doivent pas échouer sur le nettoyage.
        }

        $this->editorialSqlDatabase = null;
        $this->editorialSqlPrefix = '';
    }
}
