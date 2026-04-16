<?php

$article = $GLOBALS['currentBlogArticle'] ?? null;

if (!is_array($article)) {
    http_response_code(404);
    require TEMPLATES_PATH . '/pages/404.php';
    return;
}

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
$articleLanguage = (string) ($article['lang'] ?? (defined('CURRENT_LANG') ? CURRENT_LANG : app_config('default_lang', 'fr')));
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
<?php if ($parentArticle !== null || ($article['category'] ?? '') !== '' || ($article['tags'] ?? []) !== []): ?>
  <aside class="content-callout">
    <h2 class="content-callout-title"><?php echo htmlspecialchars(t('TXT_BLOG_METADATA'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <dl class="content-facts">
      <?php if ($parentArticle !== null): ?>
        <div class="content-facts-item">
          <dt><?php echo htmlspecialchars(t('TXT_BLOG_PARENT_ARTICLE'), ENT_QUOTES, 'UTF-8'); ?></dt>
          <dd>
            <a href="<?php echo htmlspecialchars(app_url(CURRENT_LANG . '/blog/article/' . rawurlencode((string) ($parentArticle['slug'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo htmlspecialchars((string) ($parentArticle['title'] ?? ($parentArticle['slug'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
            </a>
          </dd>
        </div>
      <?php endif; ?>
      <?php if (($article['category'] ?? '') !== ''): ?>
        <?php $articleCategory = trim((string) $article['category']); ?>
        <div class="content-facts-item">
          <dt><?php echo htmlspecialchars(t('TXT_BLOG_CATEGORY'), ENT_QUOTES, 'UTF-8'); ?></dt>
          <dd>
            <a class="blog-filter-chip" href="<?php echo htmlspecialchars($buildBlogFilterUrl($articleCategory, null), ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo htmlspecialchars($articleCategory, ENT_QUOTES, 'UTF-8'); ?>
            </a>
          </dd>
        </div>
      <?php endif; ?>
      <?php if (is_array($article['tags'] ?? null) && $article['tags'] !== []): ?>
        <div class="content-facts-item">
          <dt><?php echo htmlspecialchars(t('TXT_BLOG_TAGS'), ENT_QUOTES, 'UTF-8'); ?></dt>
          <dd class="blog-filter-chip-list">
            <?php foreach (array_values(array_filter(array_map('strval', $article['tags']), static fn (string $tag): bool => trim($tag) !== '')) as $tag): ?>
              <a class="blog-filter-chip" href="<?php echo htmlspecialchars($buildBlogFilterUrl(null, $tag), ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?>
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
  <?php echo (string) ($article['content'] ?? ''); ?>
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
    <?php
    $childUrl = app_url(CURRENT_LANG . '/blog/article/' . rawurlencode((string) ($childArticle['slug'] ?? '')));
    ?>
    <li class="blog-child-list-item">
      <a class="blog-child-link" href="<?php echo htmlspecialchars($childUrl, ENT_QUOTES, 'UTF-8'); ?>">
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

<?php if ($discussionsEnabled): ?>
<section class="content-callout blog-discussions" id="discussion-form" aria-labelledby="blog-discussions-title">
  <h2 id="blog-discussions-title" class="content-callout-title"><?php echo htmlspecialchars(t('TXT_BLOG_DISCUSSIONS'), ENT_QUOTES, 'UTF-8'); ?></h2>

  <?php if (is_array($discussionFlash) && trim((string) ($discussionFlash['message'] ?? '')) !== ''): ?>
  <p class="blog-discussion-notice blog-discussion-notice-<?php echo (($discussionFlash['type'] ?? 'error') === 'success') ? 'success' : 'error'; ?>">
    <?php echo htmlspecialchars((string) $discussionFlash['message'], ENT_QUOTES, 'UTF-8'); ?>
  </p>
  <?php endif; ?>

  <?php if ($approvedDiscussions === []): ?>
  <p class="blog-discussion-empty"><?php echo htmlspecialchars(t('TXT_BLOG_NO_VALIDATED_MESSAGES'), ENT_QUOTES, 'UTF-8'); ?></p>
  <?php else: ?>
  <ul class="blog-discussion-list">
    <?php foreach ($approvedDiscussions as $discussion): ?>
    <li class="blog-discussion-item">
      <p class="blog-discussion-meta">
        <strong><?php echo htmlspecialchars((string) ($discussion['author'] ?? t('TXT_BLOG_READER')), ENT_QUOTES, 'UTF-8'); ?></strong>
        <span>·</span>
        <time datetime="<?php echo htmlspecialchars((string) ($discussion['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
          <?php echo htmlspecialchars($formatDiscussionDate((string) ($discussion['created_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
        </time>
      </p>
      <div class="blog-discussion-content">
        <?php echo (string) ($discussion['content'] ?? ''); ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <?php if ($discussionRequireAccount): ?>
  <p class="blog-discussion-intro"><?php echo htmlspecialchars(t('TXT_BLOG_DISCUSSION_ACCOUNT_REQUIRED'), ENT_QUOTES, 'UTF-8'); ?></p>
  <?php else: ?>
  <div class="blog-discussion-compose">
    <p class="blog-discussion-intro blog-discussion-intro-compose"><?php echo htmlspecialchars(t('TXT_BLOG_DISCUSSION_MODERATION_NOTICE'), ENT_QUOTES, 'UTF-8'); ?></p>
    <form class="blog-discussion-form" method="post" action="<?php echo htmlspecialchars($discussionSubmitPath, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="article_slug" value="<?php echo htmlspecialchars($articleSlug, ENT_QUOTES, 'UTF-8'); ?>" />
      <input type="hidden" name="article_lang" value="<?php echo htmlspecialchars($articleLanguage, ENT_QUOTES, 'UTF-8'); ?>" />
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($discussionCsrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
      <input type="hidden" name="form_nonce" value="<?php echo htmlspecialchars($discussionNonce, ENT_QUOTES, 'UTF-8'); ?>" />
      <div class="blog-discussion-honeypot" aria-hidden="true">
        <label for="discussion-hp-<?php echo htmlspecialchars($honeypotField, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(t('TXT_BLOG_DISCUSSION_HONEYPOT_LABEL'), ENT_QUOTES, 'UTF-8'); ?></label>
        <input id="discussion-hp-<?php echo htmlspecialchars($honeypotField, ENT_QUOTES, 'UTF-8'); ?>" type="text" name="<?php echo htmlspecialchars($honeypotField, ENT_QUOTES, 'UTF-8'); ?>" value="" tabindex="-1" autocomplete="off" />
      </div>

      <div class="blog-discussion-grid">
        <div class="field">
          <label for="discussion-author"><?php echo htmlspecialchars(t('TXT_BLOG_DISCUSSION_NAME'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="discussion-author" type="text" name="author" maxlength="120" required value="<?php echo htmlspecialchars((string) ($discussionOldInput['author'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
        </div>
        <div class="field">
          <label for="discussion-email"><?php echo htmlspecialchars(t('TXT_BLOG_DISCUSSION_EMAIL'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="discussion-email" type="email" name="email" maxlength="180" required value="<?php echo htmlspecialchars((string) ($discussionOldInput['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
        </div>
      </div>

      <div class="field">
        <label for="discussion-content"><?php echo htmlspecialchars(t('TXT_BLOG_DISCUSSION_MESSAGE'), ENT_QUOTES, 'UTF-8'); ?></label>
        <textarea id="discussion-content" name="content" rows="6" maxlength="2000" required><?php echo htmlspecialchars((string) ($discussionOldInput['content'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
      </div>

      <?php if ($recaptchaEnabled): ?>
      <div class="blog-discussion-recaptcha">
        <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($recaptchaSiteKey, ENT_QUOTES, 'UTF-8'); ?>"></div>
        <small><?php echo htmlspecialchars(t('TXT_BLOG_DISCUSSION_RECAPTCHA_NOTICE'), ENT_QUOTES, 'UTF-8'); ?></small>
      </div>
      <?php endif; ?>

      <div class="actions-inline">
        <button type="submit"><?php echo htmlspecialchars(t('TXT_BLOG_DISCUSSION_SUBMIT'), ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
    </form>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php
$blocks['EditRegion4'] = ob_get_clean();

require __DIR__ . '/../../partials/layout.php';
