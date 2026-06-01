<?php
$properties = is_array($viewModel['rentalProperties'] ?? null) ? $viewModel['rentalProperties'] : [];
$units = is_array($viewModel['rentalUnits'] ?? null) ? $viewModel['rentalUnits'] : [];
$tenants = is_array($viewModel['rentalTenants'] ?? null) ? $viewModel['rentalTenants'] : [];
$leases = is_array($viewModel['rentalLeases'] ?? null) ? $viewModel['rentalLeases'] : [];
$leaseTypes = is_array($viewModel['rentalLeaseTypes'] ?? null) ? $viewModel['rentalLeaseTypes'] : [];
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
$tenantOptions = [];
foreach ($tenants as $tenant) {
    if (!is_array($tenant) || !is_numeric($tenant['id'] ?? null) || !is_numeric($tenant['rentalUnitId'] ?? null)) {
        continue;
    }

    $unitId = (int) $tenant['rentalUnitId'];
    if ($unitId <= 0) {
        continue;
    }

    $tenantOptions[] = [
        'id' => (int) $tenant['id'],
        'unitId' => $unitId,
        'name' => (string) ($tenant['fullName'] ?? ''),
    ];
}
$leaseStatuses = ['draft' => 'Brouillon', 'validated' => 'Validé', 'ended' => 'Terminé', 'cancelled' => 'Annulé'];
$activeLeaseUnitIds = [];
foreach ($leases as $lease) {
    if (!is_array($lease) || !is_numeric($lease['rentalUnitId'] ?? null)) {
        continue;
    }

    if (in_array((string) ($lease['status'] ?? ''), ['draft', 'validated'], true)) {
        $activeLeaseUnitIds[(int) $lease['rentalUnitId']] = true;
    }
}
$leaseCreateUnits = [];
$leaseCreateUnitIds = [];
foreach ($units as $unit) {
    if (!is_array($unit) || !is_numeric($unit['id'] ?? null)) {
        continue;
    }

    $unitId = (int) $unit['id'];
    $unitStatus = is_string($unit['status'] ?? null) ? (string) $unit['status'] : '';
    if ($unitStatus === 'available' && !isset($activeLeaseUnitIds[$unitId])) {
        $leaseCreateUnits[] = $unit;
        $leaseCreateUnitIds[$unitId] = true;
    }
}
$leaseCreateTenantOptions = [];
foreach ($tenantOptions as $tenant) {
    $tenantUnitId = is_numeric($tenant['unitId'] ?? null) ? (int) $tenant['unitId'] : 0;
    if (isset($leaseCreateUnitIds[$tenantUnitId])) {
        $leaseCreateTenantOptions[] = $tenant;
    }
}
$canCreateLease = $propertyNames !== [] && $leaseCreateUnits !== [] && $leaseCreateTenantOptions !== [];
$leaseTypeLabels = [];
$leaseTypeTaxLabels = [];
if ($leaseTypes === []) {
    $leaseTypes = [[
        'code' => 'residential_unfurnished',
        'label' => 'Habitation vide',
        'taxLabel' => 'Revenus fonciers',
        'durationMonths' => 36,
        'description' => 'Fin proposee a 3 ans.',
    ]];
}
foreach ($leaseTypes as $leaseType) {
    if (!is_array($leaseType) || !is_string($leaseType['code'] ?? null)) {
        continue;
    }

    $leaseTypeLabels[(string) $leaseType['code']] = (string) ($leaseType['label'] ?? $leaseType['code']);
    $leaseTypeTaxLabels[(string) $leaseType['code']] = (string) ($leaseType['taxLabel'] ?? '');
}
$createDialogId = 'rental-lease-create-dialog';
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>

  <?php if ($notice !== ''): ?><p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <section class="card private-list-section" data-private-filter-scope>
    <div class="private-list-header">
      <div>
        <h2>Baux</h2>
        <p class="muted">Contrats classés par propriété, bien locatif, locataire, type et statut.</p>
      </div>
      <button type="button" class="private-create-button" data-private-dialog-open="<?php echo htmlspecialchars($createDialogId, ENT_QUOTES, 'UTF-8'); ?>"<?php echo !$canCreateLease ? ' disabled' : ''; ?>>Créer un bail</button>
    </div>
    <?php if (!$canCreateLease): ?>
      <p class="muted">Un bail exige une propriété, un bien locatif disponible sans bail actif et un locataire rattaché au bien locatif.</p>
    <?php endif; ?>
    <?php if ($leases === []): ?>
      <p class="muted">Aucun bail enregistre.</p>
    <?php else: ?>
      <div class="private-list-tools">
        <div class="private-list-filter-grid">
          <label>Recherche <input type="search" placeholder="Propriété, bien locatif, locataire" data-private-filter="text" /></label>
          <label>Statut
            <select data-private-filter="status">
              <option value="all">Tous</option>
              <?php foreach ($leaseStatuses as $value => $label): ?>
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
        <thead><tr><th>Propriété</th><th>Bien locatif</th><th>Locataire</th><th>Type</th><th>Période</th><th>Loyer</th><th>Charges</th><th>Statut</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($leases as $lease): ?>
            <?php if (!is_array($lease)) { continue; } ?>
            <?php $leaseId = is_numeric($lease['id'] ?? null) ? (int) $lease['id'] : 0; ?>
            <?php $leaseType = is_string($lease['leaseType'] ?? null) ? (string) $lease['leaseType'] : ''; ?>
            <?php $dialogId = 'rental-lease-dialog-' . $leaseId; ?>
            <tr data-private-filter-row data-filter-text="<?php echo htmlspecialchars(strtolower(trim((string) ($lease['propertyName'] ?? '') . ' ' . (string) ($lease['unitLabel'] ?? '') . ' ' . (string) ($lease['tenantName'] ?? '') . ' ' . (string) ($leaseTypeLabels[$leaseType] ?? $leaseType))), ENT_QUOTES, 'UTF-8'); ?>" data-filter-status="<?php echo htmlspecialchars((string) ($lease['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
              <td><?php echo htmlspecialchars((string) ($lease['propertyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($lease['unitLabel'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($lease['tenantName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php echo htmlspecialchars((string) ($leaseTypeLabels[$leaseType] ?? $leaseType), ENT_QUOTES, 'UTF-8'); ?>
                <?php if (($leaseTypeTaxLabels[$leaseType] ?? '') !== ''): ?>
                  <br /><small><?php echo htmlspecialchars((string) $leaseTypeTaxLabels[$leaseType], ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
              </td>
              <td><?php echo htmlspecialchars((string) ($lease['startDate'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> → <?php echo htmlspecialchars((string) ($lease['endDate'] ?? 'en cours'), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(number_format((float) ($lease['monthlyRent'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</td>
              <td><?php echo htmlspecialchars(number_format((float) ($lease['chargesProvision'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</td>
              <td><?php echo htmlspecialchars((string) ($leaseStatuses[(string) ($lease['status'] ?? '')] ?? ($lease['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php if ($leaseId > 0): ?>
                  <button type="button" class="private-row-action" data-private-dialog-open="<?php echo htmlspecialchars($dialogId, ENT_QUOTES, 'UTF-8'); ?>">Modifier</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <tr class="private-empty-row" data-private-filter-empty hidden><td colspan="9">Aucun bail ne correspond aux filtres.</td></tr>
        </tbody>
      </table>
      </div>

      <?php foreach ($leases as $lease): ?>
        <?php
        if (!is_array($lease)) {
            continue;
        }
        $leaseId = is_numeric($lease['id'] ?? null) ? (int) $lease['id'] : 0;
        if ($leaseId <= 0) {
            continue;
        }
        $dialogId = 'rental-lease-dialog-' . $leaseId;
        $selectedPropertyId = is_numeric($lease['rentalPropertyId'] ?? null) ? (int) $lease['rentalPropertyId'] : 0;
        $selectedUnitId = is_numeric($lease['rentalUnitId'] ?? null) ? (int) $lease['rentalUnitId'] : 0;
        $selectedTenantId = is_numeric($lease['rentalTenantId'] ?? null) ? (int) $lease['rentalTenantId'] : 0;
        $selectedLeaseType = is_string($lease['leaseType'] ?? null) ? (string) $lease['leaseType'] : '';
        $selectedStatus = is_string($lease['status'] ?? null) ? (string) $lease['status'] : 'draft';
        $monthlyRent = number_format((float) ($lease['monthlyRent'] ?? 0), 2, '.', '');
        $chargesProvision = number_format((float) ($lease['chargesProvision'] ?? 0), 2, '.', '');
        ?>
        <dialog class="private-dialog" id="<?php echo htmlspecialchars($dialogId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="<?php echo htmlspecialchars($dialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">
          <div class="private-dialog-panel">
            <header class="private-dialog-header">
              <h3 id="<?php echo htmlspecialchars($dialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">Modifier le bail</h3>
              <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
            </header>
            <form method="post" action="<?php echo htmlspecialchars((string) ($urls['leases'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-rental-lease-form>
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="update_lease" />
              <input type="hidden" name="lease_id" value="<?php echo htmlspecialchars((string) $leaseId, ENT_QUOTES, 'UTF-8'); ?>" />
              <label>Propriété
                <select name="rental_property_id" required>
                  <?php foreach ($propertyNames as $id => $name): ?>
                    <option value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedPropertyId === $id ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Bien locatif
                <select name="rental_unit_id" required data-rental-unit-select>
                  <?php foreach ($units as $unit): ?>
                    <?php if (!is_array($unit) || !is_numeric($unit['id'] ?? null)) { continue; } ?>
                    <?php $unitId = (int) $unit['id']; ?>
                    <?php $unitPropertyId = is_numeric($unit['rentalPropertyId'] ?? null) ? (int) $unit['rentalPropertyId'] : 0; ?>
                    <option value="<?php echo htmlspecialchars((string) $unitId, ENT_QUOTES, 'UTF-8'); ?>" data-property-id="<?php echo htmlspecialchars((string) $unitPropertyId, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedUnitId === $unitId ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars((string) ($unit['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Locataire
                <select name="rental_tenant_id" required data-rental-tenant-select>
                  <option value="">Choisir un locataire</option>
                  <?php foreach ($tenantOptions as $tenant): ?>
                    <option value="<?php echo htmlspecialchars((string) $tenant['id'], ENT_QUOTES, 'UTF-8'); ?>" data-unit-id="<?php echo htmlspecialchars((string) $tenant['unitId'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedTenantId === (int) $tenant['id'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($tenant['name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <p class="notice notice-error" hidden data-rental-tenant-empty>Il faut créer un locataire pour ce bien locatif avant de modifier ce bail.</p>
              <label>Type de bail
                <select name="lease_type" required data-rental-lease-type-select>
                  <?php foreach ($leaseTypes as $leaseTypeOption): ?>
                    <?php
                    if (!is_array($leaseTypeOption) || !is_string($leaseTypeOption['code'] ?? null)) {
                        continue;
                    }
                    $durationMonths = is_numeric($leaseTypeOption['durationMonths'] ?? null) ? (int) $leaseTypeOption['durationMonths'] : '';
                    $leaseTypeCode = (string) $leaseTypeOption['code'];
                    ?>
                    <option
                      value="<?php echo htmlspecialchars($leaseTypeCode, ENT_QUOTES, 'UTF-8'); ?>"
                      data-duration-months="<?php echo htmlspecialchars((string) $durationMonths, ENT_QUOTES, 'UTF-8'); ?>"
                      data-tax-label="<?php echo htmlspecialchars((string) ($leaseTypeOption['taxLabel'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                      data-description="<?php echo htmlspecialchars((string) ($leaseTypeOption['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                      <?php echo $selectedLeaseType === $leaseTypeCode ? 'selected' : ''; ?>
                    >
                      <?php echo htmlspecialchars((string) ($leaseTypeOption['label'] ?? $leaseTypeCode), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <p class="muted" data-rental-lease-type-help></p>
              <label>Début <input type="date" name="start_date" value="<?php echo htmlspecialchars((string) ($lease['startDate'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required data-rental-lease-start-date /></label>
              <label>Fin <input type="date" name="end_date" value="<?php echo htmlspecialchars((string) ($lease['endDate'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-rental-lease-end-date data-rental-lease-end-auto="0" /></label>
              <label>Loyer mensuel <input type="number" name="monthly_rent" min="0.01" step="0.01" value="<?php echo htmlspecialchars($monthlyRent, ENT_QUOTES, 'UTF-8'); ?>" required /></label>
              <label>Provision charges <input type="number" name="charges_provision" min="0" step="0.01" value="<?php echo htmlspecialchars($chargesProvision, ENT_QUOTES, 'UTF-8'); ?>" /></label>
              <label>Statut
                <select name="status">
                  <?php foreach ($leaseStatuses as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedStatus === $value ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Notes <textarea name="notes" maxlength="2000"><?php echo htmlspecialchars((string) ($lease['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea></label>
              <button type="submit" data-rental-lease-submit>Mettre à jour</button>
            </form>
            <form method="post" action="<?php echo htmlspecialchars((string) ($urls['leases'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="download_lease" />
              <input type="hidden" name="lease_id" value="<?php echo htmlspecialchars((string) $leaseId, ENT_QUOTES, 'UTF-8'); ?>" />
              <button type="submit" class="private-button-secondary">Éditer le bail selon le type choisi</button>
            </form>
            <form method="post" action="<?php echo htmlspecialchars((string) ($urls['leases'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="adjust_lease" />
              <input type="hidden" name="lease_id" value="<?php echo htmlspecialchars((string) $leaseId, ENT_QUOTES, 'UTF-8'); ?>" />
              <fieldset class="private-fieldset">
                <legend>Réajustement annuel</legend>
                <label>Mois d'effet <input type="month" name="adjustment_month" value="<?php echo htmlspecialchars(date('Y-m'), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
                <label>Nouveau loyer mensuel <input type="number" name="adjusted_monthly_rent" min="0.01" step="0.01" value="<?php echo htmlspecialchars($monthlyRent, ENT_QUOTES, 'UTF-8'); ?>" required /></label>
                <label>Nouvelle provision charges <input type="number" name="adjusted_charges_provision" min="0" step="0.01" value="<?php echo htmlspecialchars($chargesProvision, ENT_QUOTES, 'UTF-8'); ?>" /></label>
                <label>Note d'ajustement <textarea name="adjustment_note" maxlength="500" placeholder="Indice IRL, regularisation de charges, accord locataire..."></textarea></label>
                <button type="submit">Appliquer le réajustement</button>
              </fieldset>
            </form>
            <form method="post" action="<?php echo htmlspecialchars((string) ($urls['leases'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="delete_lease" />
              <input type="hidden" name="lease_id" value="<?php echo htmlspecialchars((string) $leaseId, ENT_QUOTES, 'UTF-8'); ?>" />
              <button type="submit" class="private-button-danger">Supprimer le bail</button>
            </form>
          </div>
        </dialog>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <dialog class="private-dialog" id="<?php echo htmlspecialchars($createDialogId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="<?php echo htmlspecialchars($createDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">
    <div class="private-dialog-panel">
      <header class="private-dialog-header">
        <h3 id="<?php echo htmlspecialchars($createDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">Créer un bail</h3>
        <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
      </header>
      <?php if (!$canCreateLease): ?>
        <p class="muted">Un bail exige une propriété, un bien locatif disponible sans bail actif et un locataire rattaché au bien locatif.</p>
      <?php else: ?>
        <form method="post" action="<?php echo htmlspecialchars((string) ($urls['leases'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-rental-lease-form>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
          <input type="hidden" name="action" value="create_lease" />
          <label>Propriété
            <select name="rental_property_id" required>
              <?php foreach ($propertyNames as $id => $name): ?>
                <option value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Bien locatif
            <select name="rental_unit_id" required data-rental-unit-select>
              <?php foreach ($leaseCreateUnits as $unit): ?>
                <?php if (!is_array($unit) || !is_numeric($unit['id'] ?? null)) { continue; } ?>
                <?php $unitPropertyId = is_numeric($unit['rentalPropertyId'] ?? null) ? (int) $unit['rentalPropertyId'] : 0; ?>
                <option value="<?php echo htmlspecialchars((string) (int) $unit['id'], ENT_QUOTES, 'UTF-8'); ?>" data-property-id="<?php echo htmlspecialchars((string) $unitPropertyId, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($unit['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Locataire
            <select name="rental_tenant_id" required data-rental-tenant-select>
              <option value="">Choisir un locataire</option>
              <?php foreach ($leaseCreateTenantOptions as $tenant): ?>
                <option value="<?php echo htmlspecialchars((string) $tenant['id'], ENT_QUOTES, 'UTF-8'); ?>" data-unit-id="<?php echo htmlspecialchars((string) $tenant['unitId'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($tenant['name'], ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <p class="notice notice-error" hidden data-rental-tenant-empty>Il faut créer un locataire pour ce bien locatif avant de créer un bail.</p>
          <label>Type de bail
            <select name="lease_type" required data-rental-lease-type-select>
              <?php foreach ($leaseTypes as $leaseType): ?>
                <?php
                if (!is_array($leaseType) || !is_string($leaseType['code'] ?? null)) {
                    continue;
                }
                $durationMonths = is_numeric($leaseType['durationMonths'] ?? null) ? (int) $leaseType['durationMonths'] : '';
                ?>
                <option
                  value="<?php echo htmlspecialchars((string) $leaseType['code'], ENT_QUOTES, 'UTF-8'); ?>"
                  data-duration-months="<?php echo htmlspecialchars((string) $durationMonths, ENT_QUOTES, 'UTF-8'); ?>"
                  data-tax-label="<?php echo htmlspecialchars((string) ($leaseType['taxLabel'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                  data-description="<?php echo htmlspecialchars((string) ($leaseType['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                >
                  <?php echo htmlspecialchars((string) ($leaseType['label'] ?? $leaseType['code']), ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <p class="muted" data-rental-lease-type-help></p>
          <label>Début <input type="date" name="start_date" required data-rental-lease-start-date /></label>
          <label>Fin <input type="date" name="end_date" data-rental-lease-end-date /></label>
          <label>Loyer mensuel <input type="number" name="monthly_rent" min="0.01" step="0.01" required /></label>
          <label>Provision charges <input type="number" name="charges_provision" min="0" step="0.01" value="0" /></label>
          <label>Statut
            <select name="status">
              <?php foreach ($leaseStatuses as $value => $label): ?>
                <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Notes <textarea name="notes" maxlength="2000"></textarea></label>
          <button type="submit" data-rental-lease-submit>Créer le bail</button>
        </form>
      <?php endif; ?>
    </div>
  </dialog>
  <script>
    (() => {
      document.querySelectorAll('[data-rental-lease-form]').forEach((form) => {
        const propertySelect = form.querySelector('[name="rental_property_id"]');
        const unitSelect = form.querySelector('[data-rental-unit-select]');
        const tenantSelect = form.querySelector('[data-rental-tenant-select]');
        const emptyMessage = form.querySelector('[data-rental-tenant-empty]');
        const submitButton = form.querySelector('[data-rental-lease-submit]');
        const leaseTypeSelect = form.querySelector('[data-rental-lease-type-select]');
        const startDateInput = form.querySelector('[data-rental-lease-start-date]');
        const endDateInput = form.querySelector('[data-rental-lease-end-date]');
        const leaseTypeHelp = form.querySelector('[data-rental-lease-type-help]');
        if (!(unitSelect instanceof HTMLSelectElement) || !(tenantSelect instanceof HTMLSelectElement)) {
          return;
        }

        const unitOptions = Array.from(unitSelect.options);
        const tenantOptions = Array.from(tenantSelect.options);
        const refreshTenants = () => {
          const selectedUnitId = unitSelect.value;
          let firstMatchingValue = '';
          let matchingCount = 0;

          tenantOptions.forEach((option) => {
            const isPlaceholder = option.value === '';
            const matches = !isPlaceholder && option.dataset.unitId === selectedUnitId;
            option.hidden = !isPlaceholder && !matches;
            option.disabled = isPlaceholder || !matches;

            if (matches) {
              matchingCount += 1;
              if (firstMatchingValue === '') {
                firstMatchingValue = option.value;
              }
            }
          });

          if (matchingCount === 0) {
            tenantSelect.value = '';
            tenantSelect.disabled = true;
            if (emptyMessage instanceof HTMLElement) {
              emptyMessage.hidden = false;
            }
            if (submitButton instanceof HTMLButtonElement) {
              submitButton.disabled = true;
            }
            return;
          }

          const selectedOption = tenantSelect.selectedOptions[0] ?? null;
          if (!(selectedOption instanceof HTMLOptionElement) || selectedOption.dataset.unitId !== selectedUnitId) {
            tenantSelect.value = firstMatchingValue;
          }
          tenantSelect.disabled = false;
          if (emptyMessage instanceof HTMLElement) {
            emptyMessage.hidden = true;
          }
          if (submitButton instanceof HTMLButtonElement) {
            submitButton.disabled = false;
          }
        };

        const refreshUnits = () => {
          if (!(propertySelect instanceof HTMLSelectElement)) {
            refreshTenants();
            return;
          }

          const selectedPropertyId = propertySelect.value;
          let firstMatchingValue = '';
          let selectedStillVisible = false;

          unitOptions.forEach((option) => {
            const matches = selectedPropertyId === '' || option.dataset.propertyId === selectedPropertyId;
            option.hidden = !matches;
            option.disabled = !matches;
            if (matches && firstMatchingValue === '') {
              firstMatchingValue = option.value;
            }
            if (option.selected && matches) {
              selectedStillVisible = true;
            }
          });

          if (!selectedStillVisible) {
            unitSelect.value = firstMatchingValue;
          }

          refreshTenants();
        };

        const formatDate = (date) => {
          const year = String(date.getUTCFullYear()).padStart(4, '0');
          const month = String(date.getUTCMonth() + 1).padStart(2, '0');
          const day = String(date.getUTCDate()).padStart(2, '0');

          return `${year}-${month}-${day}`;
        };

        const defaultEndDate = (startDate, durationMonths) => {
          const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(startDate);
          if (!match || !Number.isFinite(durationMonths) || durationMonths <= 0) {
            return '';
          }

          const year = Number(match[1]);
          const month = Number(match[2]);
          const day = Number(match[3]);
          const targetMonthIndex = month - 1 + durationMonths;
          const targetYear = year + Math.floor(targetMonthIndex / 12);
          const targetMonth = ((targetMonthIndex % 12) + 12) % 12;
          const lastTargetDay = new Date(Date.UTC(targetYear, targetMonth + 1, 0)).getUTCDate();
          const targetDay = Math.min(day, lastTargetDay);
          const date = new Date(Date.UTC(targetYear, targetMonth, targetDay));
          date.setUTCDate(date.getUTCDate() - 1);

          return formatDate(date);
        };

        const refreshLeaseType = () => {
          if (!(leaseTypeSelect instanceof HTMLSelectElement)) {
            return;
          }

          const selectedOption = leaseTypeSelect.selectedOptions[0] ?? null;
          if (!(selectedOption instanceof HTMLOptionElement)) {
            return;
          }

          const taxLabel = selectedOption.dataset.taxLabel ?? '';
          const description = selectedOption.dataset.description ?? '';
          if (leaseTypeHelp instanceof HTMLElement) {
            leaseTypeHelp.textContent = [taxLabel !== '' ? `Imposition indicative : ${taxLabel}.` : '', description]
              .filter((item) => item !== '')
              .join(' ');
          }

          if (!(startDateInput instanceof HTMLInputElement) || !(endDateInput instanceof HTMLInputElement)) {
            return;
          }

          if (endDateInput.value !== '' && endDateInput.dataset.rentalLeaseEndAuto !== '1') {
            return;
          }

          const durationMonths = Number(selectedOption.dataset.durationMonths ?? '0');
          const endDate = defaultEndDate(startDateInput.value, durationMonths);
          if (endDate !== '') {
            endDateInput.value = endDate;
            endDateInput.dataset.rentalLeaseEndAuto = '1';
          }
        };

        if (propertySelect instanceof HTMLSelectElement) {
          propertySelect.addEventListener('change', refreshUnits);
        }
        unitSelect.addEventListener('change', refreshTenants);
        if (leaseTypeSelect instanceof HTMLSelectElement) {
          leaseTypeSelect.addEventListener('change', refreshLeaseType);
        }
        if (startDateInput instanceof HTMLInputElement) {
          startDateInput.addEventListener('change', refreshLeaseType);
        }
        if (endDateInput instanceof HTMLInputElement) {
          endDateInput.addEventListener('input', () => {
            endDateInput.dataset.rentalLeaseEndAuto = '0';
          });
        }
        refreshUnits();
        refreshLeaseType();
      });
    })();
  </script>
</section>
