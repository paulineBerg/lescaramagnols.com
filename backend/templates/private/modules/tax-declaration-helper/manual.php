<?php
$entries = is_array($viewModel['taxManualEntries'] ?? null) ? $viewModel['taxManualEntries'] : [];
$summary = is_array($viewModel['taxSummary'] ?? null) ? $viewModel['taxSummary'] : [];
$year = is_numeric($summary['year'] ?? null) ? (int) $summary['year'] : (int) date('Y');
$locked = !empty($summary['locked']);
$csrfToken = is_string($viewModel['taxCsrfToken'] ?? null) ? (string) $viewModel['taxCsrfToken'] : '';
$notice = is_string($viewModel['taxNotice'] ?? null) ? (string) $viewModel['taxNotice'] : '';
$error = is_string($viewModel['taxError'] ?? null) ? (string) $viewModel['taxError'] : '';
$urls = is_array($viewModel['taxUrls'] ?? null) ? $viewModel['taxUrls'] : [];
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$createDialogId = 'tax-manual-entry-create-dialog';
$taxCurrentSubsection = 'manual';
$statusLabels = [
    'draft' => 'Brouillon',
    'validated' => 'Validé',
    'cancelled' => 'Annulé',
];
$categories = [];
foreach ($entries as $entry) {
    if (!is_array($entry)) {
        continue;
    }

    $category = trim((string) ($entry['category'] ?? ''));
    if ($category !== '') {
        $categories[$category] = $category;
    }
}
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>
  <?php if ($notice !== ''): ?><p class="notice notice-success"><?php echo $h($notice); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo $h($error); ?></p><?php endif; ?>
  <section class="card private-list-section" data-private-filter-scope>
    <div class="private-list-header">
      <div>
        <h2>Revenus manuels</h2>
        <p class="muted">Liste filtrable des revenus saisis hors import automatique.</p>
      </div>
      <button type="button" class="private-create-button" data-private-dialog-open="<?php echo $h($createDialogId); ?>"<?php echo $locked ? ' disabled' : ''; ?>>Ajouter un revenu</button>
    </div>
    <?php if ($locked): ?>
      <p class="notice notice-error">Année verrouillée : modification refusée.</p>
    <?php endif; ?>
    <?php if ($entries === []): ?>
      <p class="muted">Aucun revenu manuel.</p>
    <?php else: ?>
      <div class="private-list-tools">
        <div class="private-list-filter-grid">
          <label>Recherche
            <input type="search" placeholder="Libellé ou catégorie" data-private-filter="text" />
          </label>
          <label>Catégorie
            <select data-private-filter="category">
              <option value="all">Toutes</option>
              <?php foreach ($categories as $category): ?>
                <option value="<?php echo $h($category); ?>"><?php echo $h($category); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Statut
            <select data-private-filter="status">
              <option value="all">Tous</option>
              <?php foreach ($statusLabels as $value => $label): ?>
                <option value="<?php echo $h($value); ?>"><?php echo $h($label); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <div class="private-list-filter-actions">
            <button type="button" class="private-button-secondary" data-private-filter-reset>Réinitialiser</button>
          </div>
        </div>
      </div>
      <div class="private-table-wrap">
        <table>
          <thead><tr><th>Libellé</th><th>Montant</th><th>Catégorie</th><th>Statut</th></tr></thead>
          <tbody>
          <?php foreach ($entries as $entry): ?>
            <?php if (!is_array($entry)) { continue; } ?>
            <?php
            $label = (string) ($entry['label'] ?? '');
            $category = (string) ($entry['category'] ?? '');
            $status = (string) ($entry['status'] ?? '');
            ?>
            <tr data-private-filter-row data-filter-text="<?php echo $h($label . ' ' . $category); ?>" data-filter-category="<?php echo $h($category); ?>" data-filter-status="<?php echo $h($status); ?>">
              <td><?php echo $h($label); ?></td>
              <td><?php echo $h(number_format((float) ($entry['amount'] ?? 0), 2, ',', ' ')); ?> €</td>
              <td><?php echo $h($category); ?></td>
              <td><?php echo $h($statusLabels[$status] ?? $status); ?></td>
            </tr>
          <?php endforeach; ?>
          <tr class="private-empty-row" data-private-filter-empty hidden>
            <td colspan="4">Aucun revenu manuel ne correspond aux filtres.</td>
          </tr>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <dialog class="private-dialog" id="<?php echo $h($createDialogId); ?>" aria-labelledby="<?php echo $h($createDialogId . '-title'); ?>">
    <div class="private-dialog-panel">
      <header class="private-dialog-header">
        <h3 id="<?php echo $h($createDialogId . '-title'); ?>">Ajouter un revenu manuel</h3>
        <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
      </header>
      <?php if ($locked): ?>
        <p class="notice notice-error">Année verrouillée : modification refusée.</p>
      <?php else: ?>
        <form method="post" action="<?php echo $h((string) ($urls['manual'] ?? '')); ?>">
          <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
          <label>Libellé <input type="text" name="label" maxlength="160" required /></label>
          <label>Montant <input type="number" name="amount" min="0" step="0.01" required /></label>
          <label>Catégorie <input type="text" name="category" maxlength="64" required /></label>
          <label>Statut
            <select name="status">
              <?php foreach ($statusLabels as $value => $label): ?>
                <option value="<?php echo $h($value); ?>"><?php echo $h($label); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Notes <textarea name="notes" maxlength="2000"></textarea></label>
          <button type="submit">Ajouter</button>
        </form>
      <?php endif; ?>
    </div>
  </dialog>
</section>
