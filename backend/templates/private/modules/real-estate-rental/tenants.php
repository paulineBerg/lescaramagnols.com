<?php
$properties = is_array($viewModel['rentalProperties'] ?? null) ? $viewModel['rentalProperties'] : [];
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
$statuses = ['draft' => 'Brouillon', 'validated' => 'Valide', 'cancelled' => 'Annule'];
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>

  <?php if ($notice !== ''): ?><p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <section class="card">
    <h2>Ajouter un locataire</h2>
    <?php if ($propertyNames === []): ?>
      <p class="muted">Créer d'abord un bien locatif autorise.</p>
    <?php else: ?>
      <form method="post" action="<?php echo htmlspecialchars((string) ($urls['tenants'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="action" value="create_tenant" />
        <label>Bien
          <select name="rental_property_id" required>
            <?php foreach ($propertyNames as $id => $name): ?>
              <option value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></option>
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
        <thead><tr><th>Bien</th><th>Locataire</th><th>Email</th><th>Statut</th></tr></thead>
        <tbody>
          <?php foreach ($tenants as $tenant): ?>
            <?php if (!is_array($tenant)) { continue; } ?>
            <tr>
              <td><?php echo htmlspecialchars((string) ($tenant['propertyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($tenant['fullName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($tenant['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($statuses[(string) ($tenant['status'] ?? '')] ?? ($tenant['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</section>
