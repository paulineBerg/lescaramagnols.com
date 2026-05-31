<?php
$documents = is_array($viewModel['agencyImportDocuments'] ?? null) ? $viewModel['agencyImportDocuments'] : [];
$batches = is_array($viewModel['agencyImportBatches'] ?? null) ? $viewModel['agencyImportBatches'] : [];
$agencies = is_array($viewModel['agencyImportAgencies'] ?? null) ? $viewModel['agencyImportAgencies'] : [];
$unitMappings = is_array($viewModel['agencyImportUnitMappings'] ?? null) ? $viewModel['agencyImportUnitMappings'] : [];
$properties = is_array($viewModel['agencyImportProperties'] ?? null) ? $viewModel['agencyImportProperties'] : [];
$units = is_array($viewModel['agencyImportUnits'] ?? null) ? $viewModel['agencyImportUnits'] : [];
$csrfToken = is_string($viewModel['rentalCsrfToken'] ?? null) ? (string) $viewModel['rentalCsrfToken'] : '';
$notice = is_string($viewModel['rentalNotice'] ?? null) ? (string) $viewModel['rentalNotice'] : '';
$error = is_string($viewModel['rentalError'] ?? null) ? (string) $viewModel['rentalError'] : '';
$urls = is_array($viewModel['rentalUrls'] ?? null) ? $viewModel['rentalUrls'] : [];
$actionUrl = (string) ($urls['agencyImports'] ?? private_portal_url('rental_agency_imports'));
$currentTab = is_string($viewModel['agencyImportCurrentTab'] ?? null) ? (string) $viewModel['agencyImportCurrentTab'] : 'documents';
$currentTab = in_array($currentTab, ['documents', 'imports', 'agencies'], true) ? $currentTab : 'documents';
$tabUrl = static fn (string $tab): string => $actionUrl . '?' . http_build_query(['tab' => $tab], '', '&', PHP_QUERY_RFC3986);
$createDialogId = 'rental-agency-import-create-dialog';
$agencyDialogId = 'rental-agency-create-dialog';
$mappingDialogId = 'rental-agency-unit-mapping-create-dialog';
$propertyNames = [];
foreach ($properties as $property) {
    if (!is_array($property) || !is_numeric($property['id'] ?? null)) {
        continue;
    }

    $propertyNames[(int) $property['id']] = trim((string) ($property['name'] ?? ''));
}
$unitOptions = [];
foreach ($units as $unit) {
    if (!is_array($unit) || !is_numeric($unit['id'] ?? null) || !is_numeric($unit['rentalPropertyId'] ?? null)) {
        continue;
    }

    $propertyId = (int) $unit['rentalPropertyId'];
    $unitLabel = trim((string) ($unit['label'] ?? ''));
    $type = trim((string) ($unit['unitType'] ?? ''));
    $propertyLabel = $propertyNames[$propertyId] ?? ('Propriété #' . $propertyId);
    $unitOptions[] = [
        'id' => (int) $unit['id'],
        'propertyId' => $propertyId,
        'label' => trim($propertyLabel . ' - ' . $unitLabel . ($type !== '' ? ' (' . $type . ')' : '')),
    ];
}
$agencyValue = static function (array $agency, string $key): string {
    return is_scalar($agency[$key] ?? null) ? trim((string) $agency[$key]) : '';
};
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>
  <?php if ($notice !== ''): ?><p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <nav class="private-module-nav private-section-tabs" aria-label="Vue des imports agence">
    <div class="private-module-nav-row">
      <a class="<?php echo $currentTab === 'documents' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($tabUrl('documents'), ENT_QUOTES, 'UTF-8'); ?>">Documents a revoir</a>
      <a class="<?php echo $currentTab === 'imports' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($tabUrl('imports'), ENT_QUOTES, 'UTF-8'); ?>">Imports récents</a>
      <a class="<?php echo $currentTab === 'agencies' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($tabUrl('agencies'), ENT_QUOTES, 'UTF-8'); ?>">Agences</a>
    </div>
  </nav>

  <?php if ($currentTab === 'documents'): ?>
  <section class="card private-list-section" data-private-filter-scope>
    <div class="private-list-header">
      <div>
        <h2>Documents a revoir</h2>
        <p class="muted">Imports agence à classer ou contrôler.</p>
      </div>
      <div class="private-actions">
        <button type="button" class="private-button-secondary" data-private-dialog-open="<?php echo htmlspecialchars($agencyDialogId, ENT_QUOTES, 'UTF-8'); ?>">Créer une agence</button>
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
              <option value="pending">pending</option>
              <option value="review">review</option>
              <option value="validated">validated</option>
              <option value="ignored">ignored</option>
              <option value="duplicate">duplicate</option>
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
              <td><?php echo htmlspecialchars((string) ($document['detectedDocumentType'] ?? 'unknown'), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($document['parserProfile'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($document['textExtractionStatus'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) (int) ($document['statementLineCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) (int) ($document['openIssueCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($document['reviewStatus'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
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

  <?php if ($currentTab === 'agencies'): ?>
  <section class="card private-list-section" data-private-filter-scope>
    <div class="private-list-header">
      <div>
        <h2>Agences</h2>
        <p class="muted">Agences disponibles pour les imports de relevés.</p>
      </div>
      <div class="private-actions">
        <button type="button" class="private-button-secondary" data-private-dialog-open="<?php echo htmlspecialchars($mappingDialogId, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $agencies === [] || $unitOptions === [] ? ' disabled' : ''; ?>>Créer une correspondance</button>
        <button type="button" class="private-create-button" data-private-dialog-open="<?php echo htmlspecialchars($agencyDialogId, ENT_QUOTES, 'UTF-8'); ?>">Créer une agence</button>
      </div>
    </div>
    <?php if ($agencies === []): ?>
      <p class="muted">Aucune agence enregistrée.</p>
    <?php else: ?>
      <div class="private-list-tools">
        <div class="private-list-filter-grid">
          <label>Recherche <input type="search" placeholder="Nom agence" data-private-filter="text" /></label>
          <div class="private-list-filter-actions">
            <button type="button" class="private-button-secondary" data-private-filter-reset>Réinitialiser</button>
          </div>
        </div>
      </div>
      <div class="private-table-wrap">
      <table>
        <thead>
          <tr>
            <th>Agence</th>
            <th>Contact</th>
            <th>Conseiller</th>
            <th>Imports</th>
            <th>Fichiers</th>
            <th>Dernière activité</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($agencies as $agency): ?>
            <?php if (!is_array($agency)) { continue; } ?>
            <?php
            $agencyId = is_numeric($agency['id'] ?? null) ? (int) $agency['id'] : 0;
            $agencyName = trim((string) ($agency['name'] ?? ''));
            $legalName = $agencyValue($agency, 'legalName');
            $contactSummary = trim(implode(' - ', array_filter([
                $agencyValue($agency, 'contactTitle'),
                $agencyValue($agency, 'email'),
                $agencyValue($agency, 'phone'),
            ], static fn (string $value): bool => $value !== '')));
            $advisorSummary = trim(implode(' - ', array_filter([
                $agencyValue($agency, 'advisorName'),
                $agencyValue($agency, 'advisorTitle'),
                $agencyValue($agency, 'advisorEmail'),
                $agencyValue($agency, 'advisorPhone'),
            ], static fn (string $value): bool => $value !== '')));
            $agencySearch = strtolower(trim(
                $agencyName . ' ' . $legalName . ' ' . $contactSummary . ' ' . $advisorSummary . ' '
                . $agencyValue($agency, 'postalAddress') . ' ' . $agencyValue($agency, 'notes')
            ));
            $editDialogId = 'rental-agency-edit-dialog-' . $agencyId;
            ?>
            <tr data-private-filter-row data-filter-text="<?php echo htmlspecialchars($agencySearch, ENT_QUOTES, 'UTF-8'); ?>">
              <td>
                <strong><?php echo htmlspecialchars($agencyName, ENT_QUOTES, 'UTF-8'); ?></strong>
                <?php if ($legalName !== ''): ?><br /><span class="muted"><?php echo htmlspecialchars($legalName, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
              </td>
              <td><?php echo htmlspecialchars($contactSummary !== '' ? $contactSummary : '-', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($advisorSummary !== '' ? $advisorSummary : '-', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) (int) ($agency['batchCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) (int) ($agency['fileCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($agency['lastActivityAt'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php if ($agencyId > 0): ?>
                  <button type="button" class="private-row-action" data-private-dialog-open="<?php echo htmlspecialchars($editDialogId, ENT_QUOTES, 'UTF-8'); ?>">Modifier</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <tr class="private-empty-row" data-private-filter-empty hidden><td colspan="7">Aucune agence ne correspond aux filtres.</td></tr>
        </tbody>
      </table>
      </div>
      <?php foreach ($agencies as $agency): ?>
        <?php if (!is_array($agency) || !is_numeric($agency['id'] ?? null) || (int) $agency['id'] <= 0) { continue; } ?>
        <?php
        $agencyId = (int) $agency['id'];
        $editDialogId = 'rental-agency-edit-dialog-' . $agencyId;
        ?>
        <dialog class="private-dialog" id="<?php echo htmlspecialchars($editDialogId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="<?php echo htmlspecialchars($editDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">
          <div class="private-dialog-panel">
            <header class="private-dialog-header">
              <h3 id="<?php echo htmlspecialchars($editDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">Modifier l'agence</h3>
              <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
            </header>
            <form method="post" action="<?php echo htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8'); ?>?tab=agencies">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="update_agency" />
              <input type="hidden" name="agency_id" value="<?php echo htmlspecialchars((string) $agencyId, ENT_QUOTES, 'UTF-8'); ?>" />
              <div class="private-form-grid">
                <label>Nom de l'agence <input type="text" name="agency_name" maxlength="120" value="<?php echo htmlspecialchars((string) ($agency['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
                <label>Raison sociale <input type="text" name="legal_name" maxlength="190" value="<?php echo htmlspecialchars($agencyValue($agency, 'legalName'), ENT_QUOTES, 'UTF-8'); ?>" /></label>
                <label>Titre / service <input type="text" name="contact_title" maxlength="120" value="<?php echo htmlspecialchars($agencyValue($agency, 'contactTitle'), ENT_QUOTES, 'UTF-8'); ?>" /></label>
                <label>Téléphone agence <input type="tel" name="phone" maxlength="80" value="<?php echo htmlspecialchars($agencyValue($agency, 'phone'), ENT_QUOTES, 'UTF-8'); ?>" /></label>
                <label>Email agence <input type="email" name="email" maxlength="254" value="<?php echo htmlspecialchars($agencyValue($agency, 'email'), ENT_QUOTES, 'UTF-8'); ?>" /></label>
                <label>Conseiller <input type="text" name="advisor_name" maxlength="160" value="<?php echo htmlspecialchars($agencyValue($agency, 'advisorName'), ENT_QUOTES, 'UTF-8'); ?>" /></label>
                <label>Titre conseiller <input type="text" name="advisor_title" maxlength="120" value="<?php echo htmlspecialchars($agencyValue($agency, 'advisorTitle'), ENT_QUOTES, 'UTF-8'); ?>" /></label>
                <label>Téléphone conseiller <input type="tel" name="advisor_phone" maxlength="80" value="<?php echo htmlspecialchars($agencyValue($agency, 'advisorPhone'), ENT_QUOTES, 'UTF-8'); ?>" /></label>
                <label>Email conseiller <input type="email" name="advisor_email" maxlength="254" value="<?php echo htmlspecialchars($agencyValue($agency, 'advisorEmail'), ENT_QUOTES, 'UTF-8'); ?>" /></label>
              </div>
              <label>Adresse
                <textarea name="postal_address" rows="3" maxlength="500"><?php echo htmlspecialchars($agencyValue($agency, 'postalAddress'), ENT_QUOTES, 'UTF-8'); ?></textarea>
              </label>
              <label>Notes
                <textarea name="notes" rows="3" maxlength="2000"><?php echo htmlspecialchars($agencyValue($agency, 'notes'), ENT_QUOTES, 'UTF-8'); ?></textarea>
              </label>
              <button type="submit">Enregistrer l'agence</button>
            </form>
          </div>
        </dialog>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <section class="card private-list-section" data-private-filter-scope>
    <div class="private-list-header">
      <div>
        <h2>Correspondances par agence</h2>
        <p class="muted">Exemple : chez ASG, le texte « EVE Hervé » rattache automatiquement le bien locatif Arbousier.</p>
      </div>
      <button type="button" class="private-create-button" data-private-dialog-open="<?php echo htmlspecialchars($mappingDialogId, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $agencies === [] || $unitOptions === [] ? ' disabled' : ''; ?>>Créer une correspondance</button>
    </div>
    <?php if ($unitMappings === []): ?>
      <p class="muted">Aucune correspondance agence enregistrée.</p>
    <?php else: ?>
      <div class="private-list-tools">
        <div class="private-list-filter-grid">
          <label>Recherche <input type="search" placeholder="Agence, texte ou bien" data-private-filter="text" /></label>
          <div class="private-list-filter-actions">
            <button type="button" class="private-button-secondary" data-private-filter-reset>Réinitialiser</button>
          </div>
        </div>
      </div>
      <div class="private-table-wrap">
      <table>
        <thead>
          <tr>
            <th>Agence</th>
            <th>Texte détecté</th>
            <th>Propriété</th>
            <th>Bien locatif</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($unitMappings as $mapping): ?>
            <?php if (!is_array($mapping)) { continue; } ?>
            <?php
            $mappingId = is_numeric($mapping['id'] ?? null) ? (int) $mapping['id'] : 0;
            $mappingSearch = strtolower(trim(
                (string) ($mapping['agencyName'] ?? '') . ' '
                . (string) ($mapping['matchText'] ?? '') . ' '
                . (string) ($mapping['propertyName'] ?? '') . ' '
                . (string) ($mapping['unitLabel'] ?? '')
            ));
            ?>
            <tr data-private-filter-row data-filter-text="<?php echo htmlspecialchars($mappingSearch, ENT_QUOTES, 'UTF-8'); ?>">
              <td><?php echo htmlspecialchars((string) ($mapping['agencyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><strong><?php echo htmlspecialchars((string) ($mapping['matchText'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong></td>
              <td><?php echo htmlspecialchars((string) ($mapping['propertyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(trim((string) ($mapping['unitLabel'] ?? '') . ' ' . (string) ($mapping['unitType'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php if ($mappingId > 0): ?>
                  <form method="post" action="<?php echo htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
                    <input type="hidden" name="action" value="delete_agency_unit_mapping" />
                    <input type="hidden" name="agency_unit_mapping_id" value="<?php echo htmlspecialchars((string) $mappingId, ENT_QUOTES, 'UTF-8'); ?>" />
                    <button class="button-small button-danger" type="submit">Supprimer</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <tr class="private-empty-row" data-private-filter-empty hidden><td colspan="5">Aucune correspondance ne correspond aux filtres.</td></tr>
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

  <dialog class="private-dialog" id="<?php echo htmlspecialchars($agencyDialogId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="<?php echo htmlspecialchars($agencyDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">
    <div class="private-dialog-panel">
      <header class="private-dialog-header">
        <h3 id="<?php echo htmlspecialchars($agencyDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">Créer une agence</h3>
        <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
      </header>
      <form method="post" action="<?php echo htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8'); ?>?tab=agencies">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="action" value="create_agency" />
        <div class="private-form-grid">
          <label>Nom de l'agence <input type="text" name="agency_name" maxlength="120" placeholder="ASG IMMOBILIER" required /></label>
          <label>Raison sociale <input type="text" name="legal_name" maxlength="190" /></label>
          <label>Titre / service <input type="text" name="contact_title" maxlength="120" /></label>
          <label>Téléphone agence <input type="tel" name="phone" maxlength="80" /></label>
          <label>Email agence <input type="email" name="email" maxlength="254" /></label>
          <label>Conseiller <input type="text" name="advisor_name" maxlength="160" /></label>
          <label>Titre conseiller <input type="text" name="advisor_title" maxlength="120" /></label>
          <label>Téléphone conseiller <input type="tel" name="advisor_phone" maxlength="80" /></label>
          <label>Email conseiller <input type="email" name="advisor_email" maxlength="254" /></label>
        </div>
        <label>Adresse
          <textarea name="postal_address" rows="3" maxlength="500"></textarea>
        </label>
        <label>Notes
          <textarea name="notes" rows="3" maxlength="2000"></textarea>
        </label>
        <button type="submit">Créer l'agence</button>
      </form>
    </div>
  </dialog>

  <dialog class="private-dialog" id="<?php echo htmlspecialchars($mappingDialogId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="<?php echo htmlspecialchars($mappingDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">
    <div class="private-dialog-panel">
      <header class="private-dialog-header">
        <h3 id="<?php echo htmlspecialchars($mappingDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">Créer une correspondance agence</h3>
        <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
      </header>
      <form method="post" action="<?php echo htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8'); ?>?tab=agencies">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="action" value="create_agency_unit_mapping" />
        <label>Agence
          <select name="agency_name" required>
            <option value="">Choisir une agence</option>
            <?php foreach ($agencies as $agency): ?>
              <?php if (!is_array($agency) || trim((string) ($agency['name'] ?? '')) === '') { continue; } ?>
              <option value="<?php echo htmlspecialchars((string) $agency['name'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $agency['name'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Texte détecté dans le document
          <input type="text" name="match_text" maxlength="160" placeholder="EVE Hervé" required />
        </label>
        <label>Bien locatif
          <select name="rental_unit_id" required>
            <option value="">Choisir un bien locatif</option>
            <?php foreach ($unitOptions as $unitOption): ?>
              <option value="<?php echo htmlspecialchars((string) $unitOption['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $unitOption['label'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button type="submit">Créer la correspondance</button>
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
        <thead><tr><th>Date</th><th>Agence</th><th>Fichiers</th><th>Ignores</th><th>Doublons</th><th>Statut</th></tr></thead>
        <tbody>
          <?php foreach ($batches as $batch): ?>
            <?php if (!is_array($batch)) { continue; } ?>
            <tr data-private-filter-row data-filter-text="<?php echo htmlspecialchars(strtolower(trim((string) ($batch['agencyName'] ?? '') . ' ' . (string) ($batch['status'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?>">
              <td><?php echo htmlspecialchars((string) ($batch['createdAt'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($batch['agencyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) (int) ($batch['fileCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) (int) ($batch['ignoredFileCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) (int) ($batch['duplicateFileCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($batch['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
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
