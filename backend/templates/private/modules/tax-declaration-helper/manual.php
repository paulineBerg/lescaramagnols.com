<?php
$entries = is_array($viewModel['taxManualEntries'] ?? null) ? $viewModel['taxManualEntries'] : [];
$summary = is_array($viewModel['taxSummary'] ?? null) ? $viewModel['taxSummary'] : [];
$year = is_numeric($summary['year'] ?? null) ? (int) $summary['year'] : (int) date('Y');
$locked = !empty($summary['locked']);
$csrfToken = is_string($viewModel['taxCsrfToken'] ?? null) ? (string) $viewModel['taxCsrfToken'] : '';
$notice = is_string($viewModel['taxNotice'] ?? null) ? (string) $viewModel['taxNotice'] : '';
$error = is_string($viewModel['taxError'] ?? null) ? (string) $viewModel['taxError'] : '';
$urls = is_array($viewModel['taxUrls'] ?? null) ? $viewModel['taxUrls'] : [];
?>
<section>
  <p class="muted"><a href="<?php echo htmlspecialchars((string) ($urls['year'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Retour synthese <?php echo htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8'); ?></a></p>
  <?php if ($notice !== ''): ?><p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <section class="card">
    <h2>Ajouter un revenu manuel</h2>
    <?php if ($locked): ?>
      <p class="notice notice-error">Annee verrouillee : modification refusee.</p>
    <?php else: ?>
      <form method="post" action="<?php echo htmlspecialchars((string) ($urls['manual'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
        <label>Libelle <input type="text" name="label" maxlength="160" required /></label>
        <label>Montant <input type="number" name="amount" min="0" step="0.01" required /></label>
        <label>Categorie <input type="text" name="category" maxlength="64" required /></label>
        <label>Statut
          <select name="status">
            <option value="draft">Brouillon</option>
            <option value="validated">Valide</option>
            <option value="cancelled">Annule</option>
          </select>
        </label>
        <label>Notes <textarea name="notes" maxlength="2000"></textarea></label>
        <button type="submit">Ajouter</button>
      </form>
    <?php endif; ?>
  </section>
  <section class="card">
    <h2>Revenus manuels</h2>
    <?php if ($entries === []): ?><p class="muted">Aucun revenu manuel.</p><?php else: ?>
      <table><thead><tr><th>Libelle</th><th>Montant</th><th>Categorie</th><th>Statut</th></tr></thead><tbody>
      <?php foreach ($entries as $entry): ?>
        <?php if (!is_array($entry)) { continue; } ?>
        <tr>
          <td><?php echo htmlspecialchars((string) ($entry['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars(number_format((float) ($entry['amount'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</td>
          <td><?php echo htmlspecialchars((string) ($entry['category'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) ($entry['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </section>
</section>
