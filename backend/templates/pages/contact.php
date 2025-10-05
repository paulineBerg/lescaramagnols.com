<?php
$pageTitle = $pageTitle ?? 'Contact';
ob_start();
?>
<?php
$sent = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $token = $_POST['token'] ?? '';

    if ($token !== $_SESSION['token']) {
        $error = 'Token invalide.';
    } elseif (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$message) {
        $error = 'Tous les champs sont obligatoires.';
    } else {
        require_once __DIR__ . '/../../../core/mailer.php';
        $subject = "Nouveau message de contact - Les Caramagnols";
        $htmlMessage = "<p><strong>Nom:</strong> {$name}</p>
                        <p><strong>Email:</strong> {$email}</p>
                        <p><strong>Message:</strong><br>{$message}</p>";
        $sent = send_notification_email('contact@lescaramagnols.com', $subject, $htmlMessage);
        if (!$sent) $error = 'Erreur lors de l'envoi du message.';
    }
}

$_SESSION['token'] = bin2hex(random_bytes(32));
?>
<div class="container mt-4">
  <h1>Contact</h1>
  <?php if ($sent): ?>
    <div class="alert alert-success">Message envoyé avec succès.</div>
  <?php elseif ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="post">
    <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
    <div class="mb-3">
      <label>Nom</label>
      <input name="name" class="form-control" required>
    </div>
    <div class="mb-3">
      <label>Email</label>
      <input name="email" type="email" class="form-control" required>
    </div>
    <div class="mb-3">
      <label>Message</label>
      <textarea name="message" class="form-control" rows="5" required></textarea>
    </div>
    <button class="btn btn-primary">Envoyer</button>
  </form>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>
