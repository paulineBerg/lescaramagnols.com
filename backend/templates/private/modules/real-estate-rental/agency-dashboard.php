<?php
$stats = is_array($viewModel['agencyDashboardStats'] ?? null) ? $viewModel['agencyDashboardStats'] : [];
$documents = is_array($viewModel['agencyImportDocuments'] ?? null) ? $viewModel['agencyImportDocuments'] : [];
$batches = is_array($viewModel['agencyImportBatches'] ?? null) ? $viewModel['agencyImportBatches'] : [];
$urls = is_array($viewModel['rentalUrls'] ?? null) ? $viewModel['rentalUrls'] : [];
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>

  <div class="private-kpi-grid">
    <div class="private-kpi"><span>Agences</span><strong><?php echo htmlspecialchars((string) (int) ($stats['agencyCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></strong></div>
    <div class="private-kpi"><span>Documents importés</span><strong><?php echo htmlspecialchars((string) (int) ($stats['agencyDocumentCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></strong></div>
    <div class="private-kpi"><span>A classer</span><strong><?php echo htmlspecialchars((string) (int) ($stats['pendingAgencyDocumentCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></strong></div>
    <div class="private-kpi"><span>Imports (lots)</span><strong><?php echo htmlspecialchars((string) (int) ($stats['agencyBatchCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></strong></div>
    <div class="private-kpi"><span>Correspondances biens</span><strong><?php echo htmlspecialchars((string) (int) ($stats['agencyUnitMappingCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></strong></div>
  </div>

  <div class="cards-grid">
    <section class="card">
      <h2>Agence</h2>
      <p class="muted">Le classement agence se rattache aux propriétés créées dans le menu Biens et locations.</p>
      <p class="private-actions">
        <a href="<?php echo htmlspecialchars((string) ($urls['agencies'] ?? private_portal_url('rental_agencies')), ENT_QUOTES, 'UTF-8'); ?>">Agences</a>
        <a href="<?php echo htmlspecialchars((string) ($urls['properties'] ?? private_portal_url('rental_properties')), ENT_QUOTES, 'UTF-8'); ?>">Propriétés</a>
        <a href="<?php echo htmlspecialchars((string) ($urls['agencyImports'] ?? private_portal_url('rental_agency_imports')), ENT_QUOTES, 'UTF-8'); ?>">Importer agence</a>
        <a href="<?php echo htmlspecialchars((string) ($urls['agencyReview'] ?? private_portal_url('rental_agency_review')), ENT_QUOTES, 'UTF-8'); ?>">Classer les documents</a>
      </p>
    </section>
  </div>

  <section class="card private-card-wide">
    <h2>Derniers documents importés</h2>
    <?php if ($documents === []): ?>
      <p class="muted">Aucun import agence.</p>
    <?php else: ?>
      <div class="private-table-wrap">
        <table>
          <thead><tr><th>Fichier</th><th>Type</th><th>Statut</th></tr></thead>
          <tbody>
            <?php foreach ($documents as $document): ?>
              <?php if (!is_array($document)) { continue; } ?>
              <?php $documentId = is_numeric($document['id'] ?? null) ? (int) $document['id'] : 0; ?>
              <tr>
                <td><a href="<?php echo htmlspecialchars((string) ($urls['agencyReview'] ?? private_portal_url('rental_agency_review')) . '?document_id=' . $documentId, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($document['filename'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></td>
                <td><?php echo htmlspecialchars((string) ($document['detectedDocumentType'] ?? 'unknown'), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) ($document['reviewStatus'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="card private-card-wide">
    <h2>Derniers lots d'import</h2>
    <?php if ($batches === []): ?>
      <p class="muted">Aucun lot d'import.</p>
    <?php else: ?>
      <div class="private-table-wrap">
        <table>
          <thead><tr><th>Date</th><th>Agence</th><th>Fichiers</th><th>Ignorés</th><th>Doublons</th><th>Statut</th></tr></thead>
          <tbody>
            <?php foreach ($batches as $batch): ?>
              <?php if (!is_array($batch)) { continue; } ?>
              <tr>
                <td><?php echo htmlspecialchars((string) ($batch['createdAt'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) ($batch['agencyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) (int) ($batch['fileCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) (int) ($batch['ignoredFileCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) (int) ($batch['duplicateFileCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) ($batch['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</section>
