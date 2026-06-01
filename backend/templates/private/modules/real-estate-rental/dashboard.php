<?php
$stats = is_array($viewModel['rentalDashboardStats'] ?? null) ? $viewModel['rentalDashboardStats'] : [];
$properties = is_array($viewModel['rentalProperties'] ?? null) ? $viewModel['rentalProperties'] : [];
$documents = is_array($viewModel['rentalRecentDocuments'] ?? null) ? $viewModel['rentalRecentDocuments'] : [];
$agencyDocuments = is_array($viewModel['agencyImportDocuments'] ?? null) ? $viewModel['agencyImportDocuments'] : [];
$urls = is_array($viewModel['rentalUrls'] ?? null) ? $viewModel['rentalUrls'] : [];
$annualBalances = is_array($stats['annualBalances'] ?? null) ? $stats['annualBalances'] : [];
$missingDocuments = is_array($stats['missingDocuments'] ?? null) ? $stats['missingDocuments'] : [];
$leasesEndingSoon = is_array($stats['leasesEndingSoon'] ?? null) ? $stats['leasesEndingSoon'] : [];
$issues = is_array($stats['issues'] ?? null) ? $stats['issues'] : [];
$propertyStatusLabels = [
    'active' => 'Actif',
    'inactive' => 'Inactif',
    'archived' => 'Archivé',
];
$propertyNames = [];
foreach ($properties as $property) {
    if (is_array($property) && is_numeric($property['id'] ?? null)) {
        $propertyNames[(int) $property['id']] = (string) ($property['name'] ?? ('Propriété #' . (int) $property['id']));
    }
}
$money = static fn (mixed $value): string => number_format(is_numeric($value) ? (float) $value : 0.0, 2, ',', ' ') . ' €';
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>

  <div class="private-kpi-grid">
    <div class="private-kpi"><span>Propriétés</span><strong><?php echo htmlspecialchars((string) (int) ($stats['propertyCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></strong></div>
    <div class="private-kpi"><span>Biens locatifs</span><strong><?php echo htmlspecialchars((string) (int) ($stats['unitCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></strong></div>
    <div class="private-kpi"><span>Vacants</span><strong><?php echo htmlspecialchars((string) (int) ($stats['vacantUnitCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></strong></div>
    <div class="private-kpi"><span>Indisponibles</span><strong><?php echo htmlspecialchars((string) (int) ($stats['unavailableUnitCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></strong></div>
    <div class="private-kpi"><span>Locataires</span><strong><?php echo htmlspecialchars((string) (int) ($stats['tenantCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></strong></div>
    <div class="private-kpi"><span>Baux actifs</span><strong><?php echo htmlspecialchars((string) (int) ($stats['activeLeaseCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></strong></div>
    <div class="private-kpi"><span>Loyers du mois attendus</span><strong><?php echo htmlspecialchars($money($stats['monthlyRentDue'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></strong></div>
    <div class="private-kpi"><span>Loyers du mois encaissés</span><strong><?php echo htmlspecialchars($money($stats['monthlyRentPaid'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></strong></div>
    <div class="private-kpi"><span>Partiels / retard</span><strong><?php echo htmlspecialchars((string) (int) ($stats['monthlyPartialRentCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?> / <?php echo htmlspecialchars((string) (int) ($stats['monthlyLateRentCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></strong></div>
    <div class="private-kpi"><span>Baux proches de fin</span><strong><?php echo htmlspecialchars((string) (int) ($stats['leasesEndingSoonCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></strong></div>
    <div class="private-kpi"><span>Documents manquants</span><strong><?php echo htmlspecialchars((string) (int) ($stats['missingDocumentCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></strong></div>
  </div>

  <?php if (!empty($stats['summaryBlocked'])) : ?>
    <p class="notice notice-error">
      Synthèse annuelle <?php echo htmlspecialchars((string) (int) ($stats['year'] ?? date('Y')), ENT_QUOTES, 'UTF-8'); ?> bloquée :
        <?php echo htmlspecialchars((string) (int) ($stats['issueCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?> point(s) à corriger.
    </p>
        <?php if ($issues !== []) : ?>
      <ul>
            <?php foreach (array_slice($issues, 0, 6) as $issue) : ?>
          <li><?php echo htmlspecialchars((string) $issue, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
      </ul>
        <?php endif; ?>
  <?php endif; ?>

  <div class="cards-grid">
    <section class="card">
      <h2>Biens et locations</h2>
      <ul>
        <li><a href="<?php echo htmlspecialchars((string) ($urls['properties'] ?? private_portal_url('rental_properties')), ENT_QUOTES, 'UTF-8'); ?>">Créer ou modifier une propriété</a></li>
        <li><a href="<?php echo htmlspecialchars((string) ($urls['units'] ?? private_portal_url('rental_units')), ENT_QUOTES, 'UTF-8'); ?>">Biens locatifs rattachés aux propriétés</a></li>
        <li><a href="<?php echo htmlspecialchars((string) ($urls['tenants'] ?? private_portal_url('rental_tenants')), ENT_QUOTES, 'UTF-8'); ?>">Locataires</a></li>
        <li><a href="<?php echo htmlspecialchars((string) ($urls['rents'] ?? private_portal_url('rental_rents')), ENT_QUOTES, 'UTF-8'); ?>">Loyers</a></li>
        <li><a href="<?php echo htmlspecialchars((string) ($urls['payments'] ?? private_portal_url('rental_payments')), ENT_QUOTES, 'UTF-8'); ?>">Paiements</a></li>
        <li><a href="<?php echo htmlspecialchars((string) ($urls['expenses'] ?? private_portal_url('rental_expenses')), ENT_QUOTES, 'UTF-8'); ?>">Charges</a></li>
        <li><a href="<?php echo htmlspecialchars((string) ($urls['regularizations'] ?? private_portal_url('rental_regularizations')), ENT_QUOTES, 'UTF-8'); ?>">Régularisations</a></li>
      </ul>
    </section>

    <section class="card">
      <h2>Agence</h2>
      <p class="muted">
        <?php echo htmlspecialchars((string) (int) ($stats['pendingAgencyDocumentCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>
        document(s) à classer sur
        <?php echo htmlspecialchars((string) (int) ($stats['agencyDocumentCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?> import(s).
      </p>
      <p class="muted">Le classement agence se rattache aux propriétés créées dans le menu Biens et locations.</p>
      <p class="private-actions">
        <a href="<?php echo htmlspecialchars((string) ($urls['properties'] ?? private_portal_url('rental_properties')), ENT_QUOTES, 'UTF-8'); ?>">Propriétés</a>
        <a href="<?php echo htmlspecialchars((string) ($urls['agencyImports'] ?? private_portal_url('rental_agency_imports')), ENT_QUOTES, 'UTF-8'); ?>">Importer document</a>
        <a href="<?php echo htmlspecialchars((string) ($urls['agencyReview'] ?? private_portal_url('rental_agency_review')), ENT_QUOTES, 'UTF-8'); ?>">Classer</a>
      </p>
    </section>

    <section class="card">
      <h2>Rapports</h2>
      <dl>
        <dt>Loyers attendus</dt>
        <dd><?php echo htmlspecialchars($money($stats['rentDue'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></dd>
        <dt>Loyers encaissés</dt>
        <dd><?php echo htmlspecialchars($money($stats['rentPaid'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></dd>
        <dt>Reste impayé</dt>
        <dd><?php echo htmlspecialchars($money($stats['unpaidRent'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></dd>
        <dt>Documents locatifs</dt>
        <dd><?php echo htmlspecialchars((string) (int) ($stats['documentCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></dd>
      </dl>
      <p class="private-actions">
        <a href="<?php echo htmlspecialchars((string) ($urls['summary'] ?? private_portal_url('rental_summary')), ENT_QUOTES, 'UTF-8'); ?>">Synthèse</a>
        <a href="<?php echo htmlspecialchars((string) ($stats['taxSummaryUrl'] ?? private_portal_url('tax_dashboard')), ENT_QUOTES, 'UTF-8'); ?>">Aide impôts</a>
      </p>
    </section>
  </div>

  <div class="cards-grid">
    <section class="card">
      <h2>Baux à surveiller</h2>
      <?php if ($leasesEndingSoon === []) : ?>
        <p class="muted">Aucun bail proche de fin.</p>
      <?php else : ?>
        <ul>
          <?php foreach (array_slice($leasesEndingSoon, 0, 6) as $lease) : ?>
                <?php if (!is_array($lease)) {
                    continue;
                } ?>
            <li><?php echo htmlspecialchars((string) ($lease['unitLabel'] ?? 'Bien'), ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars((string) ($lease['tenantName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars((string) ($lease['endDate'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="card">
      <h2>Documents manquants</h2>
      <?php if ($missingDocuments === []) : ?>
        <p class="muted">Aucun manque détecté.</p>
      <?php else : ?>
        <ul>
          <?php foreach (array_slice($missingDocuments, 0, 6) as $missingDocument) : ?>
                <?php if (!is_array($missingDocument)) {
                    continue;
                } ?>
            <li><?php echo htmlspecialchars((string) ($missingDocument['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>

  <section class="card private-card-wide">
    <h2>Propriétés suivies</h2>
    <?php if ($properties === []) : ?>
      <p class="muted">Aucune propriété autorisée pour ce compte.</p>
    <?php else : ?>
      <table>
        <thead><tr><th>Propriété</th><th>Type</th><th>Statut</th></tr></thead>
        <tbody>
          <?php foreach ($properties as $property) : ?>
                <?php if (!is_array($property)) {
                    continue;
                } ?>
            <tr>
              <td><?php echo htmlspecialchars((string) ($property['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($property['propertyType'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <?php $propertyStatus = is_string($property['status'] ?? null) ? (string) $property['status'] : ''; ?>
              <td><?php echo htmlspecialchars((string) ($propertyStatusLabels[$propertyStatus] ?? $propertyStatus), ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <section class="card private-card-wide">
    <h2>Solde annuel par propriété</h2>
    <?php if ($annualBalances === []) : ?>
      <p class="muted">Aucun solde annuel disponible.</p>
    <?php else : ?>
      <div class="private-table-wrap">
        <table>
          <thead><tr><th>Propriété</th><th>Loyers encaissés</th><th>Charges validées</th><th>Candidates déductibles</th><th>Solde</th></tr></thead>
          <tbody>
            <?php foreach ($annualBalances as $balance) : ?>
                <?php if (!is_array($balance)) {
                    continue;
                } ?>
                <?php $propertyId = is_numeric($balance['propertyId'] ?? null) ? (int) $balance['propertyId'] : 0; ?>
              <tr>
                <td><?php echo htmlspecialchars((string) ($propertyNames[$propertyId] ?? ('#' . $propertyId)), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($money($balance['rentPaid'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($money($balance['expenses'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($money($balance['deductibleCandidateExpenses'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($money($balance['balance'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <div class="cards-grid">
    <section class="card">
      <h2>Derniers documents locatifs</h2>
      <?php if ($documents === []) : ?>
        <p class="muted">Aucun document locatif.</p>
      <?php else : ?>
        <ul>
          <?php foreach ($documents as $document) : ?>
                <?php if (!is_array($document)) {
                    continue;
                } ?>
            <li><?php echo htmlspecialchars((string) ($document['originalName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="card">
      <h2>Derniers imports agence</h2>
      <?php if ($agencyDocuments === []) : ?>
        <p class="muted">Aucun import agence.</p>
      <?php else : ?>
        <ul>
          <?php foreach ($agencyDocuments as $document) : ?>
                <?php if (!is_array($document)) {
                    continue;
                } ?>
            <li><?php echo htmlspecialchars((string) ($document['filename'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>
</section>
