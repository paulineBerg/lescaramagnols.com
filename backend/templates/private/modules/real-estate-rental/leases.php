<?php
$properties = is_array($viewModel['rentalProperties'] ?? null) ? $viewModel['rentalProperties'] : [];
$units = is_array($viewModel['rentalUnits'] ?? null) ? $viewModel['rentalUnits'] : [];
$tenants = is_array($viewModel['rentalTenants'] ?? null) ? $viewModel['rentalTenants'] : [];
$leases = is_array($viewModel['rentalLeases'] ?? null) ? $viewModel['rentalLeases'] : [];
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
$leaseStatuses = ['draft' => 'Brouillon', 'validated' => 'Valide', 'ended' => 'Termine', 'cancelled' => 'Annule'];
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>

  <?php if ($notice !== ''): ?><p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <section class="card">
    <h2>Ajouter un bail</h2>
    <?php if ($propertyNames === [] || $units === [] || $tenantOptions === []): ?>
      <p class="muted">Un bail exige un immeuble, un lot et un locataire rattaché au lot.</p>
    <?php else: ?>
      <form method="post" action="<?php echo htmlspecialchars((string) ($urls['leases'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-rental-lease-form>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="action" value="create_lease" />
        <label>Immeuble
          <select name="rental_property_id" required>
            <?php foreach ($propertyNames as $id => $name): ?>
              <option value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Lot
          <select name="rental_unit_id" required data-rental-unit-select>
            <?php foreach ($units as $unit): ?>
              <?php if (!is_array($unit) || !is_numeric($unit['id'] ?? null)) { continue; } ?>
              <option value="<?php echo htmlspecialchars((string) (int) $unit['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($unit['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Locataire
          <select name="rental_tenant_id" required data-rental-tenant-select>
            <option value="">Choisir un locataire</option>
            <?php foreach ($tenantOptions as $tenant): ?>
              <option value="<?php echo htmlspecialchars((string) $tenant['id'], ENT_QUOTES, 'UTF-8'); ?>" data-unit-id="<?php echo htmlspecialchars((string) $tenant['unitId'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($tenant['name'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <p class="notice notice-error" hidden data-rental-tenant-empty>Il faut créer un locataire pour ce lot avant de créer un bail.</p>
        <label>Debut <input type="date" name="start_date" required /></label>
        <label>Fin <input type="date" name="end_date" /></label>
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
  </section>

  <section class="card">
    <h2>Baux</h2>
    <?php if ($leases === []): ?>
      <p class="muted">Aucun bail enregistre.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Bien</th><th>Lot</th><th>Locataire</th><th>Periode</th><th>Loyer</th><th>Statut</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($leases as $lease): ?>
            <?php if (!is_array($lease)) { continue; } ?>
            <?php $leaseId = is_numeric($lease['id'] ?? null) ? (int) $lease['id'] : 0; ?>
            <tr>
              <td><?php echo htmlspecialchars((string) ($lease['propertyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($lease['unitLabel'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($lease['tenantName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($lease['startDate'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> → <?php echo htmlspecialchars((string) ($lease['endDate'] ?? 'en cours'), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(number_format((float) ($lease['monthlyRent'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</td>
              <td><?php echo htmlspecialchars((string) ($leaseStatuses[(string) ($lease['status'] ?? '')] ?? ($lease['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php if ($leaseId > 0): ?>
                  <form method="post" action="<?php echo htmlspecialchars((string) ($urls['leases'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
                    <input type="hidden" name="action" value="delete_lease" />
                    <input type="hidden" name="lease_id" value="<?php echo htmlspecialchars((string) $leaseId, ENT_QUOTES, 'UTF-8'); ?>" />
                    <button class="button-small button-danger" type="submit">Supprimer</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
  <script>
    (() => {
      document.querySelectorAll('[data-rental-lease-form]').forEach((form) => {
        const unitSelect = form.querySelector('[data-rental-unit-select]');
        const tenantSelect = form.querySelector('[data-rental-tenant-select]');
        const emptyMessage = form.querySelector('[data-rental-tenant-empty]');
        const submitButton = form.querySelector('[data-rental-lease-submit]');
        if (!(unitSelect instanceof HTMLSelectElement) || !(tenantSelect instanceof HTMLSelectElement)) {
          return;
        }

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

        unitSelect.addEventListener('change', refreshTenants);
        refreshTenants();
      });
    })();
  </script>
</section>
