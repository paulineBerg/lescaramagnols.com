<?php
$properties = is_array($viewModel['rentalProperties'] ?? null) ? $viewModel['rentalProperties'] : [];
$csrfToken = is_string($viewModel['rentalCsrfToken'] ?? null) ? (string) $viewModel['rentalCsrfToken'] : '';
$notice = is_string($viewModel['rentalNotice'] ?? null) ? (string) $viewModel['rentalNotice'] : '';
$error = is_string($viewModel['rentalError'] ?? null) ? (string) $viewModel['rentalError'] : '';
$urls = is_array($viewModel['rentalUrls'] ?? null) ? $viewModel['rentalUrls'] : [];
$propertiesUrl = is_string($urls['properties'] ?? null) ? (string) $urls['properties'] : private_portal_url('rental_properties');
$statuses = ['draft' => 'Brouillon', 'active' => 'Actif', 'archived' => 'Archivé'];
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
    <h2>Ajouter un bien</h2>
    <form method="post" action="<?php echo htmlspecialchars($propertiesUrl, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
      <input type="hidden" name="action" value="create_property" />
      <label>Nom <input type="text" name="name" maxlength="160" required /></label>
      <label>Adresse <input type="text" name="address" maxlength="255" required /></label>
      <label>Type <input type="text" name="property_type" maxlength="64" required /></label>
      <label>Mode de détention <input type="text" name="ownership_mode" maxlength="64" required /></label>
      <label>Statut
        <select name="status">
          <option value="draft">Brouillon</option>
          <option value="active">Actif</option>
        </select>
      </label>
      <label>Notes <textarea name="notes" maxlength="2000"></textarea></label>
      <button type="submit">Créer le bien</button>
    </form>
  </section>

  <section class="card">
    <h2>Biens autorisés</h2>
    <?php if ($properties === []): ?>
      <p class="muted">Aucun bien locatif autorisé pour ce compte.</p>
    <?php else: ?>
      <?php foreach ($properties as $property): ?>
        <?php
        if (!is_array($property)) {
            continue;
        }
        $id = is_numeric($property['id'] ?? null) ? (int) $property['id'] : 0;
        if ($id <= 0) {
            continue;
        }
        $status = is_string($property['status'] ?? null) ? (string) $property['status'] : 'draft';
        ?>
        <article class="card">
          <form method="post" action="<?php echo htmlspecialchars($propertiesUrl, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
            <input type="hidden" name="action" value="update_property" />
            <input type="hidden" name="property_id" value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>" />
            <label>Nom <input type="text" name="name" maxlength="160" value="<?php echo htmlspecialchars((string) ($property['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
            <label>Adresse <input type="text" name="address" maxlength="255" value="<?php echo htmlspecialchars((string) ($property['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
            <label>Type <input type="text" name="property_type" maxlength="64" value="<?php echo htmlspecialchars((string) ($property['propertyType'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
            <label>Mode de détention <input type="text" name="ownership_mode" maxlength="64" value="<?php echo htmlspecialchars((string) ($property['ownershipMode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
            <label>Statut
              <select name="status">
                <?php foreach ($statuses as $value => $label): ?>
                  <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $status === $value ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Notes <textarea name="notes" maxlength="2000"><?php echo htmlspecialchars((string) ($property['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea></label>
            <button type="submit">Mettre à jour</button>
          </form>
          <form method="post" action="<?php echo htmlspecialchars(rtrim($propertiesUrl, '/') . '/' . $id . '/archive', ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
            <button type="submit">Archiver</button>
          </form>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</section>
