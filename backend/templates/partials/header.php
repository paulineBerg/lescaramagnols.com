<?php
// Legacy partial: keep backward compatibility, but avoid duplicate <title> tags
// when scripts_head.php already rendered the canonical title.
if (defined('CARAMAGNOLS_TITLE_TAG_RENDERED')) {
    return;
}

define('CARAMAGNOLS_TITLE_TAG_RENDERED', true);
$legacyPageTitle = $pageTitle ?? $page2 ?? t('TXT_PAGE_DEFAULT_TITLE');
?>
<title><?= htmlspecialchars((string) $legacyPageTitle, ENT_QUOTES, 'UTF-8') ?></title>
