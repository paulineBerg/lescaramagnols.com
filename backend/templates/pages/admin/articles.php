<?php
$pageTitle = $pageTitle ?? 'Les Caramagnols';
ob_start();
?>

<!-- PAGE CONTENT START -->
<?php
$dir = __DIR__ . '/../../../data/blog/';
$files = glob($dir . '*.json');
?>
<div class="container mt-4">
  <h2>Articles JSON</h2>
  <table class="table table-bordered table-striped">
    <thead><tr><th>Titre</th><th>Langue</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($files as $file): $data = json_decode(file_get_contents($file), true); ?>
      <tr>
        <td><?= htmlspecialchars($data['title']) ?></td>
        <td><?= strtoupper(pathinfo($file, PATHINFO_FILENAME)) ?></td>
        <td>
          <a href="?page=admin/edit_article&file=<?= basename($file) ?>" class="btn btn-sm btn-secondary">Modifier</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<!-- PAGE CONTENT END -->

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>
