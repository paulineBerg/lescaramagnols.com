<?php

$language = defined('CURRENT_LANG') ? CURRENT_LANG : (defined('DEFAULT_LANG') ? DEFAULT_LANG : 'fr');
$defaultLanguage = defined('DEFAULT_LANG') ? DEFAULT_LANG : 'fr';
$articleTree = is_array($GLOBALS['currentBlogArticles'] ?? null)
    ? $GLOBALS['currentBlogArticles']
    : blog_repository()->publishedArticleTree($language);
$blogFilters = is_array($GLOBALS['currentBlogFilters'] ?? null)
    ? $GLOBALS['currentBlogFilters']
    : ['category' => null, 'tag' => null];
$hubPage = is_array($GLOBALS['currentBlogHubPage'] ?? null)
    ? $GLOBALS['currentBlogHubPage']
    : null;
$requestUri = is_string($_SERVER['REQUEST_URI'] ?? null) ? (string) $_SERVER['REQUEST_URI'] : '/blog';
$hubViewModelBuilder = new \Caramagnols\Blog\BlogHubViewModelBuilder(
    blog_repository(),
    page_repository(pages_data_path()),
    \Caramagnols\Blog\BlogTaxonomy::fromDefaultConfig(),
    $defaultLanguage
);
$viewModel = $hubViewModelBuilder->build($articleTree, $blogFilters, $language, $requestUri, $hubPage);
$hubPage = is_array($viewModel['hubPage'] ?? null) ? $viewModel['hubPage'] : null;
$hubBlocks = is_array($hubPage['blocks'] ?? null) ? $hubPage['blocks'] : [];
$hubTitle = trim((string) ($hubPage['title'] ?? ''));
if ($hubTitle === '') {
    $hubTitle = t('TXT_BLOG_SITE_TITLE');
}

$activeCategorySlug = is_string($viewModel['filters']['categorySlug'] ?? null)
    ? trim((string) $viewModel['filters']['categorySlug'])
    : '';
$activeTagSlug = is_string($viewModel['filters']['tagSlug'] ?? null)
    ? trim((string) $viewModel['filters']['tagSlug'])
    : '';
if ($activeCategorySlug !== '' || $activeTagSlug !== '') {
    $requestPath = normalize_public_route((string) (parse_url($requestUri, PHP_URL_PATH) ?? '/')) ?? '/';
    $requestQuery = parse_url($requestUri, PHP_URL_QUERY);
    $canonicalPath = normalize_public_route((string) (parse_url((string) ($viewModel['canonicalPath'] ?? '/blog'), PHP_URL_PATH) ?? '/')) ?? '/';
    $queryParams = [];
    if (is_string($requestQuery) && trim($requestQuery) !== '') {
        parse_str($requestQuery, $queryParams);
        if (!is_array($queryParams)) {
            $queryParams = [];
        }
    }

    $legacyQueryFilterUsed = array_key_exists('category', $queryParams) || array_key_exists('tag', $queryParams);
    if ($legacyQueryFilterUsed || $requestPath !== $canonicalPath) {
        header('Location: ' . app_url(ltrim((string) ($viewModel['canonicalPath'] ?? '/blog'), '/')), true, 301);
        return;
    }
}

$pageTitle = $hubTitle . ' · ' . t('TXT_SITE_BRAND');
$pageBodyClass = 'page-blog-index';
$pageRobots = trim((string) ($viewModel['robots'] ?? 'index,follow'));
$pageCanonicalUrl = app_url(ltrim((string) ($viewModel['canonicalPath'] ?? '/blog'), '/'));
$GLOBALS['pageCanonicalUrl'] = $pageCanonicalUrl;
$articles = is_array($viewModel['articles'] ?? null) ? $viewModel['articles'] : [];

$hubMeta = is_array($hubPage['meta'] ?? null) ? $hubPage['meta'] : [];
$pageMetaDescription = trim((string) ($hubMeta['description'] ?? ''));
if ($pageMetaDescription === '') {
    $introTextSource = trim(strip_tags(
        (string) ($hubBlocks['EditRegion8'] ?? $hubBlocks['EditRegion2'] ?? '')
    ));
    if ($introTextSource !== '') {
        $pageMetaDescription = function_exists('mb_substr')
            ? (string) mb_substr($introTextSource, 0, 240)
            : substr($introTextSource, 0, 240);
    }
}

$hubMetaImage = \Caramagnols\Admin\AdminEditorialImageService::sanitizeImageMetadata(
    is_array($hubMeta['image'] ?? null) ? $hubMeta['image'] : []
);
$toAbsoluteImageUrl = static function (string $src): string {
    if (preg_match('#^https?://#i', $src) === 1) {
        return $src;
    }

    return app_url(ltrim($src, '/'));
};
$pageMetaImage = is_array($hubMetaImage) && trim((string) ($hubMetaImage['src'] ?? '')) !== ''
    ? $toAbsoluteImageUrl((string) $hubMetaImage['src'])
    : null;
$pageMetaImageAlt = is_array($hubMetaImage) ? trim((string) ($hubMetaImage['alt'] ?? '')) : '';
$pageMetaImageWidth = $hubMetaImage['width'] ?? null;
$pageMetaImageHeight = $hubMetaImage['height'] ?? null;
if ($pageMetaImage === null && $articles !== []) {
    foreach ($articles as $articleItem) {
        if (!is_array($articleItem)) {
            continue;
        }

        $articleImage = \Caramagnols\Admin\AdminEditorialImageService::sanitizeImageMetadata(
            is_array($articleItem['image'] ?? null) ? $articleItem['image'] : []
        );
        if (!is_array($articleImage) || trim((string) ($articleImage['src'] ?? '')) === '') {
            continue;
        }

        $pageMetaImage = $toAbsoluteImageUrl((string) $articleImage['src']);
        $pageMetaImageAlt = trim((string) ($articleImage['alt'] ?? (string) ($articleItem['title'] ?? $hubTitle)));
        $pageMetaImageWidth = $articleImage['width'] ?? null;
        $pageMetaImageHeight = $articleImage['height'] ?? null;
        if ($pageMetaImage !== null) {
            break;
        }
    }
}
if ($pageMetaImageAlt === '') {
    $pageMetaImageAlt = $hubTitle;
}

$activeCategoryLabel = trim((string) ($viewModel['filters']['categoryLabel'] ?? ''));
$activeTagLabel = trim((string) ($viewModel['filters']['tagLabel'] ?? ''));
$categoryFilters = is_array($viewModel['categoryFilters'] ?? null) ? $viewModel['categoryFilters'] : [];
$pagination = is_array($viewModel['pagination'] ?? null) ? $viewModel['pagination'] : [];
$paginationFrom = (int) ($pagination['from'] ?? 0);
$paginationTo = (int) ($pagination['to'] ?? 0);
$paginationTotal = (int) ($pagination['totalArticles'] ?? 0);

$renderHubHero = static function (string $title): string {
    ob_start();
    ?>
    <div class="content-heading">
      <div>
        <h1><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
      </div>
    </div>
    <?php

    return (string) ob_get_clean();
};

$hubIntroBlocks = array_values(array_filter([
    trim((string) ($hubBlocks['EditRegion8'] ?? '')),
    trim((string) ($hubBlocks['EditRegion2'] ?? '')),
], static fn (string $value): bool => $value !== ''));

$blocks = [];
$blocks['EditRegion1'] = trim((string) ($hubBlocks['EditRegion1'] ?? '')) !== ''
    ? (string) $hubBlocks['EditRegion1']
    : $renderHubHero($hubTitle);

ob_start();
?>
<?php foreach ($hubIntroBlocks as $hubIntroBlock): ?>
<div class="blog-hub-intro">
  <?php echo $hubIntroBlock; ?>
</div>
<?php endforeach; ?>

<?php if ($activeCategoryLabel !== '' || $activeTagLabel !== ''): ?>
<aside class="content-callout blog-filter-summary">
  <h2 class="content-callout-title"><?php echo htmlspecialchars(t('TXT_BLOG_FILTER_CURRENT'), ENT_QUOTES, 'UTF-8'); ?></h2>
  <p>
    <?php if ($activeCategoryLabel !== ''): ?>
      <?php echo htmlspecialchars(t('TXT_BLOG_FILTER_BY_CATEGORY'), ENT_QUOTES, 'UTF-8'); ?>:
      <strong><?php echo htmlspecialchars($activeCategoryLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
    <?php endif; ?>
    <?php if ($activeCategoryLabel !== '' && $activeTagLabel !== ''): ?>
      <span>•</span>
    <?php endif; ?>
    <?php if ($activeTagLabel !== ''): ?>
      <?php echo htmlspecialchars(t('TXT_BLOG_FILTER_BY_TAG'), ENT_QUOTES, 'UTF-8'); ?>:
      <strong><?php echo htmlspecialchars($activeTagLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
    <?php endif; ?>
  </p>
  <p><a href="<?php echo htmlspecialchars(app_url(ltrim((string) ($viewModel['indexPath'] ?? '/blog'), '/')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(t('TXT_BLOG_FILTER_RESET'), ENT_QUOTES, 'UTF-8'); ?></a></p>
</aside>
<?php endif; ?>

<?php if ($categoryFilters !== []): ?>
<section class="blog-list blog-hub-filters" aria-labelledby="blog-hub-filters-title">
  <h2 id="blog-hub-filters-title"><?php echo htmlspecialchars(t('TXT_BLOG_FILTER_CATEGORIES'), ENT_QUOTES, 'UTF-8'); ?></h2>
  <p class="blog-card-filter-meta">
    <?php foreach ($categoryFilters as $categoryFilter): ?>
      <?php if (!is_array($categoryFilter)): ?>
        <?php continue; ?>
      <?php endif; ?>
      <?php
      $filterLabel = trim((string) ($categoryFilter['label'] ?? ''));
      if ($filterLabel === '') {
          $filterLabel = t('TXT_BLOG_FILTER_ALL_CATEGORIES');
      }
      ?>
      <a
        class="blog-filter-chip"
        href="<?php echo htmlspecialchars(app_url(ltrim((string) ($categoryFilter['path'] ?? '/blog'), '/')), ENT_QUOTES, 'UTF-8'); ?>"
        <?php echo !empty($categoryFilter['active']) ? 'aria-current="page"' : ''; ?>
      >
        <?php echo htmlspecialchars($filterLabel, ENT_QUOTES, 'UTF-8'); ?>
      </a>
    <?php endforeach; ?>
  </p>
</section>
<?php endif; ?>
<?php
$blocks['EditRegion2'] = (string) ob_get_clean();

ob_start();
?>
<section class="blog-list blog-hub-list" aria-labelledby="blog-list-title">
  <h2 id="blog-list-title"><?php echo htmlspecialchars(t('TXT_BLOG_PUBLISHED_ARTICLES'), ENT_QUOTES, 'UTF-8'); ?></h2>

  <?php if ($paginationTotal > 0): ?>
  <p class="blog-card-meta blog-hub-results">
    <?php echo htmlspecialchars(sprintf(t('TXT_BLOG_RESULTS_RANGE'), $paginationFrom, $paginationTo, $paginationTotal), ENT_QUOTES, 'UTF-8'); ?>
  </p>
  <?php endif; ?>

  <?php if ($articles === []): ?>
    <p class="blog-empty"><?php echo htmlspecialchars(t('TXT_BLOG_EMPTY'), ENT_QUOTES, 'UTF-8'); ?></p>
  <?php else: ?>
    <?php foreach ($articles as $article): ?>
      <?php if (!is_array($article)): ?>
        <?php continue; ?>
      <?php endif; ?>
      <?php $parentPage = is_array($article['parentPage'] ?? null) ? $article['parentPage'] : null; ?>
      <article class="blog-card blog-hub-card" id="<?php echo htmlspecialchars('blog-hub-' . (string) ($article['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <?php if (is_array($article['image'] ?? null) && trim((string) ($article['image']['src'] ?? '')) !== ''): ?>
        <p class="blog-card-media">
          <a href="<?php echo htmlspecialchars(app_url(ltrim((string) ($article['path'] ?? '/blog'), '/')), ENT_QUOTES, 'UTF-8'); ?>">
            <img
              src="<?php echo htmlspecialchars((string) $article['image']['src'], ENT_QUOTES, 'UTF-8'); ?>"
              alt="<?php echo htmlspecialchars(trim((string) ($article['image']['alt'] ?? '')) !== '' ? (string) $article['image']['alt'] : (string) ($article['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
              <?php if (trim((string) ($article['image']['title'] ?? '')) !== ''): ?>
              title="<?php echo htmlspecialchars((string) $article['image']['title'], ENT_QUOTES, 'UTF-8'); ?>"
              <?php endif; ?>
              width="<?php echo (int) ($article['image']['width'] ?? 1200); ?>"
              height="<?php echo (int) ($article['image']['height'] ?? 630); ?>"
              loading="lazy"
              decoding="async"
              fetchpriority="low"
            />
          </a>
        </p>
        <?php endif; ?>

        <p class="blog-card-meta">
          <?php if (trim((string) ($article['categoryLabel'] ?? '')) !== ''): ?>
          <span><?php echo htmlspecialchars((string) $article['categoryLabel'], ENT_QUOTES, 'UTF-8'); ?></span>
          <?php endif; ?>
          <?php if (trim((string) ($article['categoryLabel'] ?? '')) !== '' && trim((string) ($article['dateLabel'] ?? '')) !== ''): ?>
          <span>•</span>
          <?php endif; ?>
          <?php if (trim((string) ($article['dateLabel'] ?? '')) !== ''): ?>
          <span><?php echo htmlspecialchars((string) $article['dateLabel'], ENT_QUOTES, 'UTF-8'); ?></span>
          <?php endif; ?>
        </p>

        <h3 class="blog-card-title">
          <a href="<?php echo htmlspecialchars(app_url(ltrim((string) ($article['path'] ?? '/blog'), '/')), ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars((string) ($article['title'] ?? t('TXT_BLOG_NO_TITLE')), ENT_QUOTES, 'UTF-8'); ?>
          </a>
        </h3>

        <?php if (trim((string) ($article['excerpt'] ?? '')) !== ''): ?>
        <p class="blog-card-excerpt"><?php echo htmlspecialchars((string) $article['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <?php if ($parentPage !== null && trim((string) ($parentPage['title'] ?? '')) !== ''): ?>
        <p class="blog-card-meta blog-hub-parent">
          <span><?php echo htmlspecialchars(t('TXT_BLOG_ATTACHED_TO'), ENT_QUOTES, 'UTF-8'); ?>:</span>
          <?php if (is_string($parentPage['route'] ?? null) && trim((string) $parentPage['route']) !== ''): ?>
          <a href="<?php echo htmlspecialchars(app_url(ltrim((string) $parentPage['route'], '/')), ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars((string) $parentPage['title'], ENT_QUOTES, 'UTF-8'); ?>
          </a>
          <?php else: ?>
          <span><?php echo htmlspecialchars((string) $parentPage['title'], ENT_QUOTES, 'UTF-8'); ?></span>
          <?php endif; ?>
        </p>
        <?php endif; ?>

        <p class="blog-card-filter-meta">
          <a class="blog-filter-chip" href="<?php echo htmlspecialchars(app_url(ltrim((string) ($article['path'] ?? '/blog'), '/')), ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars(t('TXT_BLOG_READ_ARTICLE'), ENT_QUOTES, 'UTF-8'); ?>
          </a>
        </p>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ((int) ($pagination['totalPages'] ?? 1) > 1): ?>
  <nav class="blog-hub-pagination" aria-label="<?php echo htmlspecialchars(t('TXT_BLOG_PAGINATION_LABEL'), ENT_QUOTES, 'UTF-8'); ?>">
    <p class="blog-card-filter-meta">
      <?php if (is_string($pagination['previousPath'] ?? null) && $pagination['previousPath'] !== ''): ?>
      <a class="blog-filter-chip" href="<?php echo htmlspecialchars(app_url(ltrim((string) $pagination['previousPath'], '/')), ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo htmlspecialchars(t('TXT_BLOG_PAGE_PREVIOUS'), ENT_QUOTES, 'UTF-8'); ?>
      </a>
      <?php endif; ?>

      <span class="blog-filter-chip">
        <?php echo htmlspecialchars(sprintf(t('TXT_BLOG_PAGE_X_OF_Y'), (int) ($pagination['currentPage'] ?? 1), (int) ($pagination['totalPages'] ?? 1)), ENT_QUOTES, 'UTF-8'); ?>
      </span>

      <?php if (is_string($pagination['nextPath'] ?? null) && $pagination['nextPath'] !== ''): ?>
      <a class="blog-filter-chip" href="<?php echo htmlspecialchars(app_url(ltrim((string) $pagination['nextPath'], '/')), ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo htmlspecialchars(t('TXT_BLOG_PAGE_NEXT'), ENT_QUOTES, 'UTF-8'); ?>
      </a>
      <?php endif; ?>
    </p>
  </nav>
  <?php endif; ?>
</section>
<?php
$blocks['EditRegion3'] = (string) ob_get_clean();
// Le layout global est rendu par FrontController::pageResponse().
