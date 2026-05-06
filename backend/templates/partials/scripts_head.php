<?php
if (!defined('CARAMAGNOLS_TITLE_TAG_RENDERED')) {
    define('CARAMAGNOLS_TITLE_TAG_RENDERED', true);
}
?>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars((string) ($pageTitle ?? t('TXT_PAGE_DEFAULT_TITLE')), ENT_QUOTES, 'UTF-8') ?></title>
  <?php
  $pageMetaDescriptionValue = trim((string) ($pageMetaDescription ?? ''));
  if ($pageMetaDescriptionValue === '') {
      $fallbackDescription = function_exists('t') ? trim((string) t('TXT_SCHEMA_ORG_DESCRIPTION')) : '';
      if ($fallbackDescription !== '' && !str_starts_with($fallbackDescription, '[[')) {
          $pageMetaDescriptionValue = $fallbackDescription;
      }
  }
  ?>
  <?php if ($pageMetaDescriptionValue !== ''): ?>
  <meta name="description" content="<?= htmlspecialchars($pageMetaDescriptionValue, ENT_QUOTES, 'UTF-8') ?>" />
  <?php endif; ?>
  <?php $pageRobotsValue = trim((string) ($pageRobots ?? '')); ?>
  <?php if ($pageRobotsValue !== ''): ?>
  <meta name="robots" content="<?= htmlspecialchars($pageRobotsValue, ENT_QUOTES, 'UTF-8') ?>" />
  <?php endif; ?>
  <?php
  $pageMetaImageWidthValue = is_numeric($pageMetaImageWidth ?? null) ? (int) $pageMetaImageWidth : null;
  $pageMetaImageHeightValue = is_numeric($pageMetaImageHeight ?? null) ? (int) $pageMetaImageHeight : null;
  $hasPageSocialImage = !empty($pageMetaImage);
  $pageMetaImageType = '';
  if ($hasPageSocialImage) {
      $imagePath = parse_url((string) $pageMetaImage, PHP_URL_PATH);
      if (is_string($imagePath)) {
          $imageExtension = strtolower((string) pathinfo($imagePath, PATHINFO_EXTENSION));
          $pageMetaImageType = match ($imageExtension) {
              'jpg', 'jpeg' => 'image/jpeg',
              'png' => 'image/png',
              'webp' => 'image/webp',
              'gif' => 'image/gif',
              'avif' => 'image/avif',
              default => '',
          };
      }
  }
  ?>
  <?php
  $pageCanonicalValue = trim((string) ($pageCanonicalUrl ?? ''));
  if ($pageCanonicalValue === '') {
      $pageCanonicalValue = trim((string) ($GLOBALS['pageCanonicalUrl'] ?? ''));
  }
  if ($pageCanonicalValue !== '') {
      $pageCanonicalValue = \Caramagnols\Seo\SeoUrlNormalizer::withoutFragment($pageCanonicalValue);
  }
  ?>
  <?php if ($pageCanonicalValue !== ''): ?>
  <link rel="canonical" href="<?= htmlspecialchars($pageCanonicalValue, ENT_QUOTES, 'UTF-8') ?>" />
  <?php endif; ?>
  <?php if (!empty($pageMetaImage)): ?>
  <meta property="og:image" content="<?= htmlspecialchars((string) $pageMetaImage, ENT_QUOTES, 'UTF-8') ?>" />
  <meta property="og:image:secure_url" content="<?= htmlspecialchars((string) $pageMetaImage, ENT_QUOTES, 'UTF-8') ?>" />
  <?php if (!empty($pageMetaImageAlt)): ?>
  <meta property="og:image:alt" content="<?= htmlspecialchars((string) $pageMetaImageAlt, ENT_QUOTES, 'UTF-8') ?>" />
  <?php endif; ?>
  <?php if ($pageMetaImageType !== ''): ?>
  <meta property="og:image:type" content="<?= htmlspecialchars($pageMetaImageType, ENT_QUOTES, 'UTF-8') ?>" />
  <?php endif; ?>
  <?php if (is_int($pageMetaImageWidthValue) && $pageMetaImageWidthValue > 0): ?>
  <meta property="og:image:width" content="<?= htmlspecialchars((string) $pageMetaImageWidthValue, ENT_QUOTES, 'UTF-8') ?>" />
  <?php endif; ?>
  <?php if (is_int($pageMetaImageHeightValue) && $pageMetaImageHeightValue > 0): ?>
  <meta property="og:image:height" content="<?= htmlspecialchars((string) $pageMetaImageHeightValue, ENT_QUOTES, 'UTF-8') ?>" />
  <?php endif; ?>
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:image" content="<?= htmlspecialchars((string) $pageMetaImage, ENT_QUOTES, 'UTF-8') ?>" />
  <?php if (!empty($pageMetaImageAlt)): ?>
  <meta name="twitter:image:alt" content="<?= htmlspecialchars((string) $pageMetaImageAlt, ENT_QUOTES, 'UTF-8') ?>" />
  <?php endif; ?>
  <?php endif; ?>
  <link rel="icon" href="/assets/images/structure/favicon.ico" />
  <?php $globalHeadMetadataHtml = trim((string) app_config('site.head_metadata_html', '')); ?>
  <?php
  $globalHeadMetadataOutput = $globalHeadMetadataHtml;
  if ($pageRobotsValue !== '' && $globalHeadMetadataOutput !== '') {
      $withoutGlobalRobots = preg_replace('/<meta\s+[^>]*name=["\']robots["\'][^>]*>\s*/i', '', $globalHeadMetadataOutput);
      if (is_string($withoutGlobalRobots)) {
          $globalHeadMetadataOutput = trim($withoutGlobalRobots);
      }
  }
  if ($hasPageSocialImage && $globalHeadMetadataOutput !== '') {
      $withoutGlobalSocialImage = preg_replace(
          '/\s*<meta\b(?=[^>]*\s(?:name|property)\s*=\s*["\'](?:og:image(?::[^"\']*)?|twitter:image(?::[^"\']*)?|twitter:card)["\'])[^>]*>\s*/i',
          '',
          $globalHeadMetadataOutput
      );
      if (is_string($withoutGlobalSocialImage)) {
          $globalHeadMetadataOutput = trim($withoutGlobalSocialImage);
      }
  }
  if ($globalHeadMetadataOutput !== '') {
      $globalHeadMetadataOutput = \Caramagnols\Seo\StructuredDataRenderer::stripFragmentedJsonLdScripts(
          $globalHeadMetadataOutput
      );
  }
  ?>
  <?php $tarteaucitronSettings = is_array(app_config('site.tarteaucitron', [])) ? app_config('site.tarteaucitron', []) : []; ?>
  <?php $discussionSettings = is_array(app_config('site.discussions', [])) ? app_config('site.discussions', []) : []; ?>
  <?php $discussionRecaptcha = is_array($discussionSettings['recaptcha'] ?? null) ? $discussionSettings['recaptcha'] : []; ?>
  <?php $discussionHasFormOnPage = !empty($GLOBALS['currentPageHasDiscussionForm']); ?>
  <?php $discussionRecaptchaEnabled = !empty($discussionRecaptcha['enabled']) && trim((string) ($discussionRecaptcha['site_key'] ?? '')) !== '' && $discussionHasFormOnPage; ?>
  <?php $discussionRecaptchaMode = \Caramagnols\Blog\DiscussionRecaptchaMode::normalize($discussionRecaptcha['mode'] ?? null); ?>
  <?php if ($discussionRecaptchaEnabled): ?>
  <?php
  $configuredServices = array_values(array_filter(
      is_array($tarteaucitronSettings['services'] ?? null) ? $tarteaucitronSettings['services'] : [],
      static fn ($service): bool => is_string($service) && trim($service) !== ''
  ));
  if (!in_array('recaptcha', array_map('strtolower', $configuredServices), true)) {
      $configuredServices[] = 'recaptcha';
  }
  $tarteaucitronSettings['services'] = $configuredServices;
  ?>
  <?php endif; ?>
  <?php
  $siteUrlSettings = is_array(app_config('site.url', [])) ? app_config('site.url', []) : [];
  $siteBasePath = normalize_public_route((string) ($siteUrlSettings['base_path'] ?? '/')) ?? '/';
  $langApiPath = ($siteBasePath === '/' ? '' : rtrim($siteBasePath, '/')) . '/core/api/lang.php';
  $runtimeConfig = [
      'tarteaucitron' => $tarteaucitronSettings,
      'discussions' => [
          'enabled' => !empty($discussionSettings['enabled']),
          'has_form' => $discussionHasFormOnPage,
          'require_account' => !empty($discussionSettings['require_account']),
          'recaptcha' => [
              'enabled' => $discussionRecaptchaEnabled,
              'mode' => $discussionRecaptchaMode,
              'site_key' => trim((string) ($discussionRecaptcha['site_key'] ?? '')),
          ],
      ],
      'i18n' => [
          'youtube_title_fallback' => (string) t('TXT_YOUTUBE_VIDEO_FALLBACK_TITLE'),
      ],
      'api' => [
          'lang' => $langApiPath,
      ],
  ];
  ?>
  <?php $cspNonce = (string) ($GLOBALS['csp_nonce'] ?? ''); ?>
  <?php
  $structuredDataArticle = null;
  if (is_array($GLOBALS['currentBlogArticle'] ?? null)) {
      $structuredDataArticle = $GLOBALS['currentBlogArticle'];
  } elseif (is_array($GLOBALS['currentDynamicOpenArticle'] ?? null)) {
      $structuredDataArticle = $GLOBALS['currentDynamicOpenArticle'];
  }
  $structuredDataPage = null;
  if (is_array($GLOBALS['currentDynamicPage'] ?? null)) {
      $structuredDataPage = $GLOBALS['currentDynamicPage'];
  } elseif (is_array($GLOBALS['currentBlogHubPage'] ?? null)) {
      $structuredDataPage = $GLOBALS['currentBlogHubPage'];
  }
  $structuredDataPageKind = is_array($GLOBALS['currentBlogArticles'] ?? null) ? 'blog_index' : 'web_page';
  if (is_array($structuredDataArticle)) {
      $structuredDataPageKind = 'blog_article';
  }
  $structuredDataPayload = \Caramagnols\Seo\StructuredDataBuilder::fromRuntime()->build([
      'title' => (string) ($pageTitle ?? t('TXT_PAGE_DEFAULT_TITLE')),
      'description' => $pageMetaDescriptionValue,
      'canonical_url' => $pageCanonicalValue,
      'language' => defined('CURRENT_LANG') ? (string) CURRENT_LANG : (string) app_config('default_lang', 'fr'),
      'page_kind' => $structuredDataPageKind,
      'page' => $structuredDataPage,
      'article' => $structuredDataArticle,
      'image' => [
          'url' => (string) ($pageMetaImage ?? ''),
          'alt' => (string) ($pageMetaImageAlt ?? ''),
          'width' => $pageMetaImageWidthValue,
          'height' => $pageMetaImageHeightValue,
      ],
  ]);
  $structuredDataScript = \Caramagnols\Seo\StructuredDataRenderer::renderScript($structuredDataPayload, $cspNonce);
  ?>
  <?php if ($globalHeadMetadataOutput !== ''): ?>
  <?php echo $globalHeadMetadataOutput . "\n"; ?>
  <?php endif; ?>
  <?php if ($structuredDataScript !== ''): ?>
  <?php echo $structuredDataScript; ?>
  <?php endif; ?>
  <script<?php echo $cspNonce !== '' ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
    window.caramagnolsRuntime = <?php echo json_encode($runtimeConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  </script>

  <?php if (!empty($tarteaucitronSettings['enabled'])): ?>
  <script src="/tarteaucitron/tarteaucitron.min.js" defer></script>
  <?php endif; ?>

  <?php
  echo vite_tags('src/js/main.ts');
  ?>
