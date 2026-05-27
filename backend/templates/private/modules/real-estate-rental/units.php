<?php
$properties = is_array($viewModel['rentalProperties'] ?? null) ? $viewModel['rentalProperties'] : [];
$units = is_array($viewModel['rentalUnits'] ?? null) ? $viewModel['rentalUnits'] : [];
$csrfToken = is_string($viewModel['rentalCsrfToken'] ?? null) ? (string) $viewModel['rentalCsrfToken'] : '';
$notice = is_string($viewModel['rentalNotice'] ?? null) ? (string) $viewModel['rentalNotice'] : '';
$error = is_string($viewModel['rentalError'] ?? null) ? (string) $viewModel['rentalError'] : '';
$urls = is_array($viewModel['rentalUrls'] ?? null) ? $viewModel['rentalUrls'] : [];
$unitsUrl = is_string($urls['units'] ?? null) ? (string) $urls['units'] : private_portal_url('rental_units');
$unitStatuses = ['available' => 'Disponible', 'occupied' => 'Occupé', 'maintenance' => 'Maintenance', 'archived' => 'Archivé'];
$propertyNames = [];
foreach ($properties as $property) {
    if (is_array($property) && is_numeric($property['id'] ?? null)) {
        $propertyNames[(int) $property['id']] = (string) ($property['name'] ?? ('Bien #' . (int) $property['id']));
    }
}
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>

  <?php if ($notice !== ''): ?>
    <p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <section class="card">
    <h2>Ajouter un lot</h2>
    <?php if ($properties === []): ?>
      <p class="muted">Créer d’abord un bien locatif autorisé.</p>
    <?php else: ?>
      <form method="post" action="<?php echo htmlspecialchars($unitsUrl, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="action" value="create_unit" />
        <label>Bien
          <select name="rental_property_id" required>
            <?php foreach ($propertyNames as $id => $name): ?>
              <option value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Libellé <input type="text" name="label" maxlength="160" required /></label>
        <label>Surface <input type="number" name="surface" min="0.5" max="10000" step="0.01" required /></label>
        <label><input type="checkbox" name="furnished" value="1" /> Meublé</label>
        <label>Statut
          <select name="status">
            <option value="available">Disponible</option>
            <option value="occupied">Occupé</option>
            <option value="maintenance">Maintenance</option>
          </select>
        </label>
        <label>Notes <textarea name="notes" maxlength="2000"></textarea></label>
        <button type="submit">Créer le lot</button>
      </form>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2>Lots autorisés</h2>
    <?php if ($units === []): ?>
      <p class="muted">Aucun lot locatif actif.</p>
    <?php else: ?>
      <?php foreach ($units as $unit): ?>
        <?php
        if (!is_array($unit)) {
            continue;
        }
        $id = is_numeric($unit['id'] ?? null) ? (int) $unit['id'] : 0;
        $propertyId = is_numeric($unit['rentalPropertyId'] ?? null) ? (int) $unit['rentalPropertyId'] : 0;
        if ($id <= 0 || $propertyId <= 0) {
            continue;
        }
        $status = is_string($unit['status'] ?? null) ? (string) $unit['status'] : 'available';
        ?>
        <article class="card">
          <form method="post" action="<?php echo htmlspecialchars($unitsUrl, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
            <input type="hidden" name="action" value="update_unit" />
            <input type="hidden" name="unit_id" value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>" />
            <label>Bien
              <select name="rental_property_id" required>
                <?php foreach ($propertyNames as $optionId => $name): ?>
                  <option value="<?php echo htmlspecialchars((string) $optionId, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $propertyId === $optionId ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Libellé <input type="text" name="label" maxlength="160" value="<?php echo htmlspecialchars((string) ($unit['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
            <label>Surface <input type="number" name="surface" min="0.5" max="10000" step="0.01" value="<?php echo htmlspecialchars((string) ($unit['surface'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
            <label><input type="checkbox" name="furnished" value="1" <?php echo !empty($unit['furnished']) ? 'checked' : ''; ?> /> Meublé</label>
            <label>Statut
              <select name="status">
                <?php foreach ($unitStatuses as $value => $label): ?>
                  <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $status === $value ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Notes <textarea name="notes" maxlength="2000"><?php echo htmlspecialchars((string) ($unit['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea></label>
            <button type="submit">Mettre à jour</button>
          </form>
          <form method="post" action="<?php echo htmlspecialchars(rtrim($unitsUrl, '/') . '/' . $id . '/archive', ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
            <button type="submit">Archiver</button>
          </form>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</section>
