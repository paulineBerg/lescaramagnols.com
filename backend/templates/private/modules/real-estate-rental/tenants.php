<?php
$properties = is_array($viewModel['rentalProperties'] ?? null) ? $viewModel['rentalProperties'] : [];
$units = is_array($viewModel['rentalUnits'] ?? null) ? $viewModel['rentalUnits'] : [];
$tenants = is_array($viewModel['rentalTenants'] ?? null) ? $viewModel['rentalTenants'] : [];
$csrfToken = is_string($viewModel['rentalCsrfToken'] ?? null) ? (string) $viewModel['rentalCsrfToken'] : '';
$notice = is_string($viewModel['rentalNotice'] ?? null) ? (string) $viewModel['rentalNotice'] : '';
$error = is_string($viewModel['rentalError'] ?? null) ? (string) $viewModel['rentalError'] : '';
$urls = is_array($viewModel['rentalUrls'] ?? null) ? $viewModel['rentalUrls'] : [];
$propertyNames = [];
foreach ($properties as $property) {
    if (is_array($property) && is_numeric($property['id'] ?? null)) {
        $propertyNames[(int) $property['id']] = (string) ($property['name'] ?? ('Propriété #' . (int) $property['id']));
    }
}
$unitLabels = [];
foreach ($units as $unit) {
    if (!is_array($unit) || !is_numeric($unit['id'] ?? null) || !is_numeric($unit['rentalPropertyId'] ?? null)) {
        continue;
    }
    $propertyId = (int) $unit['rentalPropertyId'];
    $unitLabels[(int) $unit['id']] = trim(
        (string) ($propertyNames[$propertyId] ?? ('Propriété #' . $propertyId))
        . ' - '
        . (string) ($unit['label'] ?? ('Bien locatif #' . (int) $unit['id']))
    );
}
$statuses = ['draft' => 'Brouillon', 'validated' => 'Valide', 'cancelled' => 'Annule'];
$createDialogId = 'rental-tenant-create-dialog';
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>

  <?php if ($notice !== ''): ?><p id="rental-feedback" class="notice notice-success private-feedback"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <section class="card private-list-section" data-private-filter-scope>
    <div class="private-list-header">
      <div>
        <h2>Locataires</h2>
        <p class="muted">Recherche par propriété, bien locatif, nom ou email.</p>
      </div>
      <button type="button" class="private-create-button" data-private-dialog-open="<?php echo htmlspecialchars($createDialogId, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $unitLabels === [] ? ' disabled' : ''; ?>>Créer un locataire</button>
    </div>
    <?php if ($unitLabels === []): ?>
      <p class="muted">Créer d'abord une propriété, puis un bien locatif avant d'ajouter un locataire.</p>
    <?php endif; ?>
    <?php if ($tenants === []): ?>
      <p class="muted">Aucun locataire enregistré.</p>
    <?php else: ?>
      <div class="private-list-tools">
        <div class="private-list-filter-grid">
          <label>Recherche <input type="search" placeholder="Nom, email, propriété" data-private-filter="text" /></label>
          <label>Statut
            <select data-private-filter="status">
              <option value="all">Tous</option>
              <?php foreach ($statuses as $value => $label): ?>
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
        <thead><tr><th>Propriété</th><th>Bien locatif</th><th>Locataire</th><th>Naissance</th><th>Contact</th><th>Profession</th><th>Statut</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($tenants as $tenant): ?>
            <?php if (!is_array($tenant)) { continue; } ?>
            <?php
            $tenantId = is_numeric($tenant['id'] ?? null) ? (int) $tenant['id'] : 0;
            $birthDate = is_string($tenant['birthDate'] ?? null) ? (string) $tenant['birthDate'] : '';
            $birthCity = is_string($tenant['birthCity'] ?? null) ? (string) $tenant['birthCity'] : '';
            $birthCountry = is_string($tenant['birthCountry'] ?? null) ? (string) $tenant['birthCountry'] : '';
            $birthLabel = trim($birthDate . ' ' . $birthCity . ' ' . $birthCountry);
            $contactLabel = trim((string) ($tenant['email'] ?? '') . ' ' . (string) ($tenant['phone'] ?? ''));
            $dialogId = 'rental-tenant-dialog-' . $tenantId;
            ?>
            <tr data-private-filter-row data-filter-text="<?php echo htmlspecialchars(strtolower(trim((string) ($tenant['propertyName'] ?? '') . ' ' . (string) ($tenant['unitLabel'] ?? '') . ' ' . (string) ($tenant['fullName'] ?? '') . ' ' . (string) ($tenant['lastName'] ?? '') . ' ' . (string) ($tenant['firstNames'] ?? '') . ' ' . (string) ($tenant['email'] ?? '') . ' ' . (string) ($tenant['phone'] ?? '') . ' ' . (string) ($tenant['occupation'] ?? '') . ' ' . (string) ($tenant['postalAddress'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?>" data-filter-status="<?php echo htmlspecialchars((string) ($tenant['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
              <td><?php echo htmlspecialchars((string) ($tenant['propertyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($tenant['unitLabel'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($tenant['fullName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($birthLabel, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($contactLabel, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($tenant['occupation'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($statuses[(string) ($tenant['status'] ?? '')] ?? ($tenant['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php if ($tenantId > 0): ?>
                  <button type="button" class="private-row-action" data-private-dialog-open="<?php echo htmlspecialchars($dialogId, ENT_QUOTES, 'UTF-8'); ?>">Modifier</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <tr class="private-empty-row" data-private-filter-empty hidden><td colspan="8">Aucun locataire ne correspond aux filtres.</td></tr>
        </tbody>
      </table>
      </div>

      <?php foreach ($tenants as $tenant): ?>
        <?php
        if (!is_array($tenant)) {
            continue;
        }
        $tenantId = is_numeric($tenant['id'] ?? null) ? (int) $tenant['id'] : 0;
        if ($tenantId <= 0) {
            continue;
        }
        $selectedUnitId = is_numeric($tenant['rentalUnitId'] ?? null) ? (int) $tenant['rentalUnitId'] : 0;
        $tenantStatus = is_string($tenant['status'] ?? null) ? (string) $tenant['status'] : 'draft';
        $dialogId = 'rental-tenant-dialog-' . $tenantId;
        ?>
        <dialog class="private-dialog" id="<?php echo htmlspecialchars($dialogId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="<?php echo htmlspecialchars($dialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">
          <div class="private-dialog-panel">
            <header class="private-dialog-header">
              <h3 id="<?php echo htmlspecialchars($dialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">Modifier le locataire</h3>
              <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
            </header>
            <form method="post" action="<?php echo htmlspecialchars((string) ($urls['tenants'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="update_tenant" />
              <input type="hidden" name="tenant_id" value="<?php echo htmlspecialchars((string) $tenantId, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="full_name" value="<?php echo htmlspecialchars((string) ($tenant['fullName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
              <label>Bien locatif
                <select name="rental_unit_id" required>
                  <option value="">Choisir un bien locatif</option>
                  <?php foreach ($unitLabels as $id => $label): ?>
                    <option value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedUnitId === $id ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <fieldset class="private-fieldset">
                <legend>Identité</legend>
                <label>Nom <input type="text" name="last_name" maxlength="120" value="<?php echo htmlspecialchars((string) ($tenant['lastName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
                <label>Prénoms <input type="text" name="first_names" maxlength="160" value="<?php echo htmlspecialchars((string) ($tenant['firstNames'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
                <label>Date de naissance <input type="date" name="birth_date" value="<?php echo htmlspecialchars((string) ($tenant['birthDate'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
                <label>Ville de naissance <input type="text" name="birth_city" maxlength="120" value="<?php echo htmlspecialchars((string) ($tenant['birthCity'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
                <label>Pays de naissance <input type="text" name="birth_country" maxlength="120" value="<?php echo htmlspecialchars((string) ($tenant['birthCountry'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
                <label>Nationalité <input type="text" name="nationality" maxlength="120" value="<?php echo htmlspecialchars((string) ($tenant['nationality'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
                <label>Profession <input type="text" name="occupation" maxlength="160" value="<?php echo htmlspecialchars((string) ($tenant['occupation'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
                <label>Adresse postale <textarea name="postal_address" maxlength="500"><?php echo htmlspecialchars((string) ($tenant['postalAddress'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea></label>
              </fieldset>
              <label>Email <input type="email" name="email" maxlength="190" value="<?php echo htmlspecialchars((string) ($tenant['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
              <label>Telephone <input type="text" name="phone" maxlength="64" value="<?php echo htmlspecialchars((string) ($tenant['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
              <label>Statut
                <select name="status">
                  <?php foreach ($statuses as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $tenantStatus === $value ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Notes <textarea name="notes" maxlength="2000"><?php echo htmlspecialchars((string) ($tenant['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea></label>
              <button type="submit">Mettre à jour</button>
            </form>
            <form method="post" action="<?php echo htmlspecialchars((string) ($urls['tenants'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="delete_tenant" />
              <input type="hidden" name="tenant_id" value="<?php echo htmlspecialchars((string) $tenantId, ENT_QUOTES, 'UTF-8'); ?>" />
              <button type="submit" class="private-button-danger">Supprimer</button>
            </form>
          </div>
        </dialog>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <dialog class="private-dialog" id="<?php echo htmlspecialchars($createDialogId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="<?php echo htmlspecialchars($createDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">
    <div class="private-dialog-panel">
      <header class="private-dialog-header">
        <h3 id="<?php echo htmlspecialchars($createDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">Créer un locataire</h3>
        <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
      </header>
      <?php if ($unitLabels === []): ?>
        <p class="muted">Créer d'abord une propriété, puis un bien locatif avant d'ajouter un locataire.</p>
      <?php else: ?>
        <form method="post" action="<?php echo htmlspecialchars((string) ($urls['tenants'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
          <input type="hidden" name="action" value="create_tenant" />
          <input type="hidden" name="full_name" value="" />
          <label>Bien locatif
            <select name="rental_unit_id" required>
              <option value="">Choisir un bien locatif</option>
              <?php foreach ($unitLabels as $id => $label): ?>
                <option value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <fieldset class="private-fieldset">
            <legend>Identité</legend>
            <label>Nom <input type="text" name="last_name" maxlength="120" required /></label>
            <label>Prénoms <input type="text" name="first_names" maxlength="160" /></label>
            <label>Date de naissance <input type="date" name="birth_date" /></label>
            <label>Ville de naissance <input type="text" name="birth_city" maxlength="120" /></label>
            <label>Pays de naissance <input type="text" name="birth_country" maxlength="120" /></label>
            <label>Nationalité <input type="text" name="nationality" maxlength="120" /></label>
            <label>Profession <input type="text" name="occupation" maxlength="160" /></label>
            <label>Adresse postale <textarea name="postal_address" maxlength="500"></textarea></label>
          </fieldset>
          <label>Email <input type="email" name="email" maxlength="190" /></label>
          <label>Telephone <input type="text" name="phone" maxlength="64" /></label>
          <label>Statut
            <select name="status">
              <?php foreach ($statuses as $value => $label): ?>
                <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Notes <textarea name="notes" maxlength="2000"></textarea></label>
          <button type="submit">Créer le locataire</button>
        </form>
      <?php endif; ?>
    </div>
  </dialog>
</section>
