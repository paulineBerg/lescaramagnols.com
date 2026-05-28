<?php
$documents = is_array($viewModel['agencyImportDocuments'] ?? null) ? $viewModel['agencyImportDocuments'] : [];
$batches = is_array($viewModel['agencyImportBatches'] ?? null) ? $viewModel['agencyImportBatches'] : [];
$csrfToken = is_string($viewModel['rentalCsrfToken'] ?? null) ? (string) $viewModel['rentalCsrfToken'] : '';
$notice = is_string($viewModel['rentalNotice'] ?? null) ? (string) $viewModel['rentalNotice'] : '';
$error = is_string($viewModel['rentalError'] ?? null) ? (string) $viewModel['rentalError'] : '';
$urls = is_array($viewModel['rentalUrls'] ?? null) ? $viewModel['rentalUrls'] : [];
$actionUrl = (string) ($urls['agencyImports'] ?? private_portal_url('rental_agency_imports'));
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>
  <?php if ($notice !== ''): ?><p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <section class="card">
    <h2>Importer un document agence</h2>
    <form method="post" action="<?php echo htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8'); ?>" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
      <input type="hidden" name="action" value="agency_import" />
      <label>Agence
        <input type="text" name="agency_name" maxlength="120" placeholder="ASG IMMOBILIER" />
      </label>
      <label>Fichier agence
        <input type="file" name="agency_import_file" accept=".pdf,.txt,application/pdf,text/plain" required />
      </label>
      <button type="submit">Importer</button>
    </form>
  </section>

  <section class="card">
    <h2>Documents a revoir</h2>
    <?php if ($documents === []): ?>
      <p class="muted">Aucun document agence importé.</p>
    <?php else: ?>
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
          </tr>
        </thead>
        <tbody>
          <?php foreach ($documents as $document): ?>
            <?php if (!is_array($document)) { continue; } ?>
            <tr>
              <td><a href="<?php echo htmlspecialchars((string) ($urls['agencyReview'] ?? private_portal_url('rental_agency_review')) . '?document_id=' . (int) ($document['id'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($document['filename'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></td>
              <td><?php echo htmlspecialchars((string) ($document['detectedDocumentType'] ?? 'unknown'), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($document['parserProfile'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($document['textExtractionStatus'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) (int) ($document['statementLineCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) (int) ($document['openIssueCount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($document['reviewStatus'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2>Lots recents</h2>
    <?php if ($batches === []): ?>
      <p class="muted">Aucun lot d'import.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Date</th><th>Agence</th><th>Fichiers</th><th>Ignores</th><th>Doublons</th><th>Statut</th></tr></thead>
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
    <?php endif; ?>
  </section>
</section>
