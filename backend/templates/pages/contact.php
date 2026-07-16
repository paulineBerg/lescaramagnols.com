<?php
$pageTitle = $pageTitle ?? t('TXT_CONTACT_PAGE_TITLE');
ob_start();
?>
<?php
$sent = false;
$error = null;
$csrfScope = 'contact_page_form';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $token = is_string($_POST['token'] ?? null) ? $_POST['token'] : null;

    if (!csrf_validate($token, $csrfScope, true)) {
        $error = t('TXT_CONTACT_TOKEN_INVALID');
    } elseif (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$message) {
        $error = t('TXT_CONTACT_REQUIRED_FIELDS');
    } else {
        require_once __DIR__ . '/../../../core/mailer.php';
        $subject = (string) t('TXT_CONTACT_SUBJECT');
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $htmlMessage = "<p><strong>" . htmlspecialchars((string) t('TXT_CONTACT_NAME'), ENT_QUOTES, 'UTF-8') . ":</strong> {$safeName}</p>
                        <p><strong>" . htmlspecialchars((string) t('TXT_CONTACT_EMAIL'), ENT_QUOTES, 'UTF-8') . ":</strong> {$safeEmail}</p>
                        <p><strong>" . htmlspecialchars((string) t('TXT_CONTACT_MESSAGE'), ENT_QUOTES, 'UTF-8') . ":</strong><br>{$safeMessage}</p>";
        $sent = send_notification_email('contact@lescaramagnols.com', $subject, $htmlMessage);
        if (!$sent) {
            $error = t('TXT_CONTACT_SEND_ERROR');
        }
    }
}

$csrfToken = csrf_token($csrfScope);
?>
<div class="container mt-4">
  <h1><?php echo htmlspecialchars(t('TXT_CONTACT_PAGE_TITLE'), ENT_QUOTES, 'UTF-8'); ?></h1>
  <?php if ($sent): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars(t('TXT_CONTACT_SUCCESS'), ENT_QUOTES, 'UTF-8'); ?></div>
  <?php elseif ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="post">
    <input type="hidden" name="token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <div class="mb-3">
      <label><?php echo htmlspecialchars(t('TXT_CONTACT_NAME'), ENT_QUOTES, 'UTF-8'); ?></label>
      <input name="name" class="form-control" required>
    </div>
    <div class="mb-3">
      <label><?php echo htmlspecialchars(t('TXT_CONTACT_EMAIL'), ENT_QUOTES, 'UTF-8'); ?></label>
      <input name="email" type="email" class="form-control" required>
    </div>
    <div class="mb-3">
      <label><?php echo htmlspecialchars(t('TXT_CONTACT_MESSAGE'), ENT_QUOTES, 'UTF-8'); ?></label>
      <textarea name="message" class="form-control" rows="5" required></textarea>
    </div>
    <button class="btn btn-primary"><?php echo htmlspecialchars(t('TXT_CONTACT_SUBMIT'), ENT_QUOTES, 'UTF-8'); ?></button>
  </form>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../layout.php';
?>
