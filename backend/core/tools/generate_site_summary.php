<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Caramagnols\Content\PageRepository;
use Caramagnols\Content\StructuredPageRenderer;
use Caramagnols\Feed\SitemapEntryCollector;
use Caramagnols\Feed\SiteSummaryService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit etre executee en CLI.\n");
    exit(1);
}

$options = site_summary_parse_cli_options(array_slice($argv, 1));
$pageSlug = trim((string) ($options['page-slug'] ?? 'accueil-le-plan-du-site-des-caramagnols'));
$region = trim((string) ($options['region'] ?? 'body'));
$storageOption = strtolower(trim((string) ($options['storage'] ?? 'active')));
$baseUrl = trim((string) ($options['base-url'] ?? site_summary_detect_base_url_from_config()));
$dryRun = isset($options['dry-run']);
$writeToStdout = isset($options['stdout']);
$availableLanguages = site_available_languages();
$defaultLanguage = (string) app_config('default_lang', 'fr');
$targetLanguages = site_summary_target_languages($options, $availableLanguages);

if ($pageSlug === '') {
    fwrite(STDERR, "Le slug de page est vide. Utilise --page-slug=accueil-le-plan-du-site-des-caramagnols\n");
    exit(2);
}

if ($region === '') {
    fwrite(STDERR, "La region cible est vide. Utilise --region=body\n");
    exit(2);
}

if (!in_array($storageOption, ['active', 'json', 'sql', 'dual-write'], true)) {
    fwrite(STDERR, "Stockage invalide. Valeurs acceptees: active, json, sql, dual-write.\n");
    exit(2);
}

if ($targetLanguages === []) {
    fwrite(STDERR, "Aucune langue cible valide.\n");
    exit(2);
}

$storageMode = $storageOption === 'active' ? null : $storageOption;
$pageRepository = new PageRepository(pages_data_path(), new StructuredPageRenderer(), $storageMode);
$page = $pageRepository->findBySlug($pageSlug);

if (!is_array($page)) {
    fwrite(STDERR, sprintf("Page sommaire introuvable: %s\n", $pageSlug));
    exit(2);
}

$collector = new SitemapEntryCollector(
    $pageRepository,
    blog_repository(),
    $baseUrl !== '' ? $baseUrl : '/',
    $availableLanguages,
    $defaultLanguage
);
$summaryService = new SiteSummaryService($collector, $availableLanguages, $defaultLanguage);
$htmlByLanguage = [];

foreach ($targetLanguages as $language) {
    $html = $summaryService->render($language);
    $htmlByLanguage[$language] = $html;

    if (!$dryRun) {
        $page = site_summary_apply_html_to_page($page, $language, $region, $html);
    }
}

if ($writeToStdout) {
    foreach ($htmlByLanguage as $language => $html) {
        fwrite(STDOUT, sprintf("----- %s -----\n%s\n", strtoupper((string) $language), $html));
    }
}

if ($dryRun) {
    fwrite(
        STDOUT,
        sprintf(
            "Sommaire genere en simulation pour %s (%s).\n",
            $pageSlug,
            implode(', ', array_map('strtoupper', $targetLanguages))
        )
    );
    exit(0);
}

if (!$pageRepository->savePage($page, $pageSlug)) {
    fwrite(STDERR, sprintf("Impossible de sauvegarder la page sommaire: %s\n", $pageSlug));
    exit(2);
}

pages_cache_clear();
if (function_exists('app_runtime_cache_clear')) {
    app_runtime_cache_clear(['pages']);
}

fwrite(
    STDOUT,
    sprintf(
        "Sommaire mis a jour dans %s, region %s, langues %s.\n",
        $pageSlug,
        $region,
        implode(', ', array_map('strtoupper', $targetLanguages))
    )
);

exit(0);

/**
 * @param array<int, string> $arguments
 * @return array<string, string|true>
 */
function site_summary_parse_cli_options(array $arguments): array
{
    $options = [];

    foreach ($arguments as $argument) {
        if (!is_string($argument) || !str_starts_with($argument, '--')) {
            continue;
        }

        $parts = explode('=', substr($argument, 2), 2);
        if (!isset($parts[1])) {
            $options[$parts[0]] = true;
            continue;
        }

        $options[$parts[0]] = $parts[1];
    }

    return $options;
}

/**
 * @param array<string, string|true> $options
 * @param array<int, string> $availableLanguages
 * @return array<int, string>
 */
function site_summary_target_languages(array $options, array $availableLanguages): array
{
    $available = array_values(array_filter(
        array_map(static fn (mixed $language): string => is_string($language) ? strtolower(trim($language)) : '', $availableLanguages),
        static fn (string $language): bool => $language !== ''
    ));

    $languageOption = $options['lang'] ?? $options['language'] ?? null;
    if (!is_string($languageOption) || trim($languageOption) === '') {
        return $available;
    }

    $requested = array_values(array_filter(
        array_map(static fn (string $language): string => strtolower(trim($language)), explode(',', $languageOption)),
        static fn (string $language): bool => $language !== ''
    ));

    return array_values(array_intersect($available, $requested));
}

/**
 * @param array<string, mixed> $page
 * @return array<string, mixed>
 */
function site_summary_apply_html_to_page(array $page, string $language, string $region, string $html): array
{
    $translations = is_array($page['translations'] ?? null) ? $page['translations'] : [];
    $translation = is_array($translations[$language] ?? null) ? $translations[$language] : [];
    $regions = is_array($translation['regions'] ?? null) ? $translation['regions'] : [];
    $existingRegion = is_array($regions[$region] ?? null) ? $regions[$region] : [];

    $regions[$region] = array_merge($existingRegion, [
        'component' => 'rich_text',
        'html' => $html,
    ]);

    $translation['regions'] = $regions;
    if (trim((string) ($translation['title'] ?? '')) === '') {
        $translation['title'] = site_summary_default_title($language);
    }

    $translations[$language] = $translation;
    $page['translations'] = $translations;

    return $page;
}

function site_summary_default_title(string $language): string
{
    return match (strtolower(trim($language))) {
        'en' => 'Sitemap',
        'de' => 'Sitemap',
        default => 'Sommaire',
    };
}

function site_summary_detect_base_url_from_config(): string
{
    $siteUrl = app_config('site.url', []);
    $siteUrl = is_array($siteUrl) ? $siteUrl : [];

    $sslDomain = trim((string) ($siteUrl['ssl_domain'] ?? ''));
    $domain = trim((string) ($siteUrl['domain'] ?? ''));
    $basePath = normalize_public_route((string) ($siteUrl['base_path'] ?? '/')) ?? '/';

    $host = $sslDomain !== '' ? $sslDomain : $domain;
    if ($host !== '') {
        return 'https://' . $host . ($basePath === '/' ? '' : $basePath);
    }

    $fromHelper = app_base_url();
    if ($fromHelper !== '') {
        return $fromHelper;
    }

    return '/';
}
