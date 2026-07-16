<?php

$pageTitle = t('TXT_BLOG_PROPOSE_TITLE') . ' · ' . t('TXT_BLOG_PAGE_LABEL');

ob_start();
?>
<div class="content-heading">
  <div>
    <h1><?php echo htmlspecialchars(t('TXT_BLOG_PROPOSE_TITLE'), ENT_QUOTES, 'UTF-8'); ?></h1>
    <p class="content-heading-subtitle"><?php echo htmlspecialchars(t('TXT_BLOG_PROPOSE_SUBTITLE'), ENT_QUOTES, 'UTF-8'); ?></p>
    <div class="content-heading-lead">
      <p><?php echo htmlspecialchars(t('TXT_BLOG_PROPOSE_LEAD'), ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
  </div>
</div>
<?php
$blocks['EditRegion1'] = ob_get_clean();

ob_start();
?>
<aside class="content-callout">
  <h2 class="content-callout-title"><?php echo htmlspecialchars(t('TXT_BLOG_PROPOSE_ALT_TITLE'), ENT_QUOTES, 'UTF-8'); ?></h2>
  <p><?php echo htmlspecialchars(t('TXT_BLOG_PROPOSE_ALT_TEXT'), ENT_QUOTES, 'UTF-8'); ?></p>
</aside>
<?php
$blocks['EditRegion3'] = ob_get_clean();
// Le layout global est rendu par FrontController::pageResponse().
