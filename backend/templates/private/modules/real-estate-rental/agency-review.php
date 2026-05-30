<?php
$documents = is_array($viewModel['agencyReviewDocuments'] ?? null) ? $viewModel['agencyReviewDocuments'] : [];
$selectedDocument = is_array($viewModel['agencyReviewSelectedDocument'] ?? null) ? $viewModel['agencyReviewSelectedDocument'] : null;
$properties = is_array($viewModel['agencyReviewProperties'] ?? null) ? $viewModel['agencyReviewProperties'] : [];
$units = is_array($viewModel['agencyReviewUnits'] ?? null) ? $viewModel['agencyReviewUnits'] : [];
$categories = is_array($viewModel['agencyReviewCategories'] ?? null) ? $viewModel['agencyReviewCategories'] : [];
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
$labelForUnit = static function (array $unit): string {
    $label = is_scalar($unit['label'] ?? null) ? trim((string) $unit['label']) : '';
    $type = is_scalar($unit['unitType'] ?? null) ? trim((string) $unit['unitType']) : '';
    return trim($label . ($type !== '' ? ' - ' . $type : ''));
};
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>

  <?php if ($notice !== ''): ?><p class="notice notice-success"><?php echo $h($notice); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo $h($error); ?></p><?php endif; ?>

  <section class="card">
    <h2>Documents agence à classer</h2>
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
            <th>Periode</th>
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
              <td><?php echo $h($document['detectedDocumentType'] ?? 'unknown'); ?></td>
              <td><?php echo $h($period); ?></td>
              <td><?php echo $h((int) ($document['statementLineCount'] ?? 0)); ?></td>
              <td><?php echo $h((int) ($document['openIssueCount'] ?? 0)); ?></td>
              <td><?php echo $h($document['reviewStatus'] ?? ''); ?></td>
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
          <h2>Revue du document</h2>
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
              <dt>Periode</dt>
              <dd><?php echo $h((string) ($selectedDocument['statementPeriodStart'] ?? '') . ' - ' . (string) ($selectedDocument['statementPeriodEnd'] ?? '')); ?></dd>
            </div>
            <div>
              <dt>Statut</dt>
              <dd><?php echo $h($selectedDocument['reviewStatus'] ?? ''); ?></dd>
            </div>
          </dl>
        </div>

        <form method="post" action="<?php echo $h($reviewUrl); ?>" class="agency-review-property-form">
          <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
          <input type="hidden" name="action" value="update_statement_property" />
          <input type="hidden" name="document_id" value="<?php echo $h($selectedDocumentId); ?>" />
          <label>Propriété par défaut
            <select name="rental_property_id">
              <option value="">Non rattache</option>
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
            <p class="notice notice-error">Aucune propriété disponible pour ce compte. Créez d'abord une propriété, puis revenez classer ce document.</p>
            <p class="private-actions"><a href="<?php echo $h($propertiesUrl); ?>">Créer une propriété</a></p>
          <?php else: ?>
            <p class="muted">Applique aux lignes sans choix manuel.</p>
          <?php endif; ?>
          <button type="submit">Rattacher</button>
        </form>
      </div>

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
        <h3>Lignes a traiter</h3>
        <div class="private-table-wrap agency-review-lines-wrap">
          <div class="agency-review-lines" role="table" aria-label="Lignes agence a traiter">
            <div class="agency-review-lines-head" role="row">
              <span>Libelle</span>
              <span>Propriété</span>
              <span>Bien locatif</span>
              <span>Categorie</span>
              <span>Debut</span>
              <span>Fin</span>
              <span>Montant</span>
              <span>Debit</span>
              <span>Credit</span>
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
              $linePropertyId = is_numeric($line['rentalPropertyId'] ?? null) ? (int) $line['rentalPropertyId'] : 0;
              $lineUnitId = is_numeric($line['rentalUnitId'] ?? null) ? (int) $line['rentalUnitId'] : 0;
              $hasLineFeedback = $lineFeedbackId === $lineId && ($lineNotice !== '' || $lineError !== '');
              $detected = [];
              if (is_scalar($line['propertyLabel'] ?? null) && trim((string) $line['propertyLabel']) !== '') {
                  $detected[] = 'Propriete detectee: ' . trim((string) $line['propertyLabel']);
              }
              if (is_scalar($line['unitLabel'] ?? null) && trim((string) $line['unitLabel']) !== '') {
                  $detected[] = 'Bien detecte: ' . trim((string) $line['unitLabel']);
              }
              if (is_scalar($line['tenantName'] ?? null) && trim((string) $line['tenantName']) !== '') {
                  $detected[] = 'Locataire: ' . trim((string) $line['tenantName']);
              }
              ?>
              <form id="<?php echo $h($formId); ?>" method="post" action="<?php echo $h($reviewUrl); ?>" class="agency-review-line-row" role="row">
                <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
                <input type="hidden" name="document_id" value="<?php echo $h($selectedDocumentId); ?>" />
                <input type="hidden" name="line_id" value="<?php echo $h($lineId); ?>" />
                <div class="agency-review-line-main" role="cell">
                  <strong><?php echo $h($line['rawLabel'] ?? ''); ?></strong>
                  <span class="muted">Page <?php echo $h((int) ($line['sourcePage'] ?? 1)); ?>, statut <?php echo $h($line['mappingStatus'] ?? ''); ?></span>
                  <?php if ($detected !== []): ?>
                    <small><?php echo $h(implode(' | ', $detected)); ?></small>
                  <?php endif; ?>
                </div>
                <label role="cell"><span>Propriété</span>
                  <select name="rental_property_id" data-private-auto-submit="validate_line">
                    <option value="">Defaut</option>
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
                  <select name="rental_unit_id" data-private-auto-submit="validate_line">
                    <option value="">Non rattache</option>
                    <?php foreach ($unitsByPropertyId as $propertyId => $propertyUnits): ?>
                      <?php
                      $groupLabel = isset($propertiesById[(int) $propertyId])
                          ? $labelForProperty($propertiesById[(int) $propertyId])
                          : 'Propriete #' . (int) $propertyId;
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
                <label role="cell"><span>Categorie</span>
                  <select name="mapped_category" data-private-auto-submit="validate_line">
                    <?php foreach ($categories as $category => $categoryLabel): ?>
                      <option value="<?php echo $h($category); ?>" <?php echo $currentCategory === (string) $category ? 'selected' : ''; ?>>
                        <?php echo $h($categoryLabel); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label role="cell"><span>Debut</span><input type="date" name="period_start" value="<?php echo $h($line['periodStart'] ?? ''); ?>" /></label>
                <label role="cell"><span>Fin</span><input type="date" name="period_end" value="<?php echo $h($line['periodEnd'] ?? ''); ?>" /></label>
                <label role="cell"><span>Montant</span><input type="number" step="0.01" name="amount" value="<?php echo $amount($line['amount'] ?? null); ?>" /></label>
                <label role="cell"><span>Debit</span><input type="number" step="0.01" name="debit_amount" value="<?php echo $amount($line['debitAmount'] ?? null); ?>" /></label>
                <label role="cell"><span>Credit</span><input type="number" step="0.01" name="credit_amount" value="<?php echo $amount($line['creditAmount'] ?? null); ?>" /></label>
                <div class="agency-review-line-actions" role="cell">
                  <?php if ($hasLineFeedback): ?>
                    <span
                      class="agency-review-line-feedback <?php echo $lineError !== '' ? 'is-error' : 'is-success'; ?>"
                      role="<?php echo $lineError !== '' ? 'alert' : 'status'; ?>"
                    >
                      <?php echo $h($lineError !== '' ? $lineError : $lineNotice); ?>
                    </span>
                  <?php endif; ?>
                  <button type="submit" name="action" value="correct_line" class="private-row-action">Corriger</button>
                  <button type="submit" name="action" value="validate_line">Valider</button>
                  <button type="submit" name="action" value="ignore_line" class="private-row-action">Ignorer</button>
                </div>
              </form>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if (is_string($selectedDocument['maskedTextPreview'] ?? null) && trim((string) $selectedDocument['maskedTextPreview']) !== ''): ?>
        <h3>Extrait masque</h3>
        <pre><?php echo $h($selectedDocument['maskedTextPreview']); ?></pre>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</section>
