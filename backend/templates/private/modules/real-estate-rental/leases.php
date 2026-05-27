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
$leaseStatuses = ['draft' => 'Brouillon', 'validated' => 'Valide', 'ended' => 'Termine', 'cancelled' => 'Annule'];
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>

  <?php if ($notice !== ''): ?><p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <section class="card">
    <h2>Ajouter un bail</h2>
    <?php if ($propertyNames === [] || $units === [] || $tenants === []): ?>
      <p class="muted">Un bail exige un bien, un lot et un locataire.</p>
    <?php else: ?>
      <form method="post" action="<?php echo htmlspecialchars((string) ($urls['leases'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="action" value="create_lease" />
        <label>Bien
          <select name="rental_property_id" required>
            <?php foreach ($propertyNames as $id => $name): ?>
              <option value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Lot
          <select name="rental_unit_id" required>
            <?php foreach ($units as $unit): ?>
              <?php if (!is_array($unit) || !is_numeric($unit['id'] ?? null)) { continue; } ?>
              <option value="<?php echo htmlspecialchars((string) (int) $unit['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($unit['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Locataire
          <select name="rental_tenant_id" required>
            <?php foreach ($tenants as $tenant): ?>
              <?php if (!is_array($tenant) || !is_numeric($tenant['id'] ?? null)) { continue; } ?>
              <option value="<?php echo htmlspecialchars((string) (int) $tenant['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($tenant['fullName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
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
        <button type="submit">Créer le bail</button>
      </form>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2>Baux</h2>
    <?php if ($leases === []): ?>
      <p class="muted">Aucun bail enregistre.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Bien</th><th>Lot</th><th>Locataire</th><th>Periode</th><th>Loyer</th><th>Statut</th></tr></thead>
        <tbody>
          <?php foreach ($leases as $lease): ?>
            <?php if (!is_array($lease)) { continue; } ?>
            <tr>
              <td><?php echo htmlspecialchars((string) ($lease['propertyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($lease['unitLabel'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($lease['tenantName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($lease['startDate'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> → <?php echo htmlspecialchars((string) ($lease['endDate'] ?? 'en cours'), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(number_format((float) ($lease['monthlyRent'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</td>
              <td><?php echo htmlspecialchars((string) ($leaseStatuses[(string) ($lease['status'] ?? '')] ?? ($lease['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</section>
