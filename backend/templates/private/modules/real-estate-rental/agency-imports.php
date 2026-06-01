<?php
$documents = is_array($viewModel['agencyImportDocuments'] ?? null) ? $viewModel['agencyImportDocuments'] : [];
$batches = is_array($viewModel['agencyImportBatches'] ?? null) ? $viewModel['agencyImportBatches'] : [];
$agencies = is_array($viewModel['agencyImportAgencies'] ?? null) ? $viewModel['agencyImportAgencies'] : [];
$csrfToken = is_string($viewModel['rentalCsrfToken'] ?? null) ? (string) $viewModel['rentalCsrfToken'] : '';
$notice = is_string($viewModel['rentalNotice'] ?? null) ? (string) $viewModel['rentalNotice'] : '';
$error = is_string($viewModel['rentalError'] ?? null) ? (string) $viewModel['rentalError'] : '';
$urls = is_array($viewModel['rentalUrls'] ?? null) ? $viewModel['rentalUrls'] : [];
$actionUrl = (string) ($urls['agencyImports'] ?? private_portal_url('rental_agency_imports'));
$agenciesUrl = (string) ($urls['agencies'] ?? private_portal_url('rental_agencies'));
$currentTab = is_string($viewModel['agencyImportCurrentTab'] ?? null) ? (string) $viewModel['agencyImportCurrentTab'] : 'documents';
$currentTab = in_array($currentTab, ['documents', 'imports'], true) ? $currentTab : 'documents';
$tabUrl = static fn (string $tab): string => $actionUrl . '?' . http_build_query(['tab' => $tab], '', '&', PHP_QUERY_RFC3986);
$reviewStatusLabels = [
    'pending' => 'À classer',
    'review' => 'À revoir',
    'validated' => 'Validé',
    'ignored' => 'Ignoré',
    'duplicate' => 'Doublon',
];
$batchStatusLabels = [
    'draft' => 'Brouillon',
    'review' => 'À revoir',
    'validated' => 'Validé',
    'cancelled' => 'Annulé',
];
$documentTypeLabels = [
    'unknown' => 'Non reconnu',
    'asg_management_statement' => 'Relevé de gestion ASG',
    'ics_management_report' => 'Compte rendu de gestion ICS',
    'copro_fund_call' => 'Appel de fonds copropriété',
    'copro_charge_regularization' => 'Régularisation de charges copropriété',
    'artisan_invoice' => 'Facture artisan',
    'lease' => 'Bail',
    'inventory_report' => 'État des lieux',
    'insurance' => 'Assurance',
    'tax_notice' => 'Avis fiscal',
    'occupancy_declaration' => 'Déclaration d’occupation',
    'complete_dossier' => 'Dossier complet',
];
$extractionStatusLabels = [
    'extracted' => 'Texte extrait',
    'needs_ocr_or_manual_entry' => 'OCR ou saisie manuelle',
    'unsupported' => 'Non pris en charge',
    'failed' => 'Échec extraction',
];
$labelFromMap = static function (mixed $value, array $labels): string {
    $key = is_scalar($value) ? trim((string) $value) : '';
    return $labels[$key] ?? ($key !== '' ? $key : '-');
};
$createDialogId = 'rental-agency-import-create-dialog';
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>
  <?php if ($notice !== ''): ?><p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <nav class="private-module-nav private-section-tabs" aria-label="Vue des imports agence">
    <div class="private-module-nav-row">
	      <a class="<?php echo $currentTab === 'documents' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($tabUrl('documents'), ENT_QUOTES, 'UTF-8'); ?>">Documents à revoir</a>
      <a class="<?php echo $currentTab === 'imports' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($tabUrl('imports'), ENT_QUOTES, 'UTF-8'); ?>">Imports récents</a>
    </div>
  </nav>

  <?php if ($currentTab === 'documents'): ?>
  <section class="card private-list-section" data-private-filter-scope>
    <div class="private-list-header">
      <div>
	        <h2>Documents à revoir</h2>
        <p class="muted">Imports agence à classer ou contrôler.</p>
      </div>
      <div class="private-actions">
        <a class="private-button-secondary" href="<?php echo htmlspecialchars($agenciesUrl, ENT_QUOTES, 'UTF-8'); ?>">Gérer les agences</a>
        <button type="button" class="private-create-button" data-private-dialog-open="<?php echo htmlspecialchars($createDialogId, ENT_QUOTES, 'UTF-8'); ?>">Importer un document</button>
      </div>
    </div>
    <?php if ($documents === []): ?>
      <p class="muted">Aucun document agence importé.</p>
    <?php else: ?>
      <div class="private-list-tools">
        <div class="private-list-filter-grid">
          <label>Recherche <input type="search" placeholder="Fichier, type, profil" data-private-filter="text" /></label>
          <label>Statut
            <select data-private-filter="status">
              <option value="all">Tous</option>
	              <?php foreach ($reviewStatusLabels as $value => $label): ?>
	                <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
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
        <thead>
          <tr>
            <th>Fichier</th>
            <th>Type</th>
            <th>Profil</th>
            <th>Extraction</th>
            <th>Lignes</th>
            <th>Anomalies</th>
            <th>Statut</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($documents as $document): ?>
            <?php if (!is_array($document)) { continue; } ?>
            <?php $documentId = is_numeric($document['id'] ?? null) ? (int) $document['id'] : 0; ?>
            <tr data-private-filter-row data-filter-text="<?php echo htmlspecialchars(strtolower(trim((string) ($document['filename'] ?? '') . ' ' . (string) ($document['detectedDocumentType'] ?? '') . ' ' . (string) ($document['parserProfile'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?>" data-filter-status="<?php echo htmlspecialchars((string) ($document['reviewStatus'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
              <td><a href="<?php echo htmlspecialchars((string) ($urls['agencyReview'] ?? private_portal_url('rental_agency_review')) . '?document_id=' . $documentId, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($document['filename'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></td>
              <td><?php echo htmlspecialchars($labelFromMap($document['detectedDocumentType'] ?? 'unknown', $documentTypeLabels), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($document['parserProfile'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($labelFromMap($document['textExtractionStatus'] ?? '', $extractionStatusLabels), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) (int) ($document['statementLineCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) (int) ($document['openIssueCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($labelFromMap($document['reviewStatus'] ?? '', $reviewStatusLabels), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php if ($documentId > 0): ?>
                  <form method="post" action="<?php echo htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
                    <input type="hidden" name="action" value="delete_agency_document" />
                    <input type="hidden" name="agency_document_id" value="<?php echo htmlspecialchars((string) $documentId, ENT_QUOTES, 'UTF-8'); ?>" />
                    <button class="button-small button-danger" type="submit">Supprimer</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <tr class="private-empty-row" data-private-filter-empty hidden><td colspan="8">Aucun document agence ne correspond aux filtres.</td></tr>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <dialog class="private-dialog" id="<?php echo htmlspecialchars($createDialogId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="<?php echo htmlspecialchars($createDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">
    <div class="private-dialog-panel">
      <header class="private-dialog-header">
        <h3 id="<?php echo htmlspecialchars($createDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">Importer un document agence</h3>
        <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
      </header>
      <form method="post" action="<?php echo htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8'); ?>" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="action" value="agency_import" />
        <label>Agence
          <input type="text" name="agency_name" maxlength="120" placeholder="ASG IMMOBILIER" list="rental-agency-name-options" />
        </label>
        <?php if ($agencies !== []): ?>
          <datalist id="rental-agency-name-options">
            <?php foreach ($agencies as $agency): ?>
              <?php if (!is_array($agency) || trim((string) ($agency['name'] ?? '')) === '') { continue; } ?>
              <option value="<?php echo htmlspecialchars((string) $agency['name'], ENT_QUOTES, 'UTF-8'); ?>"></option>
            <?php endforeach; ?>
          </datalist>
        <?php endif; ?>
        <label>Fichier agence
          <input type="file" name="agency_import_file" accept=".pdf,.txt,application/pdf,text/plain" required />
        </label>
        <button type="submit">Importer</button>
      </form>
    </div>
  </dialog>

  <?php if ($currentTab === 'imports'): ?>
  <section class="card private-list-section" data-private-filter-scope>
    <h2>Imports récents</h2>
    <?php if ($batches === []): ?>
      <p class="muted">Aucun import groupé.</p>
    <?php else: ?>
      <div class="private-list-tools">
        <div class="private-list-filter-grid">
          <label>Recherche <input type="search" placeholder="Agence ou statut" data-private-filter="text" /></label>
          <div class="private-list-filter-actions">
            <button type="button" class="private-button-secondary" data-private-filter-reset>Réinitialiser</button>
          </div>
        </div>
      </div>
      <div class="private-table-wrap">
      <table>
	        <thead><tr><th>Date</th><th>Agence</th><th>Fichiers</th><th>Ignorés</th><th>Doublons</th><th>Statut</th></tr></thead>
        <tbody>
          <?php foreach ($batches as $batch): ?>
            <?php if (!is_array($batch)) { continue; } ?>
            <tr data-private-filter-row data-filter-text="<?php echo htmlspecialchars(strtolower(trim((string) ($batch['agencyName'] ?? '') . ' ' . (string) ($batch['status'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?>">
              <td><?php echo htmlspecialchars((string) ($batch['createdAt'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($batch['agencyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) (int) ($batch['fileCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) (int) ($batch['ignoredFileCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) (int) ($batch['duplicateFileCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
	              <td><?php echo htmlspecialchars($labelFromMap($batch['status'] ?? '', $batchStatusLabels), ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
          <?php endforeach; ?>
          <tr class="private-empty-row" data-private-filter-empty hidden><td colspan="6">Aucun import ne correspond aux filtres.</td></tr>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</section>
