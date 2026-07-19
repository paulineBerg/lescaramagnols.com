<?php
$pageTitle = $pageTitle ?? t('TXT_PAGE_DEFAULT_TITLE');
ob_start();
?>

<!-- PAGE CONTENT START -->
<!-- Formulaire de login -->
<!-- PAGE CONTENT END -->

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>
