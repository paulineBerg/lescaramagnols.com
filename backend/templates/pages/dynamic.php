<?php
// backend/templates/pages/dynamic.php
// Page dynamique rendue depuis backend/data/pages.json

// Récupère la page préchargée par resolve_route (évite une double lecture disque)
$page = $GLOBALS['currentDynamicPage'] ?? null;

// Par sécurité, possibilité de recharger si non préalablement défini
if ($page === null && function_exists('get_page_by_slug')) {
    $slug = $_GET['slug'] ?? null; // non documenté, simple fallback
    $lang = defined('CURRENT_LANG') ? CURRENT_LANG : (defined('DEFAULT_LANG') ? DEFAULT_LANG : 'fr');
    if ($slug) {
        $page = get_page_by_slug($slug, $lang);
    }
}

if ($page === null) {
    // Si pas trouvé, on renvoie vers la 404 habituelle
    http_response_code(404);
    include TEMPLATES_PATH . '/pages/404.php';
    return;
}

$pageTitle = $page['title'] ?? t('TXT_SITE_BRAND');
$blocks = is_array($page['blocks'] ?? null) ? $page['blocks'] : [];
$pageSlug = trim((string) ($page['slug'] ?? ''));
$language = defined('CURRENT_LANG') ? CURRENT_LANG : (defined('DEFAULT_LANG') ? DEFAULT_LANG : 'fr');
$attachedArticles = $pageSlug !== ''
    ? blog_repository()->publishedArticleTreeForPage($pageSlug, $language)
    : [];
$toAbsoluteImageUrl = static function (string $src): string {
    if (preg_match('#^https?://#i', $src) === 1) {
        return $src;
    }

    return app_url(ltrim($src, '/'));
};
$translate = static function (string $key, string $fallback): string {
    if (!function_exists('t')) {
        return $fallback;
    }

    $translated = t($key);
    if (!is_string($translated) || $translated === '' || $translated === '[[' . $key . ']]') {
        return $fallback;
    }

    return $translated;
};
$sharedMediaRaw = is_array($page['meta']['shared_media'] ?? null) ? $page['meta']['shared_media'] : [];
$sharedMediaItems = [];
foreach ($sharedMediaRaw as $sharedMediaItem) {
    if (!is_array($sharedMediaItem)) {
        continue;
    }

    $image = \Caramagnols\Admin\AdminEditorialImageService::sanitizeImageMetadata($sharedMediaItem);
    if (!is_array($image)) {
        continue;
    }

    $src = trim((string) ($image['src'] ?? ''));
    if ($src === '') {
        continue;
    }

    $sharedMediaItems[] = [
        'src' => $src,
        'alt' => trim((string) ($image['alt'] ?? '')),
        'title' => trim((string) ($image['title'] ?? '')),
        'caption' => trim((string) ($image['caption'] ?? '')),
        'width' => isset($image['width']) ? max(1, min(8192, (int) $image['width'])) : 1600,
        'height' => isset($image['height']) ? max(1, min(8192, (int) $image['height'])) : 900,
    ];
}

if ($sharedMediaItems !== []) {
    ob_start();
    ?>
    <section class="blog-list page-shared-media" aria-labelledby="page-shared-media-title">
      <h2 id="page-shared-media-title"><?php echo htmlspecialchars($translate('TXT_PAGE_SHARED_MEDIA_TITLE', 'Galerie photos'), ENT_QUOTES, 'UTF-8'); ?></h2>
      <div class="blog-cards page-shared-media-grid">
        <?php foreach ($sharedMediaItems as $index => $sharedMediaItem): ?>
        <figure class="blog-card page-shared-media-card">
          <a href="<?php echo htmlspecialchars((string) $sharedMediaItem['src'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
            <img
              src="<?php echo htmlspecialchars((string) $sharedMediaItem['src'], ENT_QUOTES, 'UTF-8'); ?>"
              alt="<?php echo htmlspecialchars($sharedMediaItem['alt'] !== '' ? (string) $sharedMediaItem['alt'] : (string) $pageTitle, ENT_QUOTES, 'UTF-8'); ?>"
              <?php if ($sharedMediaItem['title'] !== ''): ?>
              title="<?php echo htmlspecialchars((string) $sharedMediaItem['title'], ENT_QUOTES, 'UTF-8'); ?>"
              <?php endif; ?>
              width="<?php echo (int) $sharedMediaItem['width']; ?>"
              height="<?php echo (int) $sharedMediaItem['height']; ?>"
              loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>"
              decoding="async"
              fetchpriority="<?php echo $index === 0 ? 'high' : 'low'; ?>"
            />
          </a>
          <?php if ($sharedMediaItem['caption'] !== ''): ?>
          <figcaption><?php echo htmlspecialchars((string) $sharedMediaItem['caption'], ENT_QUOTES, 'UTF-8'); ?></figcaption>
          <?php endif; ?>
        </figure>
        <?php endforeach; ?>
      </div>
    </section>
    <?php

    $existingHero = (string) ($blocks['EditRegion1'] ?? '');
    $blocks['EditRegion1'] = ob_get_clean() . $existingHero;
}

if ($attachedArticles !== []) {
    $openArticleSlug = '';
    if (isset($_GET['open_article']) && is_string($_GET['open_article'])) {
        $candidate = strtolower(trim((string) $_GET['open_article']));
        $candidate = preg_replace('/[^a-z0-9-]+/i', '-', $candidate) ?? '';
        $openArticleSlug = trim($candidate, '-');
    }
    $blogReturnUrl = null;
    if (isset($_GET['blog_return']) && is_string($_GET['blog_return'])) {
        $candidateBlogReturn = normalize_public_route((string) $_GET['blog_return']);
        if (
            is_string($candidateBlogReturn)
            && preg_match('#^/(?:[a-z]{2,5}/)?blog(?:/|$)#', $candidateBlogReturn) === 1
        ) {
            $blogReturnUrl = app_url(ltrim($candidateBlogReturn, '/'));
        }
    }

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

    $resolveFeaturedImage = static function (array $article): ?array {
        $featured = \Caramagnols\Admin\AdminEditorialImageService::sanitizeImageMetadata(
            is_array($article['featured_image'] ?? null) ? $article['featured_image'] : []
        );
        if (!is_array($featured)) {
            return null;
        }

        return [
            'src' => (string) ($featured['src'] ?? ''),
            'alt' => trim((string) ($featured['alt'] ?? '')),
            'title' => trim((string) ($featured['title'] ?? '')),
            'caption' => trim((string) ($featured['caption'] ?? '')),
            'width' => isset($featured['width']) ? max(1, min(8192, (int) $featured['width'])) : 1200,
            'height' => isset($featured['height']) ? max(1, min(8192, (int) $featured['height'])) : 630,
        ];
    };

    $flattenAttachedArticles = static function (array $items) use (&$flattenAttachedArticles): array {
        $flattened = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $flattened[] = $item;
            $children = is_array($item['child_articles'] ?? null) ? $item['child_articles'] : [];

            if ($children !== []) {
                $flattened = array_merge($flattened, $flattenAttachedArticles($children));
            }
        }

        return $flattened;
    };

    $articleCreationTimestamp = static function (array $article): int {
        foreach (['created_at', 'date', 'updated_at'] as $field) {
            $value = $article[$field] ?? null;
            $timestamp = is_string($value) ? strtotime($value) : false;

            if (is_int($timestamp)) {
                return $timestamp;
            }
        }

        return 0;
    };

    $attachedChronicleArticles = $flattenAttachedArticles($attachedArticles);
    usort(
        $attachedChronicleArticles,
        static function (array $left, array $right) use ($articleCreationTimestamp): int {
            $comparison = $articleCreationTimestamp($right) <=> $articleCreationTimestamp($left);
            if ($comparison !== 0) {
                return $comparison;
            }

            return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        }
    );

    if ($attachedChronicleArticles === []) {
        $attachedChronicleArticles = $attachedArticles;
    }

    $hasOpenArticleMatch = false;
    if ($openArticleSlug !== '') {
        foreach ($attachedChronicleArticles as $articleCandidate) {
            if (!is_array($articleCandidate)) {
                continue;
            }

            if (trim((string) ($articleCandidate['slug'] ?? '')) === $openArticleSlug) {
                $hasOpenArticleMatch = true;
                break;
            }
        }
    }

    ob_start();
    ?>
    <section class="blog-list page-attached-articles" aria-labelledby="page-attached-articles-title">
      <h2 id="page-attached-articles-title"><?php echo htmlspecialchars(t('TXT_CHRONICLE_TITLE'), ENT_QUOTES, 'UTF-8'); ?></h2>
      <?php if (is_string($blogReturnUrl) && $blogReturnUrl !== ''): ?>
      <p class="blog-card-filter-meta">
        <a class="blog-filter-chip" href="<?php echo htmlspecialchars($blogReturnUrl, ENT_QUOTES, 'UTF-8'); ?>">
          <?php echo htmlspecialchars($translate('TXT_BLOG_RETURN_TO_FILTERED_RESULTS', 'Retour aux résultats filtrés'), ENT_QUOTES, 'UTF-8'); ?>
        </a>
      </p>
      <?php endif; ?>
      <div class="page-attached-articles-accordion">
        <?php foreach ($attachedChronicleArticles as $articleIndex => $article): ?>
        <?php
        $title = (string) ($article['title'] ?? t('TXT_BLOG_NO_TITLE'));
        $slug = trim((string) ($article['slug'] ?? ''));
        $date = trim((string) ($article['date'] ?? ''));
        $category = trim((string) ($article['category'] ?? ''));
        $content = trim((string) ($article['content'] ?? ''));
        $excerpt = trim((string) ($article['excerpt'] ?? ''));
        $featuredImage = $resolveFeaturedImage($article);
        $tags = array_values(array_filter(
            array_map('strval', is_array($article['tags'] ?? null) ? $article['tags'] : []),
            static fn (string $tag): bool => trim($tag) !== ''
        ));
        if ($content === '' && $excerpt === '') {
            $excerpt = trim(strip_tags((string) ($article['content'] ?? '')));
            $excerpt = function_exists('mb_substr') ? mb_substr($excerpt, 0, 420) : substr($excerpt, 0, 420);
        }
        $shouldOpen = $openArticleSlug !== ''
            ? ($slug !== '' && $slug === $openArticleSlug)
            : $articleIndex === 0;

        if (!$hasOpenArticleMatch && $openArticleSlug !== '') {
            $shouldOpen = $articleIndex === 0;
        }
        ?>
        <details id="attached-article-<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>" class="page-attached-article" <?php echo $shouldOpen ? 'open' : ''; ?>>
          <summary class="page-attached-article-summary">
            <span class="page-attached-article-summary-main">
              <span class="page-attached-article-summary-title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></span>
              <span class="page-attached-article-summary-meta">
                <?php if ($date !== ''): ?>
                <span><?php echo htmlspecialchars($date, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
                <?php if ($category !== ''): ?>
                <?php if ($date !== ''): ?><span aria-hidden="true">•</span><?php endif; ?>
                <span><?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
              </span>
            </span>
          </summary>
          <div class="page-attached-article-panel">
            <?php if ($featuredImage !== null && $featuredImage['src'] !== ''): ?>
            <figure class="blog-card-media page-attached-article-media">
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
              <?php if ($featuredImage['caption'] !== ''): ?>
              <figcaption><?php echo htmlspecialchars((string) $featuredImage['caption'], ENT_QUOTES, 'UTF-8'); ?></figcaption>
              <?php endif; ?>
            </figure>
            <?php endif; ?>
            <?php if ($category !== '' || $tags !== []): ?>
            <p class="blog-card-filter-meta">
              <?php if ($category !== ''): ?>
              <a class="blog-filter-chip" href="<?php echo htmlspecialchars($buildBlogFilterUrl($category, null), ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars(t('TXT_BLOG_FILTER_BY_CATEGORY'), ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>
              </a>
              <?php endif; ?>
              <?php foreach ($tags as $tag): ?>
              <a class="blog-filter-chip" href="<?php echo htmlspecialchars($buildBlogFilterUrl(null, $tag), ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars(t('TXT_BLOG_FILTER_BY_TAG'), ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?>
              </a>
              <?php endforeach; ?>
            </p>
            <?php endif; ?>
            <?php if ($content !== ''): ?>
            <article class="blog-article-body">
              <?php echo (string) $article['content']; ?>
            </article>
            <?php elseif ($excerpt !== ''): ?>
            <p class="blog-card-excerpt"><?php echo htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
          </div>
        </details>
        <?php endforeach; ?>
      </div>
    </section>
    <?php

    $existingAfterBody = (string) ($blocks['EditRegion4'] ?? '');
    $blocks['EditRegion4'] = $existingAfterBody . ob_get_clean();
}

$pageRoute = normalize_public_route((string) ($page['route'] ?? '')) ?? '';
$instagramConfig = app_config('site.instagram', []);
$instagramView = null;

if (
    $pageRoute === '/'
    && is_array($instagramConfig)
    && !empty($instagramConfig['enabled'])
) {
    $instagramView = instagram_feed_service()->resolveFeed($instagramConfig);
}

if (is_array($instagramView) && !empty($instagramView['enabled'])) {
    $instagramPosts = is_array($instagramView['posts'] ?? null) ? $instagramView['posts'] : [];
    $instagramRenderablePosts = array_values(array_filter(
        $instagramPosts,
        static function ($post): bool {
            if (!is_array($post)) {
                return false;
            }

            return trim((string) ($post['permalink'] ?? '')) !== ''
                && trim((string) ($post['imageUrl'] ?? '')) !== '';
        }
    ));
    $instagramProfileUrl = trim((string) ($instagramView['profileUrl'] ?? ''));
    $instagramUsername = trim((string) ($instagramView['username'] ?? ''));
    $instagramRotationMs = max(2500, min(30000, (int) ($instagramView['rotationIntervalMs'] ?? 5500)));

    $formatInstagramDate = static function (string $value): string {
        $timestamp = strtotime($value);

        return is_int($timestamp) ? date('d/m/Y', $timestamp) : '';
    };

    ob_start();
    ?>
    <section class="instagram-feed-home" aria-labelledby="instagram-feed-home-title" data-instagram-feed data-rotation-ms="<?php echo (int) $instagramRotationMs; ?>">
      <div class="instagram-feed-home-header">
        <h2 id="instagram-feed-home-title"><?php echo htmlspecialchars(t('TXT_INSTAGRAM_LATEST_POSTS'), ENT_QUOTES, 'UTF-8'); ?></h2>
        <?php if ($instagramProfileUrl !== '' && $instagramUsername !== ''): ?>
        <a class="instagram-feed-home-profile" href="<?php echo htmlspecialchars($instagramProfileUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
          @<?php echo htmlspecialchars($instagramUsername, ENT_QUOTES, 'UTF-8'); ?>
        </a>
        <?php endif; ?>
      </div>

      <?php if ($instagramRenderablePosts === []): ?>
      <p class="instagram-feed-home-empty"><?php echo htmlspecialchars(t('TXT_INSTAGRAM_EMPTY'), ENT_QUOTES, 'UTF-8'); ?></p>
      <?php else: ?>
      <div class="instagram-feed-carousel">
        <div class="instagram-feed-carousel-viewport">
          <div class="instagram-feed-carousel-track" data-instagram-track>
            <?php foreach ($instagramRenderablePosts as $post): ?>
            <?php
            $postPermalink = trim((string) ($post['permalink'] ?? ''));
            $postImage = trim((string) ($post['imageUrl'] ?? ''));
            $postCaption = trim((string) ($post['caption'] ?? ''));
            $postDate = $formatInstagramDate((string) ($post['timestamp'] ?? ''));
            $postImageWidth = max(120, min(4096, (int) ($post['imageWidth'] ?? 1080)));
            $postImageHeight = max(120, min(4096, (int) ($post['imageHeight'] ?? 1080)));
            ?>
            <article class="instagram-feed-card" data-instagram-item>
              <a href="<?php echo htmlspecialchars($postPermalink, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                <img
                  src="<?php echo htmlspecialchars($postImage, ENT_QUOTES, 'UTF-8'); ?>"
                  alt="<?php echo htmlspecialchars($postCaption !== '' ? $postCaption : t('TXT_INSTAGRAM_POST_ALT_FALLBACK'), ENT_QUOTES, 'UTF-8'); ?>"
                  width="<?php echo (int) $postImageWidth; ?>"
                  height="<?php echo (int) $postImageHeight; ?>"
                  loading="lazy"
                  decoding="async"
                  fetchpriority="low"
                  referrerpolicy="no-referrer"
                />
                <div class="instagram-feed-card-body">
                  <?php if ($postDate !== ''): ?>
                  <p class="instagram-feed-card-date"><?php echo htmlspecialchars($postDate, ENT_QUOTES, 'UTF-8'); ?></p>
                  <?php endif; ?>
                  <p class="instagram-feed-card-caption">
                    <?php echo htmlspecialchars($postCaption !== '' ? $postCaption : t('TXT_INSTAGRAM_POST_CAPTION_FALLBACK'), ENT_QUOTES, 'UTF-8'); ?>
                  </p>
                </div>
              </a>
            </article>
            <?php endforeach; ?>
          </div>
        </div>

        <?php if (count($instagramRenderablePosts) > 1): ?>
        <div class="instagram-feed-carousel-controls">
          <button type="button" class="instagram-feed-carousel-control" data-instagram-prev aria-label="<?php echo htmlspecialchars(t('TXT_INSTAGRAM_PREV_ARIA'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(t('TXT_INSTAGRAM_PREV'), ENT_QUOTES, 'UTF-8'); ?></button>
          <div class="instagram-feed-carousel-dots">
            <?php foreach ($instagramRenderablePosts as $postIndex => $unusedPost): ?>
            <button
              type="button"
              class="instagram-feed-carousel-dot<?php echo $postIndex === 0 ? ' is-active' : ''; ?>"
              data-instagram-dot="<?php echo (int) $postIndex; ?>"
              aria-label="<?php echo htmlspecialchars(sprintf((string) t('TXT_INSTAGRAM_DOT_ARIA'), (int) ($postIndex + 1)), ENT_QUOTES, 'UTF-8'); ?>"
              <?php echo $postIndex === 0 ? 'aria-current="true"' : ''; ?>
            ></button>
            <?php endforeach; ?>
          </div>
          <button type="button" class="instagram-feed-carousel-control" data-instagram-next aria-label="<?php echo htmlspecialchars(t('TXT_INSTAGRAM_NEXT_ARIA'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(t('TXT_INSTAGRAM_NEXT'), ENT_QUOTES, 'UTF-8'); ?></button>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </section>
    <?php

    $existingPostscript = (string) ($blocks['EditRegion11'] ?? '');
    $blocks['EditRegion11'] = $existingPostscript . ob_get_clean();
}

$pageTilesHtml = $pageSlug !== '' ? page_tile_renderer()->renderAfterBody($pageSlug, $language) : '';
if ($pageTilesHtml !== '') {
    $existingAfterBody = (string) ($blocks['EditRegion4'] ?? '');
    $blocks['EditRegion4'] = $existingAfterBody . $pageTilesHtml;
}

$pageMetaDescription = !empty($page['meta']['description']) ? (string) $page['meta']['description'] : null;
$pageMetaImage = null;
$pageMetaImagePayload = \Caramagnols\Admin\AdminEditorialImageService::sanitizeImageMetadata(
    is_array($page['meta']['image'] ?? null) ? $page['meta']['image'] : []
);
$pageMetaImageAlt = '';
if (is_array($pageMetaImagePayload) && trim((string) ($pageMetaImagePayload['src'] ?? '')) !== '') {
    $pageMetaImage = $toAbsoluteImageUrl((string) $pageMetaImagePayload['src']);
    $pageMetaImageAlt = trim((string) ($pageMetaImagePayload['alt'] ?? ''));
}
// Le layout est rendu par FrontController::pageResponse().
