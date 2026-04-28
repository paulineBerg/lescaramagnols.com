<?php

$article = $GLOBALS['currentBlogArticle'] ?? null;

if (!is_array($article)) {
    http_response_code(404);
    require TEMPLATES_PATH . '/pages/404.php';
    return;
}

$defaultLanguage = defined('DEFAULT_LANG') ? DEFAULT_LANG : (string) app_config('default_lang', 'fr');
$publicUrlResolver = new \Caramagnols\Blog\BlogPublicUrlResolver(
    blog_repository(),
    page_repository(pages_data_path()),
    $defaultLanguage
);
$pageTitle = (string) ($article['title'] ?? t('TXT_BLOG_ARTICLE_DEFAULT_TITLE')) . ' · ' . t('TXT_BLOG_PAGE_LABEL');
$pageBodyClass = 'page-blog-article';
$featuredImage = \Caramagnols\Admin\AdminEditorialImageService::sanitizeImageMetadata(
    is_array($article['featured_image'] ?? null) ? $article['featured_image'] : []
);
$toAbsoluteImageUrl = static function (string $src): string {
    if (preg_match('#^https?://#i', $src) === 1) {
        return $src;
    }

    return app_url(ltrim($src, '/'));
};
$pageMetaDescription = trim((string) ($article['excerpt'] ?? ''));
if ($pageMetaDescription === '') {
    $pageMetaDescription = trim(strip_tags((string) ($article['content'] ?? '')));
    $pageMetaDescription = function_exists('mb_substr')
        ? (string) mb_substr($pageMetaDescription, 0, 240)
        : substr($pageMetaDescription, 0, 240);
}
$pageMetaImage = is_array($featuredImage) && trim((string) ($featuredImage['src'] ?? '')) !== ''
    ? $toAbsoluteImageUrl((string) $featuredImage['src'])
    : null;
$pageMetaImageAlt = is_array($featuredImage)
    ? trim((string) ($featuredImage['alt'] ?? ''))
    : '';
if ($pageMetaImageAlt === '') {
    $pageMetaImageAlt = trim((string) ($article['title'] ?? t('TXT_BLOG_ARTICLE_DEFAULT_TITLE')));
}
$parentArticle = is_array($article['parent_article'] ?? null) ? $article['parent_article'] : null;
$childArticles = is_array($article['child_articles'] ?? null) ? $article['child_articles'] : [];
$articleSlug = (string) ($article['slug'] ?? '');
$articleLanguage = (string) ($article['lang'] ?? (defined('CURRENT_LANG') ? CURRENT_LANG : $defaultLanguage));
$articleFallbackPath = $articleSlug !== ''
    ? $publicUrlResolver->fallbackArticlePath($articleSlug, $articleLanguage)
    : $publicUrlResolver->blogIndexPath($articleLanguage, false);
$articlePublicPath = $publicUrlResolver->publicPathForArticle($article) ?? $articleFallbackPath;
$articleAttachedPath = $publicUrlResolver->attachedPathForArticle($article);
$articleLegacyLanguagePrefixedPath = $publicUrlResolver->isDefaultLanguage($articleLanguage)
    ? '/' . rawurlencode($articleLanguage) . $articleFallbackPath
    : $articleFallbackPath;
$pageCanonicalUrl = app_url(ltrim($articlePublicPath, '/'));
$GLOBALS['pageCanonicalUrl'] = $pageCanonicalUrl;
$requestUri = is_string($_SERVER['REQUEST_URI'] ?? null) ? (string) $_SERVER['REQUEST_URI'] : '';
$requestPath = normalize_public_route((string) (parse_url($requestUri, PHP_URL_PATH) ?? $articleFallbackPath)) ?? $articleFallbackPath;
if (
    $articleAttachedPath !== null
    && ($requestPath === $articleFallbackPath || $requestPath === $articleLegacyLanguagePrefixedPath)
) {
    $pageRobots = 'noindex,follow';
}
$blogTaxonomy = \Caramagnols\Blog\BlogTaxonomy::fromDefaultConfig();
$articleCategorySlug = $blogTaxonomy->resolveCategorySlug($article['category'] ?? null);
$articleCategoryLabel = $articleCategorySlug !== null
    ? $blogTaxonomy->categoryLabel($articleCategorySlug, $articleLanguage)
    : trim((string) ($article['category'] ?? ''));
$articleSubcategorySlug = $blogTaxonomy->resolveSubcategorySlug($article['subcategory'] ?? null, $articleCategorySlug);
$articleSubcategoryLabel = $articleSubcategorySlug !== null
    ? $blogTaxonomy->subcategoryLabel($articleSubcategorySlug, $articleLanguage)
    : trim((string) ($article['subcategory'] ?? ''));
$articleTagItems = [];
foreach (is_array($article['tags'] ?? null) ? $article['tags'] : [] as $rawTag) {
    $tagSlug = $blogTaxonomy->resolveTagSlug($rawTag);
    $tagLabel = $tagSlug !== null ? $blogTaxonomy->tagLabel($tagSlug, $articleLanguage) : trim((string) $rawTag);
    if ($tagLabel !== '') {
        $articleTagItems[] = ['slug' => $tagSlug ?? $tagLabel, 'label' => $tagLabel];
    }
}
$renderedArticleContent = \Caramagnols\Http\PublicUrlNormalizer::rewriteHtmlFragment(
    (string) ($article['content'] ?? ''),
    $articlePublicPath
);
$resolveArticleUrl = static function (array $candidate) use ($publicUrlResolver, $articleLanguage): string {
    $slug = trim((string) ($candidate['slug'] ?? ''));
    if ($slug === '') {
        return app_url(ltrim($publicUrlResolver->blogIndexPath($articleLanguage, false), '/'));
    }

    $language = trim((string) ($candidate['lang'] ?? $articleLanguage));
    $path = $publicUrlResolver->publicPathForArticle($candidate)
        ?? $publicUrlResolver->fallbackArticlePath($slug, $language);

    return app_url(ltrim($path, '/'));
};
$slugifyBlogFilterValue = static function (string $value): string {
    $normalized = trim($value);
    if ($normalized === '') {
        return '';
    }

    $transliterated = function_exists('iconv')
        ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized)
        : $normalized;
    if (!is_string($transliterated) || trim($transliterated) === '') {
        $transliterated = $normalized;
    }

    $slug = strtolower(trim($transliterated));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

    return trim($slug, '-');
};
$buildBlogFilterUrl = static function (?string $category = null, ?string $tag = null) use ($articleLanguage, $slugifyBlogFilterValue): string {
    $segments = [trim($articleLanguage, '/'), 'blog'];

    if (is_string($category) && trim($category) !== '') {
        $slug = $slugifyBlogFilterValue($category);
        if ($slug !== '') {
            $segments[] = 'categorie';
            $segments[] = $slug;
        }
    }

    if (is_string($tag) && trim($tag) !== '') {
        $slug = $slugifyBlogFilterValue($tag);
        if ($slug !== '') {
            $segments[] = 'tag';
            $segments[] = $slug;
        }
    }

    $segments = array_values(array_filter($segments, static fn (string $segment): bool => $segment !== ''));

    return app_url(implode('/', $segments));
};
$discussionScope = 'blog_discussion_' . hash('sha256', $articleLanguage . ':' . $articleSlug);
$discussionsEnabled = (bool) app_config('site.discussions.enabled', true);
$discussionRequireAccount = $discussionsEnabled && (bool) app_config('site.discussions.require_account', false);
$discussionRepository = blog_discussion_repository();
$approvedDiscussions = $articleSlug !== ''
    ? $discussionRepository->approvedForArticle($articleSlug, $articleLanguage)
    : [];
$discussionFlash = null;
$discussionOldInput = ['author' => '', 'email' => '', 'content' => ''];
$discussionCsrfToken = '';
$discussionNonce = '';
$discussionSubmitPath = app_url('core/blog/submit_discussion.php');
$excludedRelatedSlugs = [];
if ($parentArticle !== null) {
    $excludedRelatedSlugs[] = (string) ($parentArticle['slug'] ?? '');
}
foreach ($childArticles as $childArticle) {
    $excludedRelatedSlugs[] = (string) ($childArticle['slug'] ?? '');
}
$relatedSuggestions = (new \Caramagnols\Blog\BlogRelatedArticlesService($blogTaxonomy))->suggest(
    $article,
    blog_repository()->publishedArticles($articleLanguage),
    array_values(array_filter($excludedRelatedSlugs, static fn (string $slug): bool => trim($slug) !== '')),
    3
);
$honeypotField = trim((string) app_config('site.discussions.honeypot_field', 'website'));
if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{1,40}$/', $honeypotField) !== 1) {
    $honeypotField = 'website';
}

$recaptchaConfig = app_config('site.discussions.recaptcha', []);
$recaptchaConfig = is_array($recaptchaConfig) ? $recaptchaConfig : [];
$recaptchaSiteKey = trim((string) ($recaptchaConfig['site_key'] ?? ''));
$recaptchaEnabled = $discussionsEnabled
    && (bool) ($recaptchaConfig['enabled'] ?? false)
    && $recaptchaSiteKey !== '';

if ($articleSlug !== '') {
    ensure_session_started();

    $flashBucket = is_array($_SESSION['_blog_discussion_flash'] ?? null)
        ? $_SESSION['_blog_discussion_flash']
        : [];
    $discussionFlash = is_array($flashBucket[$discussionScope] ?? null) ? $flashBucket[$discussionScope] : null;
    unset($_SESSION['_blog_discussion_flash'][$discussionScope]);

    if (is_array($discussionFlash['old'] ?? null)) {
        $discussionOldInput['author'] = trim((string) ($discussionFlash['old']['author'] ?? ''));
        $discussionOldInput['email'] = trim((string) ($discussionFlash['old']['email'] ?? ''));
        $discussionOldInput['content'] = trim((string) ($discussionFlash['old']['content'] ?? ''));
    }

    $discussionCsrfToken = csrf_token($discussionScope);
    $discussionNonce = bin2hex(random_bytes(16));

    if (!is_array($_SESSION['_blog_discussion_form_nonces'] ?? null)) {
        $_SESSION['_blog_discussion_form_nonces'] = [];
    }

    $maxFormAge = max(60, (int) app_config('site.discussions.max_form_age_seconds', 7200));
    $cutoff = time() - $maxFormAge;
    foreach ($_SESSION['_blog_discussion_form_nonces'] as $nonceKey => $noncePayload) {
        if (!is_array($noncePayload) || (int) ($noncePayload['issued_at'] ?? 0) < $cutoff) {
            unset($_SESSION['_blog_discussion_form_nonces'][$nonceKey]);
        }
    }

    $_SESSION['_blog_discussion_form_nonces'][$discussionNonce] = [
        'scope' => $discussionScope,
        'issued_at' => time(),
    ];
}

$formatDiscussionDate = static function (string $value): string {
    $timestamp = strtotime($value);

    return is_int($timestamp) ? date('d/m/Y H:i', $timestamp) : $value;
};

ob_start();
?>
<div class="content-heading">
  <div>
    <p class="content-heading-subtitle"><?php echo htmlspecialchars(t('TXT_BLOG_SITE_TITLE'), ENT_QUOTES, 'UTF-8'); ?></p>
    <h1><?php echo htmlspecialchars((string) ($article['title'] ?? t('TXT_BLOG_ARTICLE_DEFAULT_TITLE')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="content-heading-lead">
      <p>
        <?php echo htmlspecialchars(t('TXT_BLOG_PUBLISHED_ON'), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars((string) ($article['date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
        <?php if (($article['author'] ?? '') !== ''): ?>
          <?php echo htmlspecialchars(t('TXT_BLOG_BY'), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars((string) $article['author'], ENT_QUOTES, 'UTF-8'); ?>
        <?php endif; ?>
      </p>
    </div>
  </div>
  <?php if (is_array($featuredImage) && trim((string) ($featuredImage['src'] ?? '')) !== ''): ?>
  <figure class="content-heading-media blog-featured-media">
    <img
      src="<?php echo htmlspecialchars((string) $featuredImage['src'], ENT_QUOTES, 'UTF-8'); ?>"
      alt="<?php echo htmlspecialchars(trim((string) ($featuredImage['alt'] ?? '')) !== '' ? (string) $featuredImage['alt'] : (string) ($article['title'] ?? t('TXT_BLOG_ARTICLE_DEFAULT_TITLE')), ENT_QUOTES, 'UTF-8'); ?>"
      <?php if (trim((string) ($featuredImage['title'] ?? '')) !== ''): ?>
      title="<?php echo htmlspecialchars((string) $featuredImage['title'], ENT_QUOTES, 'UTF-8'); ?>"
      <?php endif; ?>
      width="<?php echo max(1, min(8192, (int) ($featuredImage['width'] ?? 1200))); ?>"
      height="<?php echo max(1, min(8192, (int) ($featuredImage['height'] ?? 630))); ?>"
      loading="lazy"
      decoding="async"
      fetchpriority="low"
    />
    <?php if (trim((string) ($featuredImage['caption'] ?? '')) !== ''): ?>
    <figcaption><?php echo htmlspecialchars((string) $featuredImage['caption'], ENT_QUOTES, 'UTF-8'); ?></figcaption>
    <?php endif; ?>
  </figure>
  <?php endif; ?>
</div>
<?php
$blocks['EditRegion1'] = ob_get_clean();

ob_start();
?>
<?php if ($parentArticle !== null || $articleCategoryLabel !== '' || $articleSubcategoryLabel !== '' || $articleTagItems !== []): ?>
  <aside class="content-callout">
    <h2 class="content-callout-title"><?php echo htmlspecialchars(t('TXT_BLOG_METADATA'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <dl class="content-facts">
      <?php if ($parentArticle !== null): ?>
        <div class="content-facts-item">
          <dt><?php echo htmlspecialchars(t('TXT_BLOG_PARENT_ARTICLE'), ENT_QUOTES, 'UTF-8'); ?></dt>
          <dd>
            <a href="<?php echo htmlspecialchars($resolveArticleUrl($parentArticle), ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo htmlspecialchars((string) ($parentArticle['title'] ?? ($parentArticle['slug'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
            </a>
          </dd>
        </div>
      <?php endif; ?>
      <?php if ($articleCategoryLabel !== ''): ?>
        <div class="content-facts-item">
          <dt><?php echo htmlspecialchars(t('TXT_BLOG_CATEGORY'), ENT_QUOTES, 'UTF-8'); ?></dt>
          <dd>
            <a class="blog-filter-chip" href="<?php echo htmlspecialchars($buildBlogFilterUrl($articleCategorySlug ?? $articleCategoryLabel, null), ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo htmlspecialchars($articleCategoryLabel, ENT_QUOTES, 'UTF-8'); ?>
            </a>
          </dd>
        </div>
      <?php endif; ?>
      <?php if ($articleSubcategoryLabel !== ''): ?>
        <div class="content-facts-item">
          <dt><?php echo htmlspecialchars(t('TXT_BLOG_SUBCATEGORY'), ENT_QUOTES, 'UTF-8'); ?></dt>
          <dd><?php echo htmlspecialchars($articleSubcategoryLabel, ENT_QUOTES, 'UTF-8'); ?></dd>
        </div>
      <?php endif; ?>
      <?php if ($articleTagItems !== []): ?>
        <div class="content-facts-item">
          <dt><?php echo htmlspecialchars(t('TXT_BLOG_TAGS'), ENT_QUOTES, 'UTF-8'); ?></dt>
          <dd class="blog-filter-chip-list">
            <?php foreach ($articleTagItems as $tag): ?>
              <a class="blog-filter-chip" href="<?php echo htmlspecialchars($buildBlogFilterUrl(null, (string) $tag['slug']), ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars((string) $tag['label'], ENT_QUOTES, 'UTF-8'); ?>
              </a>
            <?php endforeach; ?>
          </dd>
        </div>
      <?php endif; ?>
    </dl>
  </aside>
<?php endif; ?>
<?php
$blocks['EditRegion2'] = ob_get_clean();

ob_start();
?>
<article class="blog-article-body">
  <?php echo $renderedArticleContent; ?>
</article>
<?php
$blocks['EditRegion3'] = ob_get_clean();

ob_start();
?>
<?php if ($childArticles !== []): ?>
<section class="content-callout blog-children" aria-labelledby="blog-children-title">
  <h2 id="blog-children-title" class="content-callout-title"><?php echo htmlspecialchars(t('TXT_BLOG_RELATED_ARTICLES'), ENT_QUOTES, 'UTF-8'); ?></h2>
  <ul class="blog-child-list">
    <?php foreach ($childArticles as $childArticle): ?>
    <li class="blog-child-list-item">
      <a class="blog-child-link" href="<?php echo htmlspecialchars($resolveArticleUrl($childArticle), ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo htmlspecialchars((string) ($childArticle['title'] ?? t('TXT_BLOG_NO_TITLE')), ENT_QUOTES, 'UTF-8'); ?>
      </a>
      <span class="blog-child-meta">
        <?php echo htmlspecialchars((string) ($childArticle['date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
      </span>
    </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($relatedSuggestions !== []): ?>
<section class="content-callout blog-children" aria-labelledby="blog-taxonomy-related-title">
  <h2 id="blog-taxonomy-related-title" class="content-callout-title"><?php echo htmlspecialchars(t('TXT_BLOG_SUGGESTED_ARTICLES'), ENT_QUOTES, 'UTF-8'); ?></h2>
  <ul class="blog-child-list">
    <?php foreach ($relatedSuggestions as $relatedArticle): ?>
    <li class="blog-child-list-item">
      <a class="blog-child-link" href="<?php echo htmlspecialchars($resolveArticleUrl($relatedArticle), ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo htmlspecialchars((string) ($relatedArticle['title'] ?? t('TXT_BLOG_NO_TITLE')), ENT_QUOTES, 'UTF-8'); ?>
      </a>
      <span class="blog-child-meta">
        <?php echo htmlspecialchars((string) ($relatedArticle['date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
      </span>
    </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($discussionsEnabled): ?>
<?php
$discussionAnchorId = 'discussion-form';
$discussionTitleId = 'blog-discussions-title';
$discussionFieldPrefix = 'discussion';
$returnToDiscussionUrl = app_url(ltrim($articleFallbackPath, '/')) . '#discussion-form';
require TEMPLATES_PATH . '/partials/blog/discussion_panel.php';
?>
<?php endif; ?>
<?php
$blocks['EditRegion4'] = ob_get_clean();
// Le layout global est rendu par FrontController::pageResponse().
