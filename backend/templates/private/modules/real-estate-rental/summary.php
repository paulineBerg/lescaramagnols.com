<?php
$summary = is_array($viewModel['rentalSummary'] ?? null) ? $viewModel['rentalSummary'] : [];
$urls = is_array($viewModel['rentalUrls'] ?? null) ? $viewModel['rentalUrls'] : [];
$year = is_numeric($summary['year'] ?? null) ? (int) $summary['year'] : (int) date('Y');
$blocked = !empty($summary['blocked']);
$issues = is_array($summary['issues'] ?? null) ? $summary['issues'] : [];
$totals = is_array($summary['totals'] ?? null) ? $summary['totals'] : [];
$leaseTaxCategories = is_array($summary['leaseTaxCategories'] ?? null) ? $summary['leaseTaxCategories'] : [];
$properties = is_array($viewModel['rentalProperties'] ?? null) ? $viewModel['rentalProperties'] : [];
$tenants = is_array($viewModel['rentalTenants'] ?? null) ? $viewModel['rentalTenants'] : [];
$summaryUrl = (string) ($urls['summary'] ?? private_portal_url('rental_summary'));
$exportCsvUrl = (string) ($urls['exportCsv'] ?? private_portal_url('rental_export_csv'));
$exportPdfUrl = (string) ($urls['exportPdf'] ?? private_portal_url('rental_export_pdf'));
$exportZipUrl = (string) ($urls['exportZip'] ?? private_portal_url('rental_export_zip'));
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
        <a href="<?php echo htmlspecialchars($exportCsvUrl . '?year=' . $year . '&kind=summary_csv', ENT_QUOTES, 'UTF-8'); ?>">Synthese CSV</a>
        <a href="<?php echo htmlspecialchars($exportCsvUrl . '?year=' . $year . '&kind=rents', ENT_QUOTES, 'UTF-8'); ?>">Loyers CSV</a>
        <a href="<?php echo htmlspecialchars($exportCsvUrl . '?year=' . $year . '&kind=expenses', ENT_QUOTES, 'UTF-8'); ?>">Charges CSV</a>
        <a href="<?php echo htmlspecialchars($exportPdfUrl . '?year=' . $year . '&kind=summary_pdf', ENT_QUOTES, 'UTF-8'); ?>">Synthese PDF</a>
      </p>
    <?php endif; ?>
  </section>

  <?php if (!$blocked && ($properties !== [] || $tenants !== [])): ?>
    <section class="card private-card-wide">
      <h2>Exports par bien et locataire</h2>
      <?php if ($properties !== []): ?>
        <div class="private-table-wrap">
          <table>
            <thead><tr><th>Propriete</th><th>PDF annuel</th><th>Documents</th></tr></thead>
            <tbody>
              <?php foreach ($properties as $property): ?>
                <?php if (!is_array($property) || !is_numeric($property['id'] ?? null)) { continue; } ?>
                <?php $propertyId = (int) $property['id']; ?>
                <tr>
                  <td><?php echo htmlspecialchars((string) ($property['name'] ?? ('Propriete #' . $propertyId)), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><a href="<?php echo htmlspecialchars($exportPdfUrl . '?year=' . $year . '&kind=property_annual&property_id=' . $propertyId, ENT_QUOTES, 'UTF-8'); ?>">PDF</a></td>
                  <td><a href="<?php echo htmlspecialchars($exportZipUrl . '?year=' . $year . '&kind=property_documents&property_id=' . $propertyId, ENT_QUOTES, 'UTF-8'); ?>">ZIP</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if ($tenants !== []): ?>
        <div class="private-table-wrap">
          <table>
            <thead><tr><th>Locataire</th><th>Bien</th><th>Recapitulatif</th></tr></thead>
            <tbody>
              <?php foreach ($tenants as $tenant): ?>
                <?php if (!is_array($tenant) || !is_numeric($tenant['id'] ?? null)) { continue; } ?>
                <?php $tenantId = (int) $tenant['id']; ?>
                <tr>
                  <td><?php echo htmlspecialchars((string) ($tenant['fullName'] ?? ('Locataire #' . $tenantId)), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string) ($tenant['unitLabel'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><a href="<?php echo htmlspecialchars($exportPdfUrl . '?year=' . $year . '&kind=tenant_recap&tenant_id=' . $tenantId, ENT_QUOTES, 'UTF-8'); ?>">PDF</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</section>
