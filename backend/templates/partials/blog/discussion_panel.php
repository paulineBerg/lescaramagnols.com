<?php

$discussionAnchorId = trim((string) ($discussionAnchorId ?? 'discussion-form'));
if ($discussionAnchorId === '') {
    $discussionAnchorId = 'discussion-form';
}

$discussionTitleId = trim((string) ($discussionTitleId ?? 'blog-discussions-title'));
if ($discussionTitleId === '') {
    $discussionTitleId = 'blog-discussions-title';
}

$discussionFieldPrefix = trim((string) ($discussionFieldPrefix ?? 'discussion'));
$discussionFieldPrefix = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $discussionFieldPrefix) ?? 'discussion';
$discussionFieldPrefix = trim($discussionFieldPrefix, '-_');
if ($discussionFieldPrefix === '') {
    $discussionFieldPrefix = 'discussion';
}

$discussionSubmitPath = trim((string) ($discussionSubmitPath ?? ''));
$articleSlug = trim((string) ($articleSlug ?? ''));
$articleLanguage = trim((string) ($articleLanguage ?? ''));
$discussionCsrfToken = (string) ($discussionCsrfToken ?? '');
$discussionNonce = (string) ($discussionNonce ?? '');
$honeypotField = trim((string) ($honeypotField ?? 'website'));
$discussionRequireAccount = (bool) ($discussionRequireAccount ?? false);
$recaptchaEnabled = (bool) ($recaptchaEnabled ?? false);
$recaptchaSiteKey = trim((string) ($recaptchaSiteKey ?? ''));
$returnToDiscussionUrl = trim((string) ($returnToDiscussionUrl ?? ''));
$approvedDiscussions = is_array($approvedDiscussions ?? null) ? $approvedDiscussions : [];
$discussionFlash = is_array($discussionFlash ?? null) ? $discussionFlash : null;
$discussionOldInput = is_array($discussionOldInput ?? null)
    ? $discussionOldInput
    : ['author' => '', 'email' => '', 'content' => ''];

if (!isset($formatDiscussionDate) || !is_callable($formatDiscussionDate)) {
    $formatDiscussionDate = static function (string $value): string {
        $timestamp = strtotime($value);

        return is_int($timestamp) ? date('d/m/Y H:i', $timestamp) : $value;
    };
}
?>
<section class="content-callout blog-discussions" id="<?php echo htmlspecialchars($discussionAnchorId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="<?php echo htmlspecialchars($discussionTitleId, ENT_QUOTES, 'UTF-8'); ?>">
  <h2 id="<?php echo htmlspecialchars($discussionTitleId, ENT_QUOTES, 'UTF-8'); ?>" class="content-callout-title"><?php echo htmlspecialchars(t('TXT_BLOG_DISCUSSIONS'), ENT_QUOTES, 'UTF-8'); ?></h2>

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
          <?php echo htmlspecialchars((string) $formatDiscussionDate((string) ($discussion['created_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
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
    <form class="blog-discussion-form" method="post" action="<?php echo htmlspecialchars($discussionSubmitPath, ENT_QUOTES, 'UTF-8'); ?>" data-discussion-form>
      <input type="hidden" name="article_slug" value="<?php echo htmlspecialchars($articleSlug, ENT_QUOTES, 'UTF-8'); ?>" />
      <input type="hidden" name="article_lang" value="<?php echo htmlspecialchars($articleLanguage, ENT_QUOTES, 'UTF-8'); ?>" />
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($discussionCsrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
      <input type="hidden" name="form_nonce" value="<?php echo htmlspecialchars($discussionNonce, ENT_QUOTES, 'UTF-8'); ?>" />
      <?php if ($returnToDiscussionUrl !== ''): ?>
      <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($returnToDiscussionUrl, ENT_QUOTES, 'UTF-8'); ?>" />
      <?php endif; ?>
      <div class="blog-discussion-honeypot" aria-hidden="true">
        <label for="<?php echo htmlspecialchars($discussionFieldPrefix . '-hp-' . $honeypotField, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(t('TXT_BLOG_DISCUSSION_HONEYPOT_LABEL'), ENT_QUOTES, 'UTF-8'); ?></label>
        <input id="<?php echo htmlspecialchars($discussionFieldPrefix . '-hp-' . $honeypotField, ENT_QUOTES, 'UTF-8'); ?>" type="text" name="<?php echo htmlspecialchars($honeypotField, ENT_QUOTES, 'UTF-8'); ?>" value="" tabindex="-1" autocomplete="off" />
      </div>

      <div class="blog-discussion-grid">
        <div class="field">
          <label for="<?php echo htmlspecialchars($discussionFieldPrefix . '-author', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(t('TXT_BLOG_DISCUSSION_NAME'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="<?php echo htmlspecialchars($discussionFieldPrefix . '-author', ENT_QUOTES, 'UTF-8'); ?>" type="text" name="author" maxlength="120" required value="<?php echo htmlspecialchars((string) ($discussionOldInput['author'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
        </div>
        <div class="field">
          <label for="<?php echo htmlspecialchars($discussionFieldPrefix . '-email', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(t('TXT_BLOG_DISCUSSION_EMAIL'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="<?php echo htmlspecialchars($discussionFieldPrefix . '-email', ENT_QUOTES, 'UTF-8'); ?>" type="email" name="email" maxlength="180" required value="<?php echo htmlspecialchars((string) ($discussionOldInput['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
        </div>
      </div>

      <div class="field">
        <label for="<?php echo htmlspecialchars($discussionFieldPrefix . '-content', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(t('TXT_BLOG_DISCUSSION_MESSAGE'), ENT_QUOTES, 'UTF-8'); ?></label>
        <textarea id="<?php echo htmlspecialchars($discussionFieldPrefix . '-content', ENT_QUOTES, 'UTF-8'); ?>" name="content" rows="6" maxlength="2000" required><?php echo htmlspecialchars((string) ($discussionOldInput['content'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
      </div>

      <?php if ($recaptchaEnabled): ?>
      <div class="blog-discussion-recaptcha">
        <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($recaptchaSiteKey, ENT_QUOTES, 'UTF-8'); ?>"></div>
        <small><?php echo htmlspecialchars(t('TXT_BLOG_DISCUSSION_RECAPTCHA_NOTICE'), ENT_QUOTES, 'UTF-8'); ?></small>
      </div>
      <?php endif; ?>

      <p class="blog-discussion-notice blog-discussion-notice-info" data-discussion-submit-feedback hidden role="status" aria-live="polite">
        <?php echo htmlspecialchars(t('TXT_BLOG_DISCUSSION_SUBMIT_PENDING_MESSAGE'), ENT_QUOTES, 'UTF-8'); ?>
      </p>

      <div class="actions-inline">
        <button
          type="submit"
          data-discussion-submit-button
          data-submit-label-idle="<?php echo htmlspecialchars(t('TXT_BLOG_DISCUSSION_SUBMIT'), ENT_QUOTES, 'UTF-8'); ?>"
          data-submit-label-pending="<?php echo htmlspecialchars(t('TXT_BLOG_DISCUSSION_SUBMIT_PENDING_LABEL'), ENT_QUOTES, 'UTF-8'); ?>"
        ><?php echo htmlspecialchars(t('TXT_BLOG_DISCUSSION_SUBMIT'), ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
    </form>
  </div>
  <?php endif; ?>
</section>
