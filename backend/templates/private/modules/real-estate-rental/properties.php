<?php
$properties = is_array($viewModel['rentalProperties'] ?? null) ? $viewModel['rentalProperties'] : [];
$csrfToken = is_string($viewModel['rentalCsrfToken'] ?? null) ? (string) $viewModel['rentalCsrfToken'] : '';
$notice = is_string($viewModel['rentalNotice'] ?? null) ? (string) $viewModel['rentalNotice'] : '';
$error = is_string($viewModel['rentalError'] ?? null) ? (string) $viewModel['rentalError'] : '';
$urls = is_array($viewModel['rentalUrls'] ?? null) ? $viewModel['rentalUrls'] : [];
$propertiesUrl = is_string($urls['properties'] ?? null) ? (string) $urls['properties'] : private_portal_url('rental_properties');
$statuses = ['draft' => 'Brouillon', 'active' => 'Actif', 'archived' => 'Archivé'];
$propertyTypes = [
    'Appartement' => 'Appartement',
    'Maison' => 'Maison',
    'Immeuble' => 'Immeuble',
    'Garage / parking' => 'Garage / parking',
    'Local commercial' => 'Local commercial',
    'Terrain' => 'Terrain',
    'Autre' => 'Autre',
];
$ownershipModes = [
    'Pleine propriété' => 'Pleine propriété',
    'Indivision' => 'Indivision',
    'SCI' => 'SCI',
    'Usufruit' => 'Usufruit',
    'Nue-propriété' => 'Nue-propriété',
    'Autre' => 'Autre',
];
$createDialogId = 'rental-property-create-dialog';
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>

  <?php if ($notice !== ''): ?>
    <p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <section class="card private-list-section" data-private-filter-scope>
    <div class="private-list-header">
      <div>
        <h2>Propriétés autorisées</h2>
        <p class="muted">Adresses ou ensembles accessibles, avec filtre avant modification.</p>
      </div>
      <button type="button" class="private-create-button" data-private-dialog-open="<?php echo htmlspecialchars($createDialogId, ENT_QUOTES, 'UTF-8'); ?>">Créer une propriété</button>
    </div>
    <?php if ($properties === []): ?>
      <p class="muted">Aucune propriété autorisée pour ce compte.</p>
    <?php else: ?>
      <div class="private-list-tools">
        <div class="private-list-filter-grid">
          <label>Recherche
            <input type="search" placeholder="Nom, adresse, type" data-private-filter="text" />
          </label>
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
      <table class="private-click-table">
        <thead>
          <tr>
            <th>Nom</th>
            <th>Adresse</th>
            <th>Type</th>
            <th>Détention</th>
            <th>Statut</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
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
            $propertyType = is_string($property['propertyType'] ?? null) ? (string) $property['propertyType'] : '';
            $ownershipMode = is_string($property['ownershipMode'] ?? null) ? (string) $property['ownershipMode'] : '';
            $dialogId = 'rental-property-dialog-' . $id;
            ?>
            <tr data-private-filter-row data-filter-text="<?php echo htmlspecialchars(strtolower(trim((string) ($property['name'] ?? '') . ' ' . (string) ($property['address'] ?? '') . ' ' . $propertyType . ' ' . $ownershipMode)), ENT_QUOTES, 'UTF-8'); ?>" data-filter-status="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
              <td><strong><?php echo htmlspecialchars((string) ($property['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong></td>
              <td><?php echo htmlspecialchars((string) ($property['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($propertyType, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($ownershipMode, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($statuses[$status] ?? $status, ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <button type="button" class="private-row-action" data-private-dialog-open="<?php echo htmlspecialchars($dialogId, ENT_QUOTES, 'UTF-8'); ?>">Modifier</button>
              </td>
            </tr>
          <?php endforeach; ?>
          <tr class="private-empty-row" data-private-filter-empty hidden><td colspan="6">Aucune propriété ne correspond aux filtres.</td></tr>
        </tbody>
      </table>
      </div>

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
        $propertyType = is_string($property['propertyType'] ?? null) ? (string) $property['propertyType'] : '';
        $ownershipMode = is_string($property['ownershipMode'] ?? null) ? (string) $property['ownershipMode'] : '';
        $dialogId = 'rental-property-dialog-' . $id;
        ?>
        <dialog class="private-dialog" id="<?php echo htmlspecialchars($dialogId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="<?php echo htmlspecialchars($dialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">
          <div class="private-dialog-panel">
            <header class="private-dialog-header">
              <h3 id="<?php echo htmlspecialchars($dialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">Modifier la propriété <?php echo htmlspecialchars((string) ($property['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
              <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
            </header>
            <form method="post" action="<?php echo htmlspecialchars($propertiesUrl, ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="update_property" />
              <input type="hidden" name="property_id" value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>" />
              <label>Nom <input type="text" name="name" maxlength="160" value="<?php echo htmlspecialchars((string) ($property['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
              <label>Adresse <input type="text" name="address" maxlength="255" value="<?php echo htmlspecialchars((string) ($property['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
              <label>Type
                <select name="property_type" required>
                  <option value="">Choisir un type</option>
                  <?php foreach ($propertyTypes as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $propertyType === $value ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                  <?php if ($propertyType !== '' && !array_key_exists($propertyType, $propertyTypes)): ?>
                    <option value="<?php echo htmlspecialchars($propertyType, ENT_QUOTES, 'UTF-8'); ?>" selected>
                      <?php echo htmlspecialchars($propertyType, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endif; ?>
                </select>
              </label>
              <label>Mode de détention
                <select name="ownership_mode" required>
                  <option value="">Choisir un mode</option>
                  <?php foreach ($ownershipModes as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $ownershipMode === $value ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                  <?php if ($ownershipMode !== '' && !array_key_exists($ownershipMode, $ownershipModes)): ?>
                    <option value="<?php echo htmlspecialchars($ownershipMode, ENT_QUOTES, 'UTF-8'); ?>" selected>
                      <?php echo htmlspecialchars($ownershipMode, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endif; ?>
                </select>
              </label>
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
              <button type="submit" class="private-button-danger">Archiver</button>
            </form>
          </div>
        </dialog>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <dialog class="private-dialog" id="<?php echo htmlspecialchars($createDialogId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="<?php echo htmlspecialchars($createDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">
    <div class="private-dialog-panel">
      <header class="private-dialog-header">
        <h3 id="<?php echo htmlspecialchars($createDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">Créer une propriété</h3>
        <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
      </header>
      <form method="post" action="<?php echo htmlspecialchars($propertiesUrl, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="action" value="create_property" />
        <label>Nom <input type="text" name="name" maxlength="160" required /></label>
        <label>Adresse <input type="text" name="address" maxlength="255" required /></label>
        <label>Type
          <select name="property_type" required>
            <option value="">Choisir un type</option>
            <?php foreach ($propertyTypes as $value => $label): ?>
              <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Mode de détention
          <select name="ownership_mode" required>
            <option value="">Choisir un mode</option>
            <?php foreach ($ownershipModes as $value => $label): ?>
              <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Statut
          <select name="status">
            <option value="draft">Brouillon</option>
            <option value="active">Actif</option>
          </select>
        </label>
        <label>Notes <textarea name="notes" maxlength="2000"></textarea></label>
        <fieldset class="private-fieldset">
          <legend>Bien locatif initial</legend>
          <label class="private-checkbox-inline">
            <input type="checkbox" name="create_default_unit" value="1" />
            Créer aussi le bien locatif Maison entière
          </label>
          <label>Surface du bien locatif <input type="number" name="default_unit_surface" min="0.5" max="10000" step="0.01" /></label>
          <label class="private-checkbox-inline"><input type="checkbox" name="default_unit_furnished" value="1" /> Meublé</label>
        </fieldset>
        <button type="submit">Créer la propriété</button>
      </form>
    </div>
  </dialog>
</section>
