<?php
$summary = is_array($viewModel['taxSummary'] ?? null) ? $viewModel['taxSummary'] : [];
$documents = is_array($summary['missingDocuments'] ?? null) ? $summary['missingDocuments'] : [];
$year = is_numeric($summary['year'] ?? null) ? (int) $summary['year'] : (int) date('Y');
$urls = is_array($viewModel['taxUrls'] ?? null) ? $viewModel['taxUrls'] : [];
$csrfToken = is_string($viewModel['taxCsrfToken'] ?? null) ? (string) $viewModel['taxCsrfToken'] : '';
$notice = is_string($viewModel['taxNotice'] ?? null) ? (string) $viewModel['taxNotice'] : '';
$error = is_string($viewModel['taxError'] ?? null) ? (string) $viewModel['taxError'] : '';
$mailDefaults = is_array($viewModel['taxMailDefaults'] ?? null) ? $viewModel['taxMailDefaults'] : [];
?>
<section>
  <p class="muted"><a href="<?php echo htmlspecialchars((string) ($urls['year'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Retour synthese <?php echo htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8'); ?></a></p>
  <?php if ($notice !== ''): ?><p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <section class="card">
    <h2>Envoyer le PDF fiscal</h2>
    <form method="post" action="<?php echo htmlspecialchars((string) ($urls['documents'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
      <input type="hidden" name="action" value="email_tax_pdf" />
      <label>Email destinataire
        <input type="email" name="recipient_email" maxlength="190" value="<?php echo htmlspecialchars((string) ($mailDefaults['recipientEmail'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
      </label>
      <label>Objet
        <input type="text" name="subject" maxlength="180" value="<?php echo htmlspecialchars((string) ($mailDefaults['subject'] ?? 'Aide impôts - document PDF'), ENT_QUOTES, 'UTF-8'); ?>" />
      </label>
      <label>Message
        <textarea name="message" maxlength="4000"><?php echo htmlspecialchars((string) ($mailDefaults['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
      </label>
      <button type="submit">Envoyer le PDF</button>
    </form>
  </section>

  <section class="card">
    <h2>Documents et justificatifs</h2>
    <?php if ($documents === []): ?><p class="notice notice-success">Aucun justificatif manquant detecte.</p><?php else: ?>
      <ul>
        <?php foreach ($documents as $document): ?>
          <?php if (!is_array($document)) { continue; } ?>
          <li><?php echo htmlspecialchars((string) ($document['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> — <?php echo htmlspecialchars((string) ($document['sourceReference'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</section>
