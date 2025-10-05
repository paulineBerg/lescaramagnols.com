<?php
$pageTitle = $pageTitle ?? 'Les Caramagnols';
ob_start();
?>

<!-- PAGE CONTENT START -->
<?php
$file = $_GET['file'] ?? '';
$path = __DIR__ . '/../../../data/blog/' . basename($file);
$data = file_exists($path) ? json_decode(file_get_contents($path), true) : [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $data = [
    "title" => $_POST['title'],
    "slug" => $_POST['slug'],
    "author" => $_POST['author'],
    "date" => $_POST['date'],
    "content" => $_POST['content'],
    "category" => $_POST['category'],
    "status" => $_POST['status']
  ];
  file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
  header("Location: ?page=admin/articles");
  exit;
}
?>
<div class="container mt-4">
  <h2>Modifier Article</h2>
  <form method="post">
    <input name="title" class="form-control mb-2" placeholder="Titre" value="<?= htmlspecialchars($data['title'] ?? '') ?>">
    <input name="slug" class="form-control mb-2" placeholder="Slug" value="<?= htmlspecialchars($data['slug'] ?? '') ?>">
    <input name="author" class="form-control mb-2" placeholder="Auteur" value="<?= htmlspecialchars($data['author'] ?? '') ?>">
    <input name="date" type="date" class="form-control mb-2" value="<?= htmlspecialchars($data['date'] ?? '') ?>">
    <input name="category" class="form-control mb-2" placeholder="Catégorie" value="<?= htmlspecialchars($data['category'] ?? '') ?>">
    <select name="status" class="form-control mb-2">
      <option value="draft" <?= ($data['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Brouillon</option>
      <option value="published" <?= ($data['status'] ?? '') === 'published' ? 'selected' : '' ?>>Publié</option>
    </select>
    <textarea name="content" class="form-control mb-2" rows="8" placeholder="Contenu HTML"><?= htmlspecialchars($data['content'] ?? '') ?></textarea>
    <button class="btn btn-primary" type="submit">Enregistrer</button>
  </form>
</div>
<!-- PAGE CONTENT END -->

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>
