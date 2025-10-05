<?php
$pageTitle = $pageTitle ?? 'Les Caramagnols';
ob_start();
?>

<!-- PAGE CONTENT START -->
<?php // Formulaire d'inscription
<!-- PAGE CONTENT END -->

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>
