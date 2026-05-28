<?php
$documents = is_array($viewModel['agencyReviewDocuments'] ?? null) ? $viewModel['agencyReviewDocuments'] : [];
$selectedDocument = is_array($viewModel['agencyReviewSelectedDocument'] ?? null) ? $viewModel['agencyReviewSelectedDocument'] : null;
$properties = is_array($viewModel['agencyReviewProperties'] ?? null) ? $viewModel['agencyReviewProperties'] : [];
$categories = is_array($viewModel['agencyReviewCategories'] ?? null) ? $viewModel['agencyReviewCategories'] : [];
$csrfToken = is_string($viewModel['rentalCsrfToken'] ?? null) ? (string) $viewModel['rentalCsrfToken'] : '';
$notice = is_string($viewModel['rentalNotice'] ?? null) ? (string) $viewModel['rentalNotice'] : '';
$error = is_string($viewModel['rentalError'] ?? null) ? (string) $viewModel['rentalError'] : '';
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
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>

  <?php if ($notice !== ''): ?><p class="notice notice-success"><?php echo $h($notice); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo $h($error); ?></p><?php endif; ?>

  <section class="card">
    <h2>Documents agence à classer</h2>
    <p class="muted">Avant de rattacher un relevé agence, le bien locatif doit exister dans Biens et locations.</p>
    <p class="private-actions">
      <a href="<?php echo $h($propertiesUrl); ?>">Créer ou modifier un bien</a>
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
    <section class="card">
      <h2>Revue du document</h2>
      <dl>
        <dt>Fichier</dt><dd><?php echo $h($selectedDocument['filename'] ?? ''); ?></dd>
        <dt>Agence</dt><dd><?php echo $h($selectedDocument['detectedAgency'] ?? $selectedDocument['batchAgencyName'] ?? ''); ?></dd>
        <dt>Profil</dt><dd><?php echo $h($selectedDocument['parserProfile'] ?? ''); ?></dd>
        <dt>Periode</dt><dd><?php echo $h((string) ($selectedDocument['statementPeriodStart'] ?? '') . ' - ' . (string) ($selectedDocument['statementPeriodEnd'] ?? '')); ?></dd>
        <dt>Statut</dt><dd><?php echo $h($selectedDocument['reviewStatus'] ?? ''); ?></dd>
      </dl>

      <form method="post" action="<?php echo $h($reviewUrl); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
        <input type="hidden" name="action" value="update_statement_property" />
        <input type="hidden" name="document_id" value="<?php echo $h($selectedDocumentId); ?>" />
        <label>Bien rattache
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
          <p class="notice notice-error">Aucun bien disponible pour ce compte. Créez d'abord un bien locatif, puis revenez classer ce document.</p>
          <p class="private-actions"><a href="<?php echo $h($propertiesUrl); ?>">Créer un bien locatif</a></p>
        <?php else: ?>
          <p class="muted">La liste ci-dessus reprend les biens locatifs créés dans Biens et locations.</p>
        <?php endif; ?>
        <button type="submit">Rattacher</button>
      </form>

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
        <?php foreach ($lines as $line): ?>
          <?php
          if (!is_array($line) || !is_numeric($line['id'] ?? null)) {
              continue;
          }
          $lineId = (int) $line['id'];
          $currentCategory = is_scalar($line['mappedCategory'] ?? null) ? (string) $line['mappedCategory'] : 'other';
          ?>
          <form method="post" action="<?php echo $h($reviewUrl); ?>" class="private-review-line">
            <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
            <input type="hidden" name="document_id" value="<?php echo $h($selectedDocumentId); ?>" />
            <input type="hidden" name="line_id" value="<?php echo $h($lineId); ?>" />
            <p>
              <strong><?php echo $h($line['rawLabel'] ?? ''); ?></strong>
              <span class="muted">Page <?php echo $h((int) ($line['sourcePage'] ?? 1)); ?>, statut <?php echo $h($line['mappingStatus'] ?? ''); ?></span>
            </p>
            <label>Categorie
              <select name="mapped_category">
                <?php foreach ($categories as $category => $categoryLabel): ?>
                  <option value="<?php echo $h($category); ?>" <?php echo $currentCategory === (string) $category ? 'selected' : ''; ?>>
                    <?php echo $h($categoryLabel); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Debut <input type="date" name="period_start" value="<?php echo $h($line['periodStart'] ?? ''); ?>" /></label>
            <label>Fin <input type="date" name="period_end" value="<?php echo $h($line['periodEnd'] ?? ''); ?>" /></label>
            <label>Montant <input type="number" step="0.01" name="amount" value="<?php echo $amount($line['amount'] ?? null); ?>" /></label>
            <label>Debit <input type="number" step="0.01" name="debit_amount" value="<?php echo $amount($line['debitAmount'] ?? null); ?>" /></label>
            <label>Credit <input type="number" step="0.01" name="credit_amount" value="<?php echo $amount($line['creditAmount'] ?? null); ?>" /></label>
            <p class="private-actions">
              <button type="submit" name="action" value="correct_line">Corriger</button>
              <button type="submit" name="action" value="validate_line">Valider</button>
              <button type="submit" name="action" value="ignore_line">Ignorer</button>
            </p>
          </form>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if (is_string($selectedDocument['maskedTextPreview'] ?? null) && trim((string) $selectedDocument['maskedTextPreview']) !== ''): ?>
        <h3>Extrait masque</h3>
        <pre><?php echo $h($selectedDocument['maskedTextPreview']); ?></pre>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</section>
