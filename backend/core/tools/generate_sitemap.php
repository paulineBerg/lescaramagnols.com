<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Caramagnols\Feed\SitemapService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit etre executee en CLI.\n");
    exit(1);
}

$options = parse_cli_options(array_slice($argv, 1));
$writeToStdout = isset($options['stdout']);
$output = trim((string) ($options['output'] ?? 'public/sitemap.xml'));
$baseUrl = trim((string) ($options['base-url'] ?? detect_base_url_from_config()));

if ($baseUrl === '' || preg_match('#^https?://#i', $baseUrl) !== 1) {
    fwrite(
        STDERR,
        "Base URL invalide. Renseigne `site.url.domain`/`site.url.ssl_domain` "
        . "ou passe --base-url=https://www.exemple.tld\n"
    );
    exit(2);
}

$baseUrl = rtrim($baseUrl, '/');

$service = new SitemapService(
    page_repository(pages_data_path()),
    blog_repository(),
    $baseUrl,
    site_available_languages(),
    (string) app_config('default_lang', 'fr')
);

$xml = $service->render();
$urlCount = substr_count($xml, '<url>');

if ($writeToStdout) {
    fwrite(STDOUT, $xml);
}

if (!$writeToStdout || $output !== '') {
    if ($output === '') {
        fwrite(STDERR, "Le chemin de sortie est vide. Utilise --output=public/sitemap.xml\n");
        exit(2);
    }

    $outputPath = resolve_output_path($output);
    $outputDir = dirname($outputPath);

    if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
        fwrite(STDERR, sprintf("Impossible de creer le dossier de sortie: %s\n", $outputDir));
        exit(2);
    }

    $tmpFile = $outputPath . '.tmp-' . bin2hex(random_bytes(6));
    if (file_put_contents($tmpFile, $xml) === false) {
        fwrite(STDERR, sprintf("Impossible d'ecrire le fichier temporaire: %s\n", $tmpFile));
        exit(2);
    }

    if (!rename($tmpFile, $outputPath)) {
        @unlink($tmpFile);
        fwrite(STDERR, sprintf("Impossible de finaliser le fichier sitemap: %s\n", $outputPath));
        exit(2);
    }

    @chmod($outputPath, 0644);
    fwrite(
        STDOUT,
        sprintf("Sitemap genere: %s (%d urls)\n", $outputPath, $urlCount)
    );
}

exit(0);

/**
 * @param array<int, string> $arguments
 * @return array<string, string|true>
 */
function parse_cli_options(array $arguments): array
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

function detect_base_url_from_config(): string
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
    if (preg_match('#^https?://#i', $fromHelper) === 1) {
        return $fromHelper;
    }

    $fromEnv = trim((string) env('BASE_URL', ''));
    if ($fromEnv !== '' && preg_match('#^https?://#i', $fromEnv) === 1) {
        return $fromEnv;
    }

    return '';
}

function resolve_output_path(string $output): string
{
    if (str_starts_with($output, '/')) {
        return $output;
    }

    return rtrim(ROOT_PATH, '/') . '/' . ltrim($output, '/');
}
