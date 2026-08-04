<?php

declare(strict_types=1);

namespace Caramagnols\Cron;

final class CronScriptPolicy
{
    /**
     * @return array<int, string>
     */
    public static function allowedScripts(string $rootPath): array
    {
        $scripts = array_merge(self::defaultScripts(), self::configuredScripts());
        $allowed = [];

        foreach ($scripts as $script) {
            $normalized = self::normalizeScriptPath($rootPath, $script);
            if ($normalized === null) {
                continue;
            }

            $allowed[$normalized] = $normalized;
        }

        ksort($allowed);

        return array_values($allowed);
    }

    public static function isAllowed(string $rootPath, string $scriptPath): bool
    {
        $normalized = self::normalizeScriptPath($rootPath, $scriptPath);
        if ($normalized === null) {
            return false;
        }

        return in_array($normalized, self::allowedScripts($rootPath), true);
    }

    /**
     * @return array<int, string>
     */
    private static function defaultScripts(): array
    {
        return [
            'core/tools/publish_scheduled_blog_articles.php',
            'core/tools/backup_production.php',
            'core/tools/check_log_alerts.php',
            'core/tools/purge_sql_logs.php',
            'core/tools/purge_private_account_deletion_backups.php',
            'core/tools/purge_private_discussions.php',
            'core/tools/purge_web_development_previews.php',
            'core/tools/plan_next_blog_article.php',
            'core/tools/generate_search_index.php',
            'core/tools/generate_sitemap.php',
            'core/tools/check_env.php',
            'core/tools/check_instagram_feed.php',
            'core/tools/check_security_headers.php',
            'core/tools/check_vite_assets.php',
            // Document Hub cron jobs
            'core/tools/document_hub_backup.php',
            'core/tools/document_hub_gc.php',
            'core/tools/document_hub_integrity.php',
            'core/tools/document_hub_maintenance.php',
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function configuredScripts(): array
    {
        $raw = trim((string) env('CRON_CENTER_ALLOWED_SCRIPTS', ''));
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($parts) ? array_map('strval', $parts) : [];
    }

    private static function normalizeScriptPath(string $rootPath, string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        if (str_starts_with($path, $rootPath . '/')) {
            $path = substr($path, strlen($rootPath) + 1);
        }

        $path = ltrim($path, '/');
        if (!str_starts_with($path, 'core/tools/') || !str_ends_with($path, '.php') || str_contains($path, '..')) {
            return null;
        }

        if (!is_file($rootPath . '/' . $path)) {
            return null;
        }

        return $path;
    }
}
