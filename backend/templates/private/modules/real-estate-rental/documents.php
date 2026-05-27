<?php
$properties = is_array($viewModel['rentalProperties'] ?? null) ? $viewModel['rentalProperties'] : [];
$units = is_array($viewModel['rentalUnits'] ?? null) ? $viewModel['rentalUnits'] : [];
$leases = is_array($viewModel['rentalLeases'] ?? null) ? $viewModel['rentalLeases'] : [];
$documents = is_array($viewModel['rentalDocuments'] ?? null) ? $viewModel['rentalDocuments'] : [];
$csrfToken = is_string($viewModel['rentalCsrfToken'] ?? null) ? (string) $viewModel['rentalCsrfToken'] : '';
$notice = is_string($viewModel['rentalNotice'] ?? null) ? (string) $viewModel['rentalNotice'] : '';
$error = is_string($viewModel['rentalError'] ?? null) ? (string) $viewModel['rentalError'] : '';
$urls = is_array($viewModel['rentalUrls'] ?? null) ? $viewModel['rentalUrls'] : [];
$propertyNames = [];
foreach ($properties as $property) {
    if (is_array($property) && is_numeric($property['id'] ?? null)) {
        $propertyNames[(int) $property['id']] = (string) ($property['name'] ?? ('Bien #' . (int) $property['id']));
    }
}
?>
<section>
  <p class="muted">
    <a href="<?php echo htmlspecialchars((string) ($urls['documents'] ?? private_portal_url('rental_documents')), ENT_QUOTES, 'UTF-8'); ?>">Documents</a>
    · <a href="<?php echo htmlspecialchars((string) ($urls['summary'] ?? private_portal_url('rental_summary')), ENT_QUOTES, 'UTF-8'); ?>">Synthese</a>
  </p>
  <?php if ($notice !== ''): ?><p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <section class="card">
    <h2>Ajouter un document locatif</h2>
    <?php if ($propertyNames === []): ?>
      <p class="muted">Créer d'abord un bien locatif autorise.</p>
    <?php else: ?>
      <form method="post" action="<?php echo htmlspecialchars((string) ($urls['documents'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="action" value="upload_document" />
        <label>Bien
          <select name="rental_property_id" required>
            <?php foreach ($propertyNames as $id => $name): ?>
              <option value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Lot optionnel
          <select name="rental_unit_id">
            <option value="">Non rattache</option>
            <?php foreach ($units as $unit): ?>
              <?php if (!is_array($unit) || !is_numeric($unit['id'] ?? null)) { continue; } ?>
              <option value="<?php echo htmlspecialchars((string) (int) $unit['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($unit['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Bail optionnel
          <select name="rental_lease_id">
            <option value="">Non rattache</option>
            <?php foreach ($leases as $lease): ?>
              <?php if (!is_array($lease) || !is_numeric($lease['id'] ?? null)) { continue; } ?>
              <option value="<?php echo htmlspecialchars((string) (int) $lease['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($lease['tenantName'] ?? 'Bail'), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Fichier <input type="file" name="rental_document_file" required /></label>
        <button type="submit">Envoyer le document</button>
      </form>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2>Documents locatifs</h2>
    <?php if ($documents === []): ?>
      <p class="muted">Aucun document locatif.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Bien</th><th>Nom</th><th>Poids</th><th>Ajoute le</th></tr></thead>
        <tbody>
          <?php foreach ($documents as $document): ?>
            <?php if (!is_array($document)) { continue; } ?>
            <?php $documentId = is_string($document['documentId'] ?? null) ? (string) $document['documentId'] : ''; ?>
            <?php if ($documentId === '') { continue; } ?>
            <tr>
              <td><?php echo htmlspecialchars((string) ($document['propertyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><a href="<?php echo htmlspecialchars(rtrim((string) ($urls['documents'] ?? ''), '/') . '/' . rawurlencode($documentId), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($document['originalName'] ?? $documentId), ENT_QUOTES, 'UTF-8'); ?></a></td>
              <td><?php echo htmlspecialchars(number_format(((int) ($document['sizeBytes'] ?? 0)) / 1024, 1, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> Ko</td>
              <td><?php echo htmlspecialchars((string) ($document['uploadedAt'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</section>
