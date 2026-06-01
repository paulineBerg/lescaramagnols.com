<?php
$documents = is_array($viewModel['agencyReviewDocuments'] ?? null) ? $viewModel['agencyReviewDocuments'] : [];
$selectedDocument = is_array($viewModel['agencyReviewSelectedDocument'] ?? null) ? $viewModel['agencyReviewSelectedDocument'] : null;
$properties = is_array($viewModel['agencyReviewProperties'] ?? null) ? $viewModel['agencyReviewProperties'] : [];
$units = is_array($viewModel['agencyReviewUnits'] ?? null) ? $viewModel['agencyReviewUnits'] : [];
$categories = is_array($viewModel['agencyReviewCategories'] ?? null) ? $viewModel['agencyReviewCategories'] : [];
$sensitiveCategories = is_array($viewModel['agencyReviewSensitiveCategories'] ?? null)
    ? array_values(array_filter($viewModel['agencyReviewSensitiveCategories'], 'is_string'))
    : [];
$reconciliation = is_array($viewModel['agencyReviewReconciliation'] ?? null)
    ? $viewModel['agencyReviewReconciliation']
    : [];
$csrfToken = is_string($viewModel['rentalCsrfToken'] ?? null) ? (string) $viewModel['rentalCsrfToken'] : '';
$notice = is_string($viewModel['rentalNotice'] ?? null) ? (string) $viewModel['rentalNotice'] : '';
$error = is_string($viewModel['rentalError'] ?? null) ? (string) $viewModel['rentalError'] : '';
$lineFeedbackId = is_numeric($viewModel['agencyReviewLineFeedbackId'] ?? null)
    ? (int) $viewModel['agencyReviewLineFeedbackId']
    : 0;
$lineNotice = is_string($viewModel['agencyReviewLineNotice'] ?? null)
    ? (string) $viewModel['agencyReviewLineNotice']
    : '';
$lineError = is_string($viewModel['agencyReviewLineError'] ?? null)
    ? (string) $viewModel['agencyReviewLineError']
    : '';
$urls = is_array($viewModel['rentalUrls'] ?? null) ? $viewModel['rentalUrls'] : [];
$reviewUrl = (string) ($urls['agencyReview'] ?? private_portal_url('rental_agency_review'));
$importsUrl = (string) ($urls['agencyImports'] ?? private_portal_url('rental_agency_imports'));
$propertiesUrl = (string) ($urls['properties'] ?? private_portal_url('rental_properties'));
$summaryUrl = (string) ($urls['summary'] ?? private_portal_url('rental_summary'));
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$amount = static function (mixed $value) use ($h): string {
    return is_numeric($value) ? $h(number_format((float) $value, 2, '.', '')) : '';
};
$money = static function (mixed $value) use ($h): string {
    return is_numeric($value) ? $h(number_format((float) $value, 2, ',', ' ') . ' €') : '0,00 €';
};
$reviewStatusLabels = [
    'pending' => 'À valider',
    'review' => 'À revoir',
    'validated' => 'Validé',
    'ignored' => 'Ignoré',
    'duplicate' => 'Doublon',
];
$mappingStatusLabels = [
    'suggested' => 'Proposé',
    'review' => 'À contrôler',
    'validated' => 'Validé',
    'ignored' => 'Ignoré',
];
$documentTypeLabels = [
    'unknown' => 'Non reconnu',
    'rent_receipt' => 'Quittance',
    'management_statement' => 'Relevé de gestion',
    'other_agency_document' => 'Autres',
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
$unitTypeLabels = [
    'apartment' => 'Appartement',
    'house' => 'Maison',
    'garage' => 'Garage',
    'parking' => 'Parking',
    'commercial_space' => 'Local commercial',
    'room' => 'Chambre',
    'storage' => 'Cave / stockage',
    'other' => 'Autre',
];
$labelFromMap = static function (mixed $value, array $labels): string {
    $key = is_scalar($value) ? trim((string) $value) : '';
    return $labels[$key] ?? ($key !== '' ? $key : '-');
};
$labelForProperty = static function (array $property): string {
    $name = is_scalar($property['name'] ?? null) ? trim((string) $property['name']) : '';
    $address = is_scalar($property['address'] ?? null) ? trim((string) $property['address']) : '';
    return trim($name . ($address !== '' ? ' - ' . $address : ''));
};
$propertiesById = [];
foreach ($properties as $property) {
    if (is_array($property) && is_numeric($property['id'] ?? null)) {
        $propertiesById[(int) $property['id']] = $property;
    }
}
$unitsByPropertyId = [];
foreach ($units as $unit) {
    if (!is_array($unit) || !is_numeric($unit['id'] ?? null) || !is_numeric($unit['rentalPropertyId'] ?? null)) {
        continue;
    }

    $propertyId = (int) $unit['rentalPropertyId'];
    $unitsByPropertyId[$propertyId] ??= [];
    $unitsByPropertyId[$propertyId][] = $unit;
}
$labelForUnit = static function (array $unit) use ($unitTypeLabels): string {
    $label = is_scalar($unit['label'] ?? null) ? trim((string) $unit['label']) : '';
    $type = is_scalar($unit['unitType'] ?? null) ? trim((string) $unit['unitType']) : '';
    $typeLabel = $type !== '' ? (string) ($unitTypeLabels[$type] ?? 'Autre') : '';
    return trim($label . ($typeLabel !== '' ? ' - ' . $typeLabel : ''));
};
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>

  <?php if ($notice !== ''): ?><p class="notice notice-success"><?php echo $h($notice); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo $h($error); ?></p><?php endif; ?>

  <section class="card">
    <h2>Documents agence à valider</h2>
    <p class="muted">Avant de rattacher un relevé agence, la propriété doit exister dans Biens et locations.</p>
    <p class="private-actions">
      <a href="<?php echo $h($propertiesUrl); ?>">Créer ou modifier une propriété</a>
      <a href="<?php echo $h($importsUrl); ?>">Importer un document agence</a>
    </p>
    <?php if ($documents === []): ?>
      <p class="muted">Aucun document agence importé.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Fichier</th>
            <th>Type</th>
            <th>Période</th>
            <th>Lignes</th>
            <th>Anomalies</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($documents as $document): ?>
            <?php
            if (!is_array($document)) {
                continue;
            }
            $documentId = is_numeric($document['id'] ?? null) ? (int) $document['id'] : 0;
            $period = trim((string) ($document['statementPeriodStart'] ?? '') . ' - ' . (string) ($document['statementPeriodEnd'] ?? ''));
            ?>
            <tr>
              <td><a href="<?php echo $h($reviewUrl . '?document_id=' . $documentId); ?>"><?php echo $h($document['filename'] ?? ''); ?></a></td>
              <td><?php echo $h($labelFromMap($document['detectedDocumentType'] ?? 'unknown', $documentTypeLabels)); ?></td>
              <td><?php echo $h($period); ?></td>
              <td><?php echo $h((int) ($document['statementLineCount'] ?? 0)); ?></td>
              <td><?php echo $h((int) ($document['openIssueCount'] ?? 0)); ?></td>
              <td><?php echo $h($labelFromMap($document['reviewStatus'] ?? '', $reviewStatusLabels)); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <?php if ($selectedDocument !== null): ?>
    <?php
    $selectedDocumentId = is_numeric($selectedDocument['id'] ?? null) ? (int) $selectedDocument['id'] : 0;
    $selectedPropertyId = is_numeric($selectedDocument['rentalPropertyId'] ?? null) ? (int) $selectedDocument['rentalPropertyId'] : 0;
    $lines = is_array($selectedDocument['lines'] ?? null) ? $selectedDocument['lines'] : [];
    $issues = is_array($selectedDocument['issues'] ?? null) ? $selectedDocument['issues'] : [];
    ?>
    <section class="card agency-review-card">
      <div class="agency-review-compact-head">
        <div class="agency-review-summary-block">
          <h2>Contrôle du document</h2>
          <dl class="agency-review-summary">
            <div>
              <dt>Fichier</dt>
              <dd><?php echo $h($selectedDocument['filename'] ?? ''); ?></dd>
            </div>
            <div>
              <dt>Agence</dt>
              <dd><?php echo $h($selectedDocument['detectedAgency'] ?? $selectedDocument['batchAgencyName'] ?? ''); ?></dd>
            </div>
            <div>
              <dt>Profil</dt>
              <dd><?php echo $h($selectedDocument['parserProfile'] ?? ''); ?></dd>
            </div>
            <div>
              <dt>Période</dt>
              <dd><?php echo $h((string) ($selectedDocument['statementPeriodStart'] ?? '') . ' - ' . (string) ($selectedDocument['statementPeriodEnd'] ?? '')); ?></dd>
            </div>
            <div>
              <dt>Statut</dt>
              <dd><?php echo $h($labelFromMap($selectedDocument['reviewStatus'] ?? '', $reviewStatusLabels)); ?></dd>
            </div>
          </dl>
        </div>

        <form method="post" action="<?php echo $h($reviewUrl); ?>" class="agency-review-property-form">
          <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
          <input type="hidden" name="action" value="update_statement_property" />
          <input type="hidden" name="document_id" value="<?php echo $h($selectedDocumentId); ?>" />
          <label>Propriété par défaut
            <select name="rental_property_id">
              <option value="">Non rattaché</option>
              <?php foreach ($properties as $property): ?>
                <?php
                if (!is_array($property) || !is_numeric($property['id'] ?? null)) {
                    continue;
                }
                $propertyId = (int) $property['id'];
                ?>
                <option value="<?php echo $h($propertyId); ?>" <?php echo $selectedPropertyId === $propertyId ? 'selected' : ''; ?>>
                  <?php echo $h($labelForProperty($property)); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <?php if ($properties === []): ?>
            <p class="notice notice-error">Aucune propriété disponible pour ce compte. Créez d'abord une propriété, puis revenez valider ce document.</p>
            <p class="private-actions"><a href="<?php echo $h($propertiesUrl); ?>">Créer une propriété</a></p>
          <?php else: ?>
            <p class="muted">Applique aux lignes sans choix manuel.</p>
          <?php endif; ?>
          <button type="submit">Rattacher</button>
        </form>
      </div>

      <?php if ($reconciliation !== []): ?>
        <?php
        $reconciliationStatus = is_string($reconciliation['status'] ?? null) ? (string) $reconciliation['status'] : 'review_required';
        $reconciliationLabels = [
            'ready' => 'Prêt pour synthèse',
            'review_required' => 'Contrôle requis',
            'manual_entry_required' => 'OCR ou saisie manuelle',
            'source_document_missing' => 'Source à vérifier',
        ];
        $reconciliationAlerts = [];
        if (!empty($reconciliation['manualEntryRequired'])) {
            $reconciliationAlerts[] = 'Texte insuffisant : document à traiter par OCR ou saisie manuelle.';
        }
        if (empty($reconciliation['sourceDocumentRetained'])) {
            $reconciliationAlerts[] = 'Justificatif source non rattaché au stockage privé.';
        }
        if ((int) ($reconciliation['duplicateCandidateCount'] ?? 0) > 0) {
            $reconciliationAlerts[] = (int) $reconciliation['duplicateCandidateCount'] . ' doublon potentiel de ligne.';
        }
        if ((int) ($reconciliation['missingPeriodLineCount'] ?? 0) > 0) {
            $reconciliationAlerts[] = (int) $reconciliation['missingPeriodLineCount'] . ' ligne fiscale sans période.';
        }
        if ((int) ($reconciliation['unclassifiedLineCount'] ?? 0) > 0) {
            $reconciliationAlerts[] = (int) $reconciliation['unclassifiedLineCount'] . ' ligne non classée.';
        }
        if ((int) ($reconciliation['sensitiveAwaitingReviewCount'] ?? 0) > 0) {
            $reconciliationAlerts[] = (int) $reconciliation['sensitiveAwaitingReviewCount'] . ' catégorie fiscale sensible en attente de contrôle.';
        }
        if (abs((float) ($reconciliation['transferDelta'] ?? 0.0)) > 0.05) {
            $reconciliationAlerts[] = 'Écart entre virements et net rapproché : ' . number_format((float) $reconciliation['transferDelta'], 2, ',', ' ') . ' €.';
        }
        ?>
        <div class="agency-reconciliation-panel">
          <div class="agency-reconciliation-status">
            <strong><?php echo $h($reconciliationLabels[$reconciliationStatus] ?? 'Contrôle requis'); ?></strong>
            <span><?php echo !empty($reconciliation['sourceDocumentRetained']) ? 'Justificatif conservé' : 'Justificatif manquant'; ?></span>
          </div>
          <dl class="agency-reconciliation-metrics">
            <div>
              <dt>Recettes</dt>
              <dd><?php echo $money($reconciliation['incomeAmount'] ?? 0.0); ?></dd>
            </div>
            <div>
              <dt>Charges</dt>
              <dd><?php echo $money($reconciliation['expenseAmount'] ?? 0.0); ?></dd>
            </div>
            <div>
              <dt>Net avant virement</dt>
              <dd><?php echo $money($reconciliation['netBeforeTransfer'] ?? 0.0); ?></dd>
            </div>
            <div>
              <dt>Virements</dt>
              <dd><?php echo $money($reconciliation['ownerTransferAmount'] ?? 0.0); ?></dd>
            </div>
            <div>
              <dt>Écart</dt>
              <dd><?php echo $money($reconciliation['transferDelta'] ?? 0.0); ?></dd>
            </div>
          </dl>
          <?php if ($reconciliationAlerts !== []): ?>
            <ul class="agency-reconciliation-alerts">
              <?php foreach ($reconciliationAlerts as $alert): ?>
                <li><?php echo $h($alert); ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($issues !== []): ?>
        <h3>Anomalies</h3>
        <ul>
          <?php foreach ($issues as $issue): ?>
            <?php if (!is_array($issue)) { continue; } ?>
            <li><?php echo $h(($issue['severity'] ?? 'warning') . ' - ' . ($issue['message'] ?? '')); ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if ($lines === []): ?>
        <p class="muted">Aucune ligne exploitable dans ce document.</p>
      <?php else: ?>
        <h3>Lignes à traiter</h3>
        <p class="muted">Le contrôle fiscal confirme manuellement les catégories sensibles avant validation, afin qu’une ligne à impact fiscal ne soit pas envoyée par erreur dans les synthèses.</p>
        <form method="post" action="<?php echo $h($reviewUrl); ?>" class="agency-review-bulk-form">
          <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
          <input type="hidden" name="document_id" value="<?php echo $h($selectedDocumentId); ?>" />
          <div class="private-table-wrap agency-review-lines-wrap">
            <div class="agency-review-lines" role="table" aria-label="Lignes agence à traiter">
              <div class="agency-review-lines-head" role="row">
                <span>Libellé</span>
                <span>Propriété</span>
                <span>Bien locatif</span>
                <span>Catégorie</span>
                <span>Montant</span>
                <span>Débit</span>
                <span>Crédit</span>
                <span>Actions</span>
              </div>
            <?php foreach ($lines as $line): ?>
              <?php
              if (!is_array($line) || !is_numeric($line['id'] ?? null)) {
                  continue;
              }
              $lineId = (int) $line['id'];
              $formId = 'agency-review-line-' . $lineId;
              $currentCategory = is_scalar($line['mappedCategory'] ?? null) ? (string) $line['mappedCategory'] : 'other';
              $requiresFiscalReview = in_array($currentCategory, $sensitiveCategories, true);
              $isLineValidated = (string) ($line['mappingStatus'] ?? '') === 'validated';
              $bulkValidationLabel = $requiresFiscalReview
                  ? 'Contrôle fiscal confirmé'
                  : 'Valider avec l’enregistrement';
              $linePropertyId = is_numeric($line['rentalPropertyId'] ?? null) ? (int) $line['rentalPropertyId'] : 0;
              $lineUnitId = is_numeric($line['rentalUnitId'] ?? null) ? (int) $line['rentalUnitId'] : 0;
              $hasLineFeedback = $lineFeedbackId === $lineId && ($lineNotice !== '' || $lineError !== '');
              $detected = [];
              if (is_scalar($line['propertyLabel'] ?? null) && trim((string) $line['propertyLabel']) !== '') {
                  $detected[] = 'Propriété détectée : ' . trim((string) $line['propertyLabel']);
              }
              if (is_scalar($line['unitLabel'] ?? null) && trim((string) $line['unitLabel']) !== '') {
                  $detected[] = 'Bien détecté : ' . trim((string) $line['unitLabel']);
              }
              if (is_scalar($line['tenantName'] ?? null) && trim((string) $line['tenantName']) !== '') {
                  $detected[] = 'Locataire : ' . trim((string) $line['tenantName']);
              }
              ?>
              <div id="<?php echo $h($formId); ?>" class="agency-review-line-row" role="row">
                <div class="agency-review-line-main" role="cell">
                  <strong><?php echo $h($line['rawLabel'] ?? ''); ?></strong>
                  <span class="muted">Page <?php echo $h((int) ($line['sourcePage'] ?? 1)); ?>, statut <?php echo $h($labelFromMap($line['mappingStatus'] ?? '', $mappingStatusLabels)); ?></span>
                  <?php if ($requiresFiscalReview): ?>
                    <small class="agency-line-warning">Controle fiscal à confirmer</small>
                  <?php endif; ?>
                  <?php if ($detected !== []): ?>
                    <small><?php echo $h(implode(' | ', $detected)); ?></small>
                  <?php endif; ?>
                </div>
                <label role="cell"><span>Propriété</span>
                  <select name="lines[<?php echo $h($lineId); ?>][rental_property_id]">
                    <option value="">Défaut</option>
                    <?php foreach ($properties as $property): ?>
                      <?php
                      if (!is_array($property) || !is_numeric($property['id'] ?? null)) {
                          continue;
                      }
                      $propertyId = (int) $property['id'];
                      ?>
                      <option value="<?php echo $h($propertyId); ?>" <?php echo $linePropertyId === $propertyId ? 'selected' : ''; ?>>
                        <?php echo $h($labelForProperty($property)); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label role="cell"><span>Bien locatif</span>
                  <select name="lines[<?php echo $h($lineId); ?>][rental_unit_id]">
                    <option value="">Non rattaché</option>
                    <?php foreach ($unitsByPropertyId as $propertyId => $propertyUnits): ?>
                      <?php
                      $groupLabel = isset($propertiesById[(int) $propertyId])
                          ? $labelForProperty($propertiesById[(int) $propertyId])
                          : 'Propriété #' . (int) $propertyId;
                      ?>
                      <optgroup label="<?php echo $h($groupLabel); ?>">
                        <?php foreach ($propertyUnits as $unit): ?>
                          <?php
                          if (!is_array($unit) || !is_numeric($unit['id'] ?? null)) {
                              continue;
                          }
                          $unitId = (int) $unit['id'];
                          ?>
                          <option value="<?php echo $h($unitId); ?>" <?php echo $lineUnitId === $unitId ? 'selected' : ''; ?>>
                            <?php echo $h($labelForUnit($unit)); ?>
                          </option>
                        <?php endforeach; ?>
                      </optgroup>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label role="cell"><span>Catégorie</span>
                  <select name="lines[<?php echo $h($lineId); ?>][mapped_category]">
                    <?php foreach ($categories as $category => $categoryLabel): ?>
                      <option value="<?php echo $h($category); ?>" <?php echo $currentCategory === (string) $category ? 'selected' : ''; ?>>
                        <?php echo $h($categoryLabel); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <input type="hidden" name="lines[<?php echo $h($lineId); ?>][period_start]" value="<?php echo $h($line['periodStart'] ?? ''); ?>" />
                <input type="hidden" name="lines[<?php echo $h($lineId); ?>][period_end]" value="<?php echo $h($line['periodEnd'] ?? ''); ?>" />
                <label role="cell"><span>Montant</span><input type="number" step="0.01" name="lines[<?php echo $h($lineId); ?>][amount]" value="<?php echo $amount($line['amount'] ?? null); ?>" /></label>
                <label role="cell"><span>Débit</span><input type="number" step="0.01" name="lines[<?php echo $h($lineId); ?>][debit_amount]" value="<?php echo $amount($line['debitAmount'] ?? null); ?>" /></label>
                <label role="cell"><span>Crédit</span><input type="number" step="0.01" name="lines[<?php echo $h($lineId); ?>][credit_amount]" value="<?php echo $amount($line['creditAmount'] ?? null); ?>" /></label>
                <div class="agency-review-line-actions" role="cell">
                  <?php if ($hasLineFeedback): ?>
                    <span
                      class="agency-review-line-feedback <?php echo $lineError !== '' ? 'is-error' : 'is-success'; ?>"
                      role="<?php echo $lineError !== '' ? 'alert' : 'status'; ?>"
                    >
                      <?php echo $h($lineError !== '' ? $lineError : $lineNotice); ?>
                    </span>
                  <?php endif; ?>
                  <label class="private-checkbox-inline agency-bulk-select">
                    <input type="checkbox" name="lines[<?php echo $h($lineId); ?>][bulk_validate]" value="1" <?php echo $isLineValidated ? 'checked' : ''; ?> />
                    <span><?php echo $h($bulkValidationLabel); ?></span>
                  </label>
                  <button type="submit" name="line_action[<?php echo $h($lineId); ?>]" value="correct_line" class="private-row-action">Corriger</button>
                  <button type="submit" name="line_action[<?php echo $h($lineId); ?>]" value="validate_line">Valider</button>
                  <button type="submit" name="line_action[<?php echo $h($lineId); ?>]" value="ignore_line" class="private-row-action">Ignorer</button>
                </div>
              </div>
            <?php endforeach; ?>
            </div>
          </div>
          <div class="private-actions agency-review-bulk-actions">
            <button type="submit" name="action" value="bulk_update_lines">Enregistrer et valider les lignes cochées</button>
          </div>
        </form>
      <?php endif; ?>

      <?php if (is_string($selectedDocument['maskedTextPreview'] ?? null) && trim((string) $selectedDocument['maskedTextPreview']) !== ''): ?>
        <h3>Extrait masqué</h3>
        <pre><?php echo $h($selectedDocument['maskedTextPreview']); ?></pre>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</section>
