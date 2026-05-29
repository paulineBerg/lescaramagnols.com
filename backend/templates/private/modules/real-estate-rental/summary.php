<?php
$summary = is_array($viewModel['rentalSummary'] ?? null) ? $viewModel['rentalSummary'] : [];
$urls = is_array($viewModel['rentalUrls'] ?? null) ? $viewModel['rentalUrls'] : [];
$year = is_numeric($summary['year'] ?? null) ? (int) $summary['year'] : (int) date('Y');
$blocked = !empty($summary['blocked']);
$issues = is_array($summary['issues'] ?? null) ? $summary['issues'] : [];
$totals = is_array($summary['totals'] ?? null) ? $summary['totals'] : [];
$leaseTaxCategories = is_array($summary['leaseTaxCategories'] ?? null) ? $summary['leaseTaxCategories'] : [];
$summaryUrl = (string) ($urls['summary'] ?? private_portal_url('rental_summary'));
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>

  <section class="card">
    <h2>Synthese annuelle locative</h2>
    <form method="get" action="<?php echo htmlspecialchars($summaryUrl, ENT_QUOTES, 'UTF-8'); ?>">
      <label>Annee <input type="number" name="year" min="2000" max="2100" value="<?php echo htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8'); ?>" /></label>
      <button type="submit">Recalculer</button>
    </form>

    <?php if ($blocked): ?>
      <p class="notice notice-error">Synthese et exports bloques : des donnees brouillon existent pour <?php echo htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8'); ?>.</p>
      <ul>
        <?php foreach ($issues as $issue): ?>
          <li><?php echo htmlspecialchars((string) $issue, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <dl>
        <dt>Loyers attendus</dt><dd><?php echo htmlspecialchars(number_format((float) ($totals['rentDue'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</dd>
        <dt>Loyers encaisses</dt><dd><?php echo htmlspecialchars(number_format((float) ($totals['rentPaid'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</dd>
        <dt>Reste impaye</dt><dd><?php echo htmlspecialchars(number_format((float) ($totals['unpaidRent'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</dd>
        <dt>Charges recuperables</dt><dd><?php echo htmlspecialchars(number_format((float) ($totals['recoverableExpenses'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</dd>
        <dt>Charges potentiellement deductibles</dt><dd><?php echo htmlspecialchars(number_format((float) ($totals['deductibleCandidateExpenses'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</dd>
        <dt>Charges non deductibles</dt><dd><?php echo htmlspecialchars(number_format((float) ($totals['nonDeductibleExpenses'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</dd>
        <?php foreach ($leaseTaxCategories as $category): ?>
          <?php if (!is_array($category)) { continue; } ?>
          <dt><?php echo htmlspecialchars((string) ($category['label'] ?? 'Imposition'), ENT_QUOTES, 'UTF-8'); ?></dt>
          <dd><?php echo htmlspecialchars((string) (int) ($category['count'] ?? 0), ENT_QUOTES, 'UTF-8'); ?> bail(aux)</dd>
        <?php endforeach; ?>
      </dl>
      <p class="private-actions">
        <a href="<?php echo htmlspecialchars((string) ($urls['exportCsv'] ?? private_portal_url('rental_export_csv')) . '?year=' . $year, ENT_QUOTES, 'UTF-8'); ?>">Export CSV</a>
        <a href="<?php echo htmlspecialchars((string) ($urls['exportPdf'] ?? private_portal_url('rental_export_pdf')) . '?year=' . $year, ENT_QUOTES, 'UTF-8'); ?>">Export PDF</a>
      </p>
    <?php endif; ?>
  </section>
</section>
