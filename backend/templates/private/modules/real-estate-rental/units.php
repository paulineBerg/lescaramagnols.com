<?php
$properties = is_array($viewModel['rentalProperties'] ?? null) ? $viewModel['rentalProperties'] : [];
$units = is_array($viewModel['rentalUnits'] ?? null) ? $viewModel['rentalUnits'] : [];
$csrfToken = is_string($viewModel['rentalCsrfToken'] ?? null) ? (string) $viewModel['rentalCsrfToken'] : '';
$notice = is_string($viewModel['rentalNotice'] ?? null) ? (string) $viewModel['rentalNotice'] : '';
$error = is_string($viewModel['rentalError'] ?? null) ? (string) $viewModel['rentalError'] : '';
$urls = is_array($viewModel['rentalUrls'] ?? null) ? $viewModel['rentalUrls'] : [];
$unitsUrl = is_string($urls['units'] ?? null) ? (string) $urls['units'] : private_portal_url('rental_units');
$unitStatuses = ['available' => 'Disponible', 'unavailable' => 'Indisponible'];
$unitTypes = [
    'apartment' => 'Appartement',
    'house' => 'Maison',
    'garage' => 'Garage',
    'parking' => 'Parking',
    'commercial_space' => 'Local commercial',
    'room' => 'Chambre',
    'storage' => 'Cave / stockage',
    'other' => 'Autre',
];
$createDialogId = 'rental-unit-create-dialog';
$propertyNames = [];
foreach ($properties as $property) {
    if (is_array($property) && is_numeric($property['id'] ?? null)) {
        $propertyNames[(int) $property['id']] = (string) ($property['name'] ?? ('Propriété #' . (int) $property['id']));
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

  <section class="card private-list-section" data-private-filter-scope>
    <div class="private-list-header">
      <div>
        <h2>Biens locatifs autorisés</h2>
        <p class="muted">Unités louables rattachées aux propriétés, filtrables par type, propriété ou disponibilité.</p>
      </div>
      <button type="button" class="private-create-button" data-private-dialog-open="<?php echo htmlspecialchars($createDialogId, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $properties === [] ? ' disabled' : ''; ?>>Créer un bien locatif</button>
    </div>
    <?php if ($properties === []): ?>
      <p class="muted">Créer d’abord une propriété autorisée.</p>
    <?php endif; ?>
    <?php if ($units === []): ?>
      <p class="muted">Aucun bien locatif actif.</p>
    <?php else: ?>
      <div class="private-list-tools">
        <div class="private-list-filter-grid">
          <label>Recherche <input type="search" placeholder="Bien locatif, type ou propriété" data-private-filter="text" /></label>
          <label>Disponibilité
            <select data-private-filter="status">
              <option value="all">Tous</option>
              <?php foreach ($unitStatuses as $value => $label): ?>
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
            <th>Bien locatif</th>
            <th>Propriété</th>
            <th>Type</th>
            <th>Désignation</th>
            <th>Adresse / repère</th>
            <th>Fiscal</th>
            <th>Pièces</th>
            <th>Surface</th>
            <th>Meublé</th>
            <th>Disponibilité</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
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
            $status = in_array($status, ['occupied', 'maintenance'], true) ? 'unavailable' : $status;
            $unitType = is_string($unit['unitType'] ?? null) ? (string) $unit['unitType'] : 'other';
            $typeLabel = $unitTypes[$unitType] ?? $unitTypes['other'];
            $address = is_string($unit['address'] ?? null) ? trim((string) $unit['address']) : '';
            $building = is_string($unit['building'] ?? null) ? trim((string) $unit['building']) : '';
            $floor = is_string($unit['floor'] ?? null) ? trim((string) $unit['floor']) : '';
            $door = is_string($unit['door'] ?? null) ? trim((string) $unit['door']) : '';
            $designation = is_string($unit['designation'] ?? null) ? trim((string) $unit['designation']) : '';
            $taxIdentifier = is_string($unit['taxIdentifier'] ?? null) ? trim((string) $unit['taxIdentifier']) : '';
            $roomCount = is_numeric($unit['roomCount'] ?? null) ? (int) $unit['roomCount'] : null;
            $unavailableUntil = is_string($unit['unavailableUntil'] ?? null) ? trim((string) $unit['unavailableUntil']) : '';
            $locationParts = array_filter([
                $address,
                $building !== '' ? 'Bât. ' . $building : '',
                $floor !== '' ? 'Étage ' . $floor : '',
                $door !== '' ? 'Porte ' . $door : '',
            ]);
            $locationLabel = $locationParts !== [] ? implode(' - ', $locationParts) : 'Adresse de la propriété';
            $dialogId = 'rental-unit-dialog-' . $id;
            ?>
            <tr data-private-filter-row data-filter-text="<?php echo htmlspecialchars(strtolower(trim((string) ($unit['label'] ?? '') . ' ' . (string) ($propertyNames[$propertyId] ?? '') . ' ' . $typeLabel . ' ' . $designation . ' ' . $taxIdentifier . ' ' . $locationLabel . ' ' . (empty($unit['furnished']) ? 'non meuble' : 'meuble'))), ENT_QUOTES, 'UTF-8'); ?>" data-filter-status="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
              <td><strong><?php echo htmlspecialchars((string) ($unit['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong></td>
              <td><?php echo htmlspecialchars((string) ($propertyNames[$propertyId] ?? ('Propriété #' . $propertyId)), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($designation, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($taxIdentifier, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo $roomCount !== null ? htmlspecialchars((string) $roomCount, ENT_QUOTES, 'UTF-8') : ''; ?></td>
              <td><?php echo htmlspecialchars((string) ($unit['surface'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> m²</td>
              <td><?php echo !empty($unit['furnished']) ? 'Oui' : 'Non'; ?></td>
              <td><?php echo htmlspecialchars((string) ($unitStatuses[$status] ?? $status) . ($unavailableUntil !== '' ? ' jusqu’au ' . $unavailableUntil : ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <button type="button" class="private-row-action" data-private-dialog-open="<?php echo htmlspecialchars($dialogId, ENT_QUOTES, 'UTF-8'); ?>">Modifier</button>
              </td>
            </tr>
          <?php endforeach; ?>
          <tr class="private-empty-row" data-private-filter-empty hidden><td colspan="11">Aucun bien locatif ne correspond aux filtres.</td></tr>
        </tbody>
      </table>
      </div>

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
        $status = in_array($status, ['occupied', 'maintenance'], true) ? 'unavailable' : $status;
        $unitType = is_string($unit['unitType'] ?? null) ? (string) $unit['unitType'] : 'other';
        $dialogId = 'rental-unit-dialog-' . $id;
        ?>
        <dialog class="private-dialog" id="<?php echo htmlspecialchars($dialogId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="<?php echo htmlspecialchars($dialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">
          <div class="private-dialog-panel">
            <header class="private-dialog-header">
              <h3 id="<?php echo htmlspecialchars($dialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">Modifier le bien locatif <?php echo htmlspecialchars((string) ($unit['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
              <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
            </header>
            <form method="post" action="<?php echo htmlspecialchars($unitsUrl, ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="update_unit" />
              <input type="hidden" name="unit_id" value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>" />
              <label>Propriété
                <select name="rental_property_id" required>
                  <?php foreach ($propertyNames as $optionId => $name): ?>
                    <option value="<?php echo htmlspecialchars((string) $optionId, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $propertyId === $optionId ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Nom du bien locatif <input type="text" name="label" maxlength="160" value="<?php echo htmlspecialchars((string) ($unit['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
              <label>Type
                <select name="unit_type" required>
                  <?php foreach ($unitTypes as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $unitType === $value ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Adresse spécifique <input type="text" name="address" maxlength="255" value="<?php echo htmlspecialchars((string) ($unit['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Vide = adresse de la propriété" /></label>
              <label>Bâtiment <input type="text" name="building" maxlength="120" value="<?php echo htmlspecialchars((string) ($unit['building'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
              <label>Étage <input type="text" name="floor" maxlength="64" value="<?php echo htmlspecialchars((string) ($unit['floor'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
              <label>Porte / repère <input type="text" name="door" maxlength="64" value="<?php echo htmlspecialchars((string) ($unit['door'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
              <fieldset class="private-fieldset">
                <legend>Identifiants et descriptif V2</legend>
                <label>Identifiant fiscal <input type="text" name="tax_identifier" maxlength="80" value="<?php echo htmlspecialchars((string) ($unit['taxIdentifier'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
                <label>Nombre de pièces <input type="number" name="room_count" min="0" max="99" step="1" value="<?php echo htmlspecialchars((string) ($unit['roomCount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
                <label>Désignation <input type="text" name="designation" maxlength="160" value="<?php echo htmlspecialchars((string) ($unit['designation'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
                <label>Autres détails <textarea name="other_details" maxlength="2000"><?php echo htmlspecialchars((string) ($unit['otherDetails'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea></label>
                <label>Équipements <textarea name="equipment_elements" maxlength="2000"><?php echo htmlspecialchars((string) ($unit['equipmentElements'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea></label>
                <label>Mode de chauffage <input type="text" name="heating_production_mode" maxlength="160" value="<?php echo htmlspecialchars((string) ($unit['heatingProductionMode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
                <label>Production eau chaude <input type="text" name="hot_water_production_mode" maxlength="160" value="<?php echo htmlspecialchars((string) ($unit['hotWaterProductionMode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
                <label>Assainissement <input type="text" name="sanitation" maxlength="160" value="<?php echo htmlspecialchars((string) ($unit['sanitation'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
              </fieldset>
              <label>Surface <input type="number" name="surface" min="0.5" max="10000" step="0.01" value="<?php echo htmlspecialchars((string) ($unit['surface'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
              <label class="private-checkbox-inline"><input type="checkbox" name="furnished" value="1" <?php echo !empty($unit['furnished']) ? 'checked' : ''; ?> /> Meublé</label>
              <label>Disponibilité
                <select name="status">
                  <?php foreach ($unitStatuses as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $status === $value ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Indisponible jusqu’au <input type="date" name="unavailable_until" value="<?php echo htmlspecialchars((string) ($unit['unavailableUntil'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
              <label>Notes <textarea name="notes" maxlength="2000"><?php echo htmlspecialchars((string) ($unit['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea></label>
              <button type="submit">Mettre à jour</button>
            </form>
            <form method="post" action="<?php echo htmlspecialchars(rtrim($unitsUrl, '/') . '/' . $id . '/archive', ENT_QUOTES, 'UTF-8'); ?>">
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
        <h3 id="<?php echo htmlspecialchars($createDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">Créer un bien locatif</h3>
        <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
      </header>
      <?php if ($properties === []): ?>
        <p class="muted">Créer d’abord une propriété autorisée.</p>
      <?php else: ?>
        <form method="post" action="<?php echo htmlspecialchars($unitsUrl, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
          <input type="hidden" name="action" value="create_unit" />
          <label>Propriété
            <select name="rental_property_id" required>
              <?php foreach ($propertyNames as $id => $name): ?>
                <option value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Nom du bien locatif <input type="text" name="label" maxlength="160" required /></label>
          <label>Type
            <select name="unit_type" required>
              <?php foreach ($unitTypes as $value => $label): ?>
                <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Adresse spécifique <input type="text" name="address" maxlength="255" placeholder="Vide = adresse de la propriété" /></label>
          <label>Bâtiment <input type="text" name="building" maxlength="120" /></label>
          <label>Étage <input type="text" name="floor" maxlength="64" /></label>
          <label>Porte / repère <input type="text" name="door" maxlength="64" /></label>
          <fieldset class="private-fieldset">
            <legend>Identifiants et descriptif V2</legend>
            <label>Identifiant fiscal <input type="text" name="tax_identifier" maxlength="80" /></label>
            <label>Nombre de pièces <input type="number" name="room_count" min="0" max="99" step="1" /></label>
            <label>Désignation <input type="text" name="designation" maxlength="160" /></label>
            <label>Autres détails <textarea name="other_details" maxlength="2000"></textarea></label>
            <label>Équipements <textarea name="equipment_elements" maxlength="2000"></textarea></label>
            <label>Mode de chauffage <input type="text" name="heating_production_mode" maxlength="160" /></label>
            <label>Production eau chaude <input type="text" name="hot_water_production_mode" maxlength="160" /></label>
            <label>Assainissement <input type="text" name="sanitation" maxlength="160" /></label>
          </fieldset>
          <label>Surface <input type="number" name="surface" min="0.5" max="10000" step="0.01" required /></label>
          <label class="private-checkbox-inline"><input type="checkbox" name="furnished" value="1" /> Meublé</label>
          <label>Disponibilité
            <select name="status">
              <option value="available">Disponible</option>
              <option value="unavailable">Indisponible</option>
            </select>
          </label>
          <label>Indisponible jusqu’au <input type="date" name="unavailable_until" /></label>
          <label>Notes <textarea name="notes" maxlength="2000"></textarea></label>
          <button type="submit">Créer le bien locatif</button>
        </form>
      <?php endif; ?>
    </div>
  </dialog>
</section>
