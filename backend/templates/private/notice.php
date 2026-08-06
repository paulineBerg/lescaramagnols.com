<?php
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
?>
<section>
  <div class="card">
    <h2><?php echo htmlspecialchars((string) ($privateNoticeTitle ?? ''), ENT_QUOTES, 'UTF-8'); ?></h2>
    <p><?php echo htmlspecialchars((string) ($privateNoticeBody ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>

    <?php if (($privateNoticeToken ?? '') !== ''): ?>
      <p class="muted">
        <?php echo htmlspecialchars($translate('TXT_PRIVATE_NOTICE_TOKEN_LABEL', 'Référence token :'), ENT_QUOTES, 'UTF-8'); ?>
        <code><?php echo htmlspecialchars((string) $privateNoticeToken, ENT_QUOTES, 'UTF-8'); ?></code>
      </p>
    <?php endif; ?>

    <?php if (($privateNoticeActionUrl ?? '') !== '' && ($privateNoticeActionLabel ?? '') !== ''): ?>
      <p class="private-actions">
        <a href="<?php echo htmlspecialchars((string) $privateNoticeActionUrl, ENT_QUOTES, 'UTF-8'); ?>">
          <?php echo htmlspecialchars((string) $privateNoticeActionLabel, ENT_QUOTES, 'UTF-8'); ?>
        </a>
      </p>
    <?php endif; ?>
  </div>
</section>
