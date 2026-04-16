<!-- LesCaramagnols -->
<!-- templates/scripts_body_bas.php -->
<script nonce="<?= htmlspecialchars($GLOBALS['csp_nonce'] ?? '') ?>">
document.addEventListener('DOMContentLoaded', () => {
    const lang = "<?= CURRENT_LANG ?>";

    // Ajoute ?lang=xx aux liens internes qui n’en ont pas
    document.querySelectorAll('a[href^="/"]:not([href*="?lang="])').forEach(link => {
        const href = link.getAttribute('href');
        if (!href.startsWith('/search')) { // ne pas polluer le champ recherche
            const sep = href.includes('?') ? '&' : '?';
            link.setAttribute('href', href + sep + 'lang=' + lang);
        }
    });
});
</script>
<?php
$globalHeadMetadataHtml = trim((string) app_config('site.head_metadata_html', ''));
$headProvidesJsonLd = stripos($globalHeadMetadataHtml, 'application/ld+json') !== false;
$schemaOrgName = (string) t('TXT_SCHEMA_ORG_NAME');
$schemaOrgDescription = (string) t('TXT_SCHEMA_ORG_DESCRIPTION');
$schemaPersonName = (string) t('TXT_SCHEMA_PERSON_NAME');
$schemaPersonJobTitle = (string) t('TXT_SCHEMA_PERSON_JOB_TITLE');
$schemaWebsiteName = (string) t('TXT_SCHEMA_WEBSITE_NAME');
?>
<?php if (!$headProvidesJsonLd): ?>
<script type="application/ld+json" nonce="<?= htmlspecialchars($GLOBALS['csp_nonce'] ?? '') ?>">
<?= json_encode(
    [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => 'https://www.lescaramagnols.com/#organization',
                'name' => $schemaOrgName,
                'url' => 'https://www.lescaramagnols.com',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => 'https://www.lescaramagnols.com/assets/images/structure/logo.png',
                    'width' => 816,
                    'height' => 815,
                ],
                'image' => 'https://www.lescaramagnols.com/assets/images/bouger/golfe/montage.jpg',
                'description' => $schemaOrgDescription,
                'email' => 'contact@lescaramagnols.com',
                'sameAs' => [
                    'https://www.facebook.com/lescaramagnols',
                    'https://www.instagram.com/paulineetnoel',
                ],
                'founder' => [
                    '@id' => 'https://www.lescaramagnols.com/#pauline-bergon',
                ],
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => '2738 route de la Mole',
                    'postalCode' => '83310',
                    'addressLocality' => 'Cogolin',
                    'addressCountry' => 'FR',
                ],
                'identifier' => [
                    '@type' => 'PropertyValue',
                    'propertyID' => 'RCS',
                    'value' => '803 935 725',
                ],
            ],
            [
                '@type' => 'Person',
                '@id' => 'https://www.lescaramagnols.com/#pauline-bergon',
                'name' => $schemaPersonName,
                'url' => 'https://www.lescaramagnols.com',
                'jobTitle' => $schemaPersonJobTitle,
                'worksFor' => [
                    '@id' => 'https://www.lescaramagnols.com/#organization',
                ],
            ],
            [
                '@type' => 'WebSite',
                '@id' => 'https://www.lescaramagnols.com/#website',
                'url' => 'https://www.lescaramagnols.com',
                'name' => $schemaWebsiteName,
                'publisher' => [
                    '@id' => 'https://www.lescaramagnols.com/#organization',
                ],
                'inLanguage' => ['fr-FR', 'en', 'de'],
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => 'https://www.lescaramagnols.com/search?q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ],
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
) ?>
</script>
<?php endif; ?>
