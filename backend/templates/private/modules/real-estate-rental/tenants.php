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
        $propertyNames[(int) $property['id']] = (string) ($property['name'] ?? ('Bien #' . (int) $property['id']));
    }
}
$unitLabels = [];
foreach ($units as $unit) {
    if (!is_array($unit) || !is_numeric($unit['id'] ?? null) || !is_numeric($unit['rentalPropertyId'] ?? null)) {
        continue;
    }
    $propertyId = (int) $unit['rentalPropertyId'];
    $unitLabels[(int) $unit['id']] = trim(
        (string) ($propertyNames[$propertyId] ?? ('Immeuble #' . $propertyId))
        . ' - '
        . (string) ($unit['label'] ?? ('Lot #' . (int) $unit['id']))
    );
}
$statuses = ['draft' => 'Brouillon', 'validated' => 'Valide', 'cancelled' => 'Annule'];
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>

  <?php if ($notice !== ''): ?><p id="rental-feedback" class="notice notice-success private-feedback"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <section class="card">
    <h2>Ajouter un locataire</h2>
    <?php if ($unitLabels === []): ?>
      <p class="muted">Créer d'abord un immeuble, puis un lot locatif avant d'ajouter un locataire.</p>
    <?php else: ?>
      <form method="post" action="<?php echo htmlspecialchars((string) ($urls['tenants'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="action" value="create_tenant" />
        <label>Lot
          <select name="rental_unit_id" required>
            <option value="">Choisir un lot</option>
            <?php foreach ($unitLabels as $id => $label): ?>
              <option value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Nom complet <input type="text" name="full_name" maxlength="160" required /></label>
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
  </section>

  <section class="card">
    <h2>Locataires</h2>
    <?php if ($tenants === []): ?>
      <p class="muted">Aucun locataire enregistré.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Immeuble</th><th>Lot</th><th>Locataire</th><th>Email</th><th>Statut</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($tenants as $tenant): ?>
            <?php if (!is_array($tenant)) { continue; } ?>
            <?php
            $tenantId = is_numeric($tenant['id'] ?? null) ? (int) $tenant['id'] : 0;
            $dialogId = 'rental-tenant-dialog-' . $tenantId;
            ?>
            <tr>
              <td><?php echo htmlspecialchars((string) ($tenant['propertyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($tenant['unitLabel'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($tenant['fullName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($tenant['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($statuses[(string) ($tenant['status'] ?? '')] ?? ($tenant['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php if ($tenantId > 0): ?>
                  <button type="button" class="private-row-action" data-private-dialog-open="<?php echo htmlspecialchars($dialogId, ENT_QUOTES, 'UTF-8'); ?>">Modifier</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

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
              <label>Lot
                <select name="rental_unit_id" required>
                  <option value="">Choisir un lot</option>
                  <?php foreach ($unitLabels as $id => $label): ?>
                    <option value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedUnitId === $id ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Nom complet <input type="text" name="full_name" maxlength="160" value="<?php echo htmlspecialchars((string) ($tenant['fullName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
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
</section>
