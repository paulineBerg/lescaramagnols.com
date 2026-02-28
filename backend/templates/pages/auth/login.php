<?php
$pageTitle = $pageTitle ?? 'Les Caramagnols';
ob_start();
?>

<!-- PAGE CONTENT START -->
<?php // Formulaire de login
<!-- PAGE CONTENT END -->

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>
