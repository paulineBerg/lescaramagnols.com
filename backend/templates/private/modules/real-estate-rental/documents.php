<?php
$properties = is_array($viewModel['rentalProperties'] ?? null) ? $viewModel['rentalProperties'] : [];
$units = is_array($viewModel['rentalUnits'] ?? null) ? $viewModel['rentalUnits'] : [];
$leases = is_array($viewModel['rentalLeases'] ?? null) ? $viewModel['rentalLeases'] : [];
$documents = is_array($viewModel['rentalDocuments'] ?? null) ? $viewModel['rentalDocuments'] : [];
$csrfToken = is_string($viewModel['rentalCsrfToken'] ?? null) ? (string) $viewModel['rentalCsrfToken'] : '';
$notice = is_string($viewModel['rentalNotice'] ?? null) ? (string) $viewModel['rentalNotice'] : '';
$error = is_string($viewModel['rentalError'] ?? null) ? (string) $viewModel['rentalError'] : '';
$urls = is_array($viewModel['rentalUrls'] ?? null) ? $viewModel['rentalUrls'] : [];
$mailDefaults = is_array($viewModel['rentalMailDefaults'] ?? null) ? $viewModel['rentalMailDefaults'] : [];
$createDialogId = 'rental-document-create-dialog';
$bulkDialogId = 'rental-document-bulk-dialog';
$dangerDialogId = 'rental-document-danger-dialog';
$propertyNames = [];
foreach ($properties as $property) {
    if (is_array($property) && is_numeric($property['id'] ?? null)) {
        $propertyNames[(int) $property['id']] = (string) ($property['name'] ?? ('Propriété #' . (int) $property['id']));
    }
}
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>
  <?php if ($notice !== ''): ?><p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <section class="card private-list-section" data-private-filter-scope>
    <div class="private-list-header">
      <div>
        <h2>Documents locatifs</h2>
        <p class="muted">Fichiers rattachés aux propriétés, biens locatifs ou baux.</p>
      </div>
      <div class="private-list-filter-actions">
        <button type="button" class="private-create-button" data-private-dialog-open="<?php echo htmlspecialchars($createDialogId, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $propertyNames === [] ? ' disabled' : ''; ?>>Ajouter un document</button>
        <?php if ($documents !== []): ?>
          <button type="button" class="private-button-secondary" data-private-dialog-open="<?php echo htmlspecialchars($bulkDialogId, ENT_QUOTES, 'UTF-8'); ?>">Envoyer une sélection</button>
          <button type="button" class="private-button-danger" data-private-dialog-open="<?php echo htmlspecialchars($dangerDialogId, ENT_QUOTES, 'UTF-8'); ?>">Suppressions</button>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($propertyNames === []): ?>
      <p class="muted">Créer d'abord une propriété autorisée.</p>
    <?php endif; ?>
    <?php if ($documents === []): ?>
      <p class="muted">Aucun document locatif.</p>
    <?php else: ?>
      <div class="private-list-tools">
        <div class="private-list-filter-grid">
          <label>Recherche <input type="search" placeholder="Nom ou propriété" data-private-filter="text" /></label>
          <div class="private-list-filter-actions">
            <button type="button" class="private-button-secondary" data-private-filter-reset>Réinitialiser</button>
          </div>
        </div>
      </div>
      <div class="private-table-wrap">
      <table>
        <thead><tr><th>Propriété</th><th>Nom</th><th>Rattachement</th><th>Poids</th><th>Ajoute le</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($documents as $document): ?>
            <?php if (!is_array($document)) { continue; } ?>
            <?php $documentId = is_string($document['documentId'] ?? null) ? (string) $document['documentId'] : ''; ?>
            <?php if ($documentId === '') { continue; } ?>
            <tr data-private-filter-row data-filter-text="<?php echo htmlspecialchars(strtolower(trim((string) ($document['propertyName'] ?? '') . ' ' . (string) ($document['originalName'] ?? $documentId))), ENT_QUOTES, 'UTF-8'); ?>">
              <td><?php echo htmlspecialchars((string) ($document['propertyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><a href="<?php echo htmlspecialchars(rtrim((string) ($urls['documents'] ?? ''), '/') . '/' . rawurlencode($documentId), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($document['originalName'] ?? $documentId), ENT_QUOTES, 'UTF-8'); ?></a></td>
              <td><?php echo htmlspecialchars((string) (($document['expenseLabel'] ?? '') ?: ($document['unitLabel'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(number_format(((int) ($document['sizeBytes'] ?? 0)) / 1024, 1, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> Ko</td>
              <td><?php echo htmlspecialchars((string) ($document['uploadedAt'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <form method="post" action="<?php echo htmlspecialchars((string) ($urls['documents'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
                  <input type="hidden" name="action" value="delete_document" />
                  <input type="hidden" name="document_id" value="<?php echo htmlspecialchars($documentId, ENT_QUOTES, 'UTF-8'); ?>" />
                  <button class="button-small button-danger" type="submit">Supprimer</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <tr class="private-empty-row" data-private-filter-empty hidden><td colspan="6">Aucun document ne correspond aux filtres.</td></tr>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </section>

  <dialog class="private-dialog" id="<?php echo htmlspecialchars($createDialogId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="<?php echo htmlspecialchars($createDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">
    <div class="private-dialog-panel">
      <header class="private-dialog-header">
        <h3 id="<?php echo htmlspecialchars($createDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">Ajouter un document locatif</h3>
        <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
      </header>
      <?php if ($propertyNames === []): ?>
        <p class="muted">Créer d'abord une propriété autorisée.</p>
      <?php else: ?>
        <form method="post" action="<?php echo htmlspecialchars((string) ($urls['documents'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" enctype="multipart/form-data" data-rental-document-form>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
          <input type="hidden" name="action" value="upload_document" />
          <label>Propriété
            <select name="rental_property_id" required>
              <?php foreach ($propertyNames as $id => $name): ?>
                <option value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Bien locatif optionnel
            <select name="rental_unit_id" data-rental-document-unit-select>
              <option value="">Non rattaché</option>
              <?php foreach ($units as $unit): ?>
                <?php if (!is_array($unit) || !is_numeric($unit['id'] ?? null)) { continue; } ?>
                <?php $unitPropertyId = is_numeric($unit['rentalPropertyId'] ?? null) ? (int) $unit['rentalPropertyId'] : 0; ?>
                <option value="<?php echo htmlspecialchars((string) (int) $unit['id'], ENT_QUOTES, 'UTF-8'); ?>" data-property-id="<?php echo htmlspecialchars((string) $unitPropertyId, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($unit['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Bail optionnel
            <select name="rental_lease_id" data-rental-document-lease-select>
              <option value="">Non rattaché</option>
              <?php foreach ($leases as $lease): ?>
                <?php if (!is_array($lease) || !is_numeric($lease['id'] ?? null)) { continue; } ?>
                <option value="<?php echo htmlspecialchars((string) (int) $lease['id'], ENT_QUOTES, 'UTF-8'); ?>" data-unit-id="<?php echo htmlspecialchars((string) (int) ($lease['rentalUnitId'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($lease['tenantName'] ?? 'Bail'), ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Fichier <input type="file" name="rental_document_file" required /></label>
          <button type="submit">Envoyer le document</button>
        </form>
      <?php endif; ?>
    </div>
  </dialog>

  <?php if ($documents !== []): ?>
    <dialog class="private-dialog" id="<?php echo htmlspecialchars($bulkDialogId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="<?php echo htmlspecialchars($bulkDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">
      <div class="private-dialog-panel">
        <header class="private-dialog-header">
          <h3 id="<?php echo htmlspecialchars($bulkDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">Envoyer ou supprimer une sélection</h3>
          <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
        </header>
        <form method="post" action="<?php echo htmlspecialchars((string) ($urls['documents'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
          <fieldset>
            <legend>Documents</legend>
            <?php foreach ($documents as $document): ?>
              <?php if (!is_array($document)) { continue; } ?>
              <?php $documentId = is_string($document['documentId'] ?? null) ? (string) $document['documentId'] : ''; ?>
              <?php if ($documentId === '') { continue; } ?>
              <label>
                <input type="checkbox" name="document_ids[]" value="<?php echo htmlspecialchars($documentId, ENT_QUOTES, 'UTF-8'); ?>" />
                <?php echo htmlspecialchars((string) ($document['originalName'] ?? $documentId), ENT_QUOTES, 'UTF-8'); ?>
              </label>
            <?php endforeach; ?>
          </fieldset>
          <label>Email destinataire <input type="email" name="recipient_email" maxlength="190" /></label>
          <label>Objet <input type="text" name="subject" maxlength="180" value="<?php echo htmlspecialchars((string) ($mailDefaults['subject'] ?? 'Document locatif'), ENT_QUOTES, 'UTF-8'); ?>" /></label>
          <label>Message <textarea name="message" maxlength="4000"><?php echo htmlspecialchars((string) ($mailDefaults['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea></label>
          <button type="submit" name="action" value="email_documents">Envoyer par email</button>
          <button class="private-button-danger" type="submit" name="action" value="delete_selected_documents">Supprimer la sélection</button>
        </form>
      </div>
    </dialog>

    <dialog class="private-dialog" id="<?php echo htmlspecialchars($dangerDialogId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="<?php echo htmlspecialchars($dangerDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">
      <div class="private-dialog-panel">
        <header class="private-dialog-header">
          <h3 id="<?php echo htmlspecialchars($dangerDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">Suppressions globales</h3>
          <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
        </header>
        <form method="post" action="<?php echo htmlspecialchars((string) ($urls['documents'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
          <input type="hidden" name="action" value="delete_all_documents" />
          <label>Confirmer avec SUPPRIMER <input type="text" name="confirm_delete_all" autocomplete="off" /></label>
          <button class="private-button-danger" type="submit">Supprimer tous les documents locatifs</button>
        </form>
        <form method="post" action="<?php echo htmlspecialchars((string) ($urls['documents'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
          <input type="hidden" name="action" value="purge_rental_data" />
          <label>Confirmer avec SUPPRIMER <input type="text" name="confirm_purge" autocomplete="off" /></label>
          <button class="private-button-danger" type="submit">Supprimer toutes les informations locatives</button>
        </form>
      </div>
    </dialog>
  <?php endif; ?>
  <script>
    (() => {
      document.querySelectorAll('[data-rental-document-form]').forEach((form) => {
        const propertySelect = form.querySelector('[name="rental_property_id"]');
        const unitSelect = form.querySelector('[data-rental-document-unit-select]');
        const leaseSelect = form.querySelector('[data-rental-document-lease-select]');
        if (!(unitSelect instanceof HTMLSelectElement) || !(leaseSelect instanceof HTMLSelectElement)) {
          return;
        }

        const unitOptions = Array.from(unitSelect.options);
        const leaseOptions = Array.from(leaseSelect.options);
        const refreshLeases = () => {
          const selectedUnitId = unitSelect.value;
          let selectedStillVisible = leaseSelect.value === '';

          leaseOptions.forEach((option) => {
            const isPlaceholder = option.value === '';
            const matches = selectedUnitId !== '' && option.dataset.unitId === selectedUnitId;
            option.hidden = !isPlaceholder && !matches;
            option.disabled = !isPlaceholder && !matches;
            if (option.selected && (isPlaceholder || matches)) {
              selectedStillVisible = true;
            }
          });

          if (!selectedStillVisible) {
            leaseSelect.value = '';
          }
        };

        const refreshUnits = () => {
          if (!(propertySelect instanceof HTMLSelectElement)) {
            refreshLeases();
            return;
          }

          const selectedPropertyId = propertySelect.value;
          let selectedStillVisible = unitSelect.value === '';

          unitOptions.forEach((option) => {
            const isPlaceholder = option.value === '';
            const matches = selectedPropertyId !== '' && option.dataset.propertyId === selectedPropertyId;
            option.hidden = !isPlaceholder && !matches;
            option.disabled = !isPlaceholder && !matches;
            if (option.selected && (isPlaceholder || matches)) {
              selectedStillVisible = true;
            }
          });

          if (!selectedStillVisible) {
            unitSelect.value = '';
          }

          refreshLeases();
        };

        if (propertySelect instanceof HTMLSelectElement) {
          propertySelect.addEventListener('change', refreshUnits);
        }
        unitSelect.addEventListener('change', refreshLeases);
        refreshUnits();
      });
    })();
  </script>
</section>
