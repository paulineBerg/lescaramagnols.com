<?php
$summary = is_array($viewModel['taxSummary'] ?? null) ? $viewModel['taxSummary'] : [];
$totals = is_array($summary['totals'] ?? null) ? $summary['totals'] : [];
$lines = is_array($summary['lines'] ?? null) ? $summary['lines'] : [];
$year = is_numeric($summary['year'] ?? null) ? (int) $summary['year'] : (int) date('Y');
$locked = !empty($summary['locked']);
$notice = is_string($viewModel['taxNotice'] ?? null) ? (string) $viewModel['taxNotice'] : '';
$error = is_string($viewModel['taxError'] ?? null) ? (string) $viewModel['taxError'] : '';
$csrfToken = is_string($viewModel['taxCsrfToken'] ?? null) ? (string) $viewModel['taxCsrfToken'] : '';
$urls = is_array($viewModel['taxUrls'] ?? null) ? $viewModel['taxUrls'] : [];
$yearUrl = (string) ($urls['year'] ?? (private_portal_url('tax_dashboard') . '/' . $year));
?>
<section>
  <p class="muted">
    <a href="<?php echo htmlspecialchars((string) ($urls['dashboard'] ?? private_portal_url('tax_dashboard')), ENT_QUOTES, 'UTF-8'); ?>">Impots</a>
    · <a href="<?php echo htmlspecialchars((string) ($urls['manual'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Revenus manuels</a>
    · <a href="<?php echo htmlspecialchars((string) ($urls['controls'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Controle</a>
    · <a href="<?php echo htmlspecialchars((string) ($urls['documents'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Documents</a>
  </p>
  <p class="notice">Aide non officielle : cette synthese ne remplace pas une declaration fiscale ni un conseil professionnel.</p>
  <?php if ($notice !== ''): ?><p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <section class="card">
    <h2>Synthese <?php echo htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8'); ?> <?php echo $locked ? '(verrouillee)' : ''; ?></h2>
    <dl>
      <dt>Revenus manuels</dt><dd><?php echo htmlspecialchars(number_format((float) ($totals['manualIncome'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</dd>
      <dt>Revenus locatifs</dt><dd><?php echo htmlspecialchars(number_format((float) ($totals['rentalIncome'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</dd>
      <dt>Revenus bruts</dt><dd><?php echo htmlspecialchars(number_format((float) ($totals['grossIncome'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</dd>
      <dt>Charges candidates</dt><dd><?php echo htmlspecialchars(number_format((float) ($totals['deductibleExpenses'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</dd>
    </dl>
    <form method="post" action="<?php echo htmlspecialchars($yearUrl, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
      <button type="submit" name="action" value="generate_summary" <?php echo $locked ? 'disabled' : ''; ?>>Generer la synthese</button>
      <button type="submit" name="action" value="lock_year" <?php echo $locked ? 'disabled' : ''; ?>>Verrouiller</button>
      <button type="submit" name="action" value="unlock_year" <?php echo !$locked ? 'disabled' : ''; ?>>Deverrouiller admin</button>
    </form>
    <p class="private-actions">
      <a href="<?php echo htmlspecialchars((string) ($urls['exportCsv'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Export CSV</a>
      <a href="<?php echo htmlspecialchars((string) ($urls['exportPdf'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Export PDF</a>
    </p>
  </section>

  <section class="card">
    <h2>Lignes avec origine</h2>
    <?php if ($lines === []): ?>
      <p class="muted">Aucune ligne calculee.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Source</th><th>Type</th><th>Libelle</th><th>Montant</th><th>Origine</th></tr></thead>
        <tbody>
          <?php foreach ($lines as $line): ?>
            <?php if (!is_array($line)) { continue; } ?>
            <tr>
              <td><?php echo htmlspecialchars((string) ($line['sourceLabel'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($line['lineType'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($line['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(number_format((float) ($line['amount'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</td>
              <td><?php echo htmlspecialchars((string) ($line['sourceReference'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</section>
