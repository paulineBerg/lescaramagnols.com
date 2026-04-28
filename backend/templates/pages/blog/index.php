<?php

$language = defined('CURRENT_LANG') ? CURRENT_LANG : (defined('DEFAULT_LANG') ? DEFAULT_LANG : 'fr');
$defaultLanguage = defined('DEFAULT_LANG') ? DEFAULT_LANG : 'fr';
$blogTaxonomy = \Caramagnols\Blog\BlogTaxonomy::fromDefaultConfig();

$articles = is_array($GLOBALS['currentBlogArticles'] ?? null)
    ? $GLOBALS['currentBlogArticles']
    : blog_repository()->publishedArticleTree($language);

$blogFilters = is_array($GLOBALS['currentBlogFilters'] ?? null)
    ? $GLOBALS['currentBlogFilters']
    : ['category' => null, 'tag' => null];
$activeCategory = is_string($blogFilters['category'] ?? null) ? trim((string) $blogFilters['category']) : '';
$activeTag = is_string($blogFilters['tag'] ?? null) ? trim((string) $blogFilters['tag']) : '';
$activeCategorySlug = $blogTaxonomy->resolveCategorySlug($activeCategory) ?? $activeCategory;
$activeTagSlug = $blogTaxonomy->resolveTagSlug($activeTag) ?? $activeTag;
$activeCategoryLabel = $activeCategorySlug !== '' ? $blogTaxonomy->categoryLabel($activeCategorySlug, $language) : '';
$activeTagLabel = $activeTagSlug !== '' ? $blogTaxonomy->tagLabel($activeTagSlug, $language) : '';

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

$buildBlogFilterUrl = static function (?string $category = null, ?string $tag = null) use ($language, $slugifyBlogFilterValue): string {
    $segments = [trim($language, '/'), 'blog'];

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
$returnToBlogPath = normalize_public_route((string) (parse_url(
    $buildBlogFilterUrl(
        $activeCategorySlug !== '' ? $activeCategorySlug : null,
        $activeTagSlug !== '' ? $activeTagSlug : null
    ),
    PHP_URL_PATH
) ?? '/')) ?? '/';

$resolveFeaturedImage = static function (array $article): ?array {
    $featured = \Caramagnols\Admin\AdminEditorialImageService::sanitizeImageMetadata(
        is_array($article['featured_image'] ?? null) ? $article['featured_image'] : []
    );
    if (!is_array($featured)) {
        return null;
    }

    $width = isset($featured['width']) ? max(1, min(8192, (int) $featured['width'])) : 1200;
    $height = isset($featured['height']) ? max(1, min(8192, (int) $featured['height'])) : 630;

    return [
        'src' => (string) ($featured['src'] ?? ''),
        'alt' => trim((string) ($featured['alt'] ?? '')),
        'title' => trim((string) ($featured['title'] ?? '')),
        'width' => $width,
        'height' => $height,
    ];
};

$pageRouteCache = [];
$resolveArticleDestinationUrl = static function (array $article) use (&$pageRouteCache, $language, $defaultLanguage, $returnToBlogPath): string {
    $slug = trim((string) ($article['slug'] ?? ''));
    if ($slug === '') {
        return app_url(trim($language, '/') . '/blog');
    }

    $pageSlug = trim((string) ($article['page_slug'] ?? ''));
    if ($pageSlug !== '') {
        if (!array_key_exists($pageSlug, $pageRouteCache)) {
            $page = page_repository()->findPublishedStructuredBySlug($pageSlug, $language, $defaultLanguage);
            $pageRouteCache[$pageSlug] = is_array($page)
                ? (normalize_public_route((string) ($page['route'] ?? '')) ?? null)
                : null;
        }

        $route = $pageRouteCache[$pageSlug];
        if (is_string($route) && $route !== '') {
            $path = trim($language, '/') . ($route === '/' ? '/' : $route);
            $url = app_url($path);
            $queryParams = ['open_article' => $slug];
            if ($returnToBlogPath !== '') {
                $queryParams['blog_return'] = $returnToBlogPath;
            }
            $query = http_build_query($queryParams);

            return $url . ($query !== '' ? '?' . $query : '') . '#attached-article-' . rawurlencode($slug);
        }
    }

    return app_url(trim($language, '/') . '/blog/article/' . rawurlencode($slug));
};

$canonicalFilterUrl = null;
if ($activeCategorySlug !== '' || $activeTagSlug !== '') {
    $canonicalFilterUrl = $buildBlogFilterUrl(
        $activeCategorySlug !== '' ? $activeCategorySlug : null,
        $activeTagSlug !== '' ? $activeTagSlug : null
    );

    $requestUri = is_string($_SERVER['REQUEST_URI'] ?? null) ? (string) $_SERVER['REQUEST_URI'] : '';
    $requestPath = normalize_public_route((string) (parse_url($requestUri, PHP_URL_PATH) ?? '/')) ?? '/';
    $requestQuery = parse_url($requestUri, PHP_URL_QUERY);
    $canonicalPath = normalize_public_route((string) (parse_url($canonicalFilterUrl, PHP_URL_PATH) ?? '/')) ?? '/';

    $queryParams = [];
    if (is_string($requestQuery) && trim($requestQuery) !== '') {
        parse_str($requestQuery, $queryParams);
    }

    $legacyQueryFilterUsed = array_key_exists('category', $queryParams) || array_key_exists('tag', $queryParams);
    $mustRedirect = $legacyQueryFilterUsed || $requestPath !== $canonicalPath;

    if ($mustRedirect) {
        header('Location: ' . $canonicalFilterUrl, true, 301);
        return;
    }
}

$pageTitle = t('TXT_BLOG_PAGE_LABEL') . ' · ' . t('TXT_SITE_BRAND');
$pageRobots = $activeTagSlug !== '' ? 'noindex,follow' : 'index,follow';

ob_start();
?>
<div class="content-heading">
  <div>
    <h1><?php echo htmlspecialchars(t('TXT_BLOG_SITE_TITLE'), ENT_QUOTES, 'UTF-8'); ?></h1>
  </div>
</div>
<?php
$blocks['EditRegion1'] = ob_get_clean();

ob_start();
?>
<?php if ($activeCategorySlug !== '' || $activeTagSlug !== ''): ?>
<aside class="content-callout blog-filter-summary">
  <h2 class="content-callout-title"><?php echo htmlspecialchars(t('TXT_BLOG_FILTER_CURRENT'), ENT_QUOTES, 'UTF-8'); ?></h2>
  <p>
    <?php if ($activeCategorySlug !== ''): ?>
      <?php echo htmlspecialchars(t('TXT_BLOG_FILTER_BY_CATEGORY'), ENT_QUOTES, 'UTF-8'); ?>:
      <strong><?php echo htmlspecialchars($activeCategoryLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
    <?php endif; ?>
    <?php if ($activeCategorySlug !== '' && $activeTagSlug !== ''): ?>
      <span>•</span>
    <?php endif; ?>
    <?php if ($activeTagSlug !== ''): ?>
      <?php echo htmlspecialchars(t('TXT_BLOG_FILTER_BY_TAG'), ENT_QUOTES, 'UTF-8'); ?>:
      <strong><?php echo htmlspecialchars($activeTagLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
    <?php endif; ?>
  </p>
  <p><a href="<?php echo htmlspecialchars($buildBlogFilterUrl(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(t('TXT_BLOG_FILTER_RESET'), ENT_QUOTES, 'UTF-8'); ?></a></p>
</aside>
<?php endif; ?>
<?php
$blocks['EditRegion2'] = ob_get_clean();

ob_start();
?>
<section class="blog-list" aria-labelledby="blog-list-title">
  <h2 id="blog-list-title"><?php echo htmlspecialchars(t('TXT_BLOG_PUBLISHED_ARTICLES'), ENT_QUOTES, 'UTF-8'); ?></h2>
  <?php if ($articles === []): ?>
    <p class="blog-empty"><?php echo htmlspecialchars(t('TXT_BLOG_EMPTY'), ENT_QUOTES, 'UTF-8'); ?></p>
  <?php else: ?>
    <?php
    $renderBlogTree = static function (
        array $items,
        int $depth = 0
    ) use (&$renderBlogTree, $buildBlogFilterUrl, $resolveArticleDestinationUrl, $resolveFeaturedImage, $blogTaxonomy, $language): void {
        foreach ($items as $article) {
            $title = (string) ($article['title'] ?? t('TXT_BLOG_NO_TITLE'));
            $date = (string) ($article['date'] ?? '');
            $excerpt = trim((string) ($article['excerpt'] ?? ''));
            $featuredImage = $resolveFeaturedImage($article);

            if ($excerpt === '') {
                $excerpt = trim(strip_tags((string) ($article['content'] ?? '')));
            }

            $excerpt = function_exists('mb_substr') ? mb_substr($excerpt, 0, 280) : substr($excerpt, 0, 280);
            $articleUrl = $resolveArticleDestinationUrl($article);
            $childArticles = is_array($article['child_articles'] ?? null) ? $article['child_articles'] : [];
            $categorySlug = $blogTaxonomy->resolveCategorySlug($article['category'] ?? null);
            $category = $categorySlug !== null ? $blogTaxonomy->categoryLabel($categorySlug, $language) : trim((string) ($article['category'] ?? ''));
            $tags = [];
            foreach (is_array($article['tags'] ?? null) ? $article['tags'] : [] as $rawTag) {
                $tagSlug = $blogTaxonomy->resolveTagSlug($rawTag);
                $tagLabel = $tagSlug !== null ? $blogTaxonomy->tagLabel($tagSlug, $language) : trim((string) $rawTag);
                if ($tagLabel !== '') {
                    $tags[] = ['slug' => $tagSlug ?? $tagLabel, 'label' => $tagLabel];
                }
            }
            ?>
            <article class="blog-card<?php echo $depth > 0 ? ' blog-card-child' : ''; ?>">
              <p class="blog-card-meta">
                <span><?php echo htmlspecialchars($date, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php if ($category !== ''): ?>
                  <span>•</span>
                  <span><?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
                <?php if ($depth > 0): ?>
                  <span>•</span>
                  <span><?php echo htmlspecialchars(t('TXT_BLOG_RELATED_ARTICLE'), ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
              </p>
              <?php if ($category !== '' || $tags !== []): ?>
              <p class="blog-card-filter-meta">
                <?php if ($category !== ''): ?>
                <a class="blog-filter-chip" href="<?php echo htmlspecialchars($buildBlogFilterUrl($categorySlug ?? $category, null), ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo htmlspecialchars(t('TXT_BLOG_FILTER_BY_CATEGORY'), ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>
                </a>
                <?php endif; ?>
                <?php foreach ($tags as $tag): ?>
                <a class="blog-filter-chip" href="<?php echo htmlspecialchars($buildBlogFilterUrl(null, (string) $tag['slug']), ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo htmlspecialchars(t('TXT_BLOG_FILTER_BY_TAG'), ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars((string) $tag['label'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
                <?php endforeach; ?>
              </p>
              <?php endif; ?>
              <h3 class="blog-card-title">
                <a href="<?php echo htmlspecialchars($articleUrl, ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>
                </a>
              </h3>
              <?php if ($featuredImage !== null && $featuredImage['src'] !== ''): ?>
              <p class="blog-card-media">
                <img
                  src="<?php echo htmlspecialchars((string) $featuredImage['src'], ENT_QUOTES, 'UTF-8'); ?>"
                  alt="<?php echo htmlspecialchars($featuredImage['alt'] !== '' ? (string) $featuredImage['alt'] : $title, ENT_QUOTES, 'UTF-8'); ?>"
                  <?php if ($featuredImage['title'] !== ''): ?>
                  title="<?php echo htmlspecialchars((string) $featuredImage['title'], ENT_QUOTES, 'UTF-8'); ?>"
                  <?php endif; ?>
                  width="<?php echo (int) $featuredImage['width']; ?>"
                  height="<?php echo (int) $featuredImage['height']; ?>"
                  loading="lazy"
                  decoding="async"
                  fetchpriority="low"
                />
              </p>
              <?php endif; ?>
              <p class="blog-card-excerpt"><?php echo htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8'); ?></p>

              <?php if ($childArticles !== []): ?>
              <div class="blog-card-children">
                <p class="blog-card-children-title"><?php echo htmlspecialchars(t('TXT_BLOG_RELATED_ARTICLES'), ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="blog-card-children-list">
                  <?php $renderBlogTree($childArticles, $depth + 1); ?>
                </div>
              </div>
              <?php endif; ?>
            </article>
            <?php
        }
    };

    $renderBlogTree($articles);
    ?>
  <?php endif; ?>
</section>
<?php
$blocks['EditRegion3'] = ob_get_clean();

require __DIR__ . '/../../partials/layout.php';
