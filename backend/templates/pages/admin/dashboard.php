<?php
$pageTitle = $pageTitle ?? 'Les Caramagnols';
ob_start();
?>

<!-- PAGE CONTENT START -->
<?php include '../../partials/header.php'; ?>
<?php include '../../partials/nav.php'; ?>
<div class="container mt-4">
  <h1 class="mb-4">Tableau de bord Admin</h1>
  <div class="row">
    <div class="col-md-4"><a href="?page=admin/articles" class="btn btn-primary w-100 mb-2">Gérer les Articles</a></div>
    <div class="col-md-4"><a href="?page=admin/moderate" class="btn btn-warning w-100 mb-2">Modérer les Commentaires</a></div>
    <div class="col-md-4"><a href="?page=admin/propositions" class="btn btn-success w-100 mb-2">Propositions d'Articles</a></div>
  </div>
</div>
<?php include '../../partials/footer.php'; ?>
<!-- PAGE CONTENT END -->

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>
