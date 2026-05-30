<?php
$properties = is_array($viewModel['rentalProperties'] ?? null) ? $viewModel['rentalProperties'] : [];
$units = is_array($viewModel['rentalUnits'] ?? null) ? $viewModel['rentalUnits'] : [];
$expenses = is_array($viewModel['rentalExpenses'] ?? null) ? $viewModel['rentalExpenses'] : [];
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
$expenseStatuses = ['draft' => 'Brouillon', 'validated' => 'Valide', 'cancelled' => 'Annule'];
$createDialogId = 'rental-expense-create-dialog';
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>
  <?php if ($notice !== ''): ?><p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <section class="card private-list-section" data-private-filter-scope>
    <div class="private-list-header">
      <div>
        <h2>Charges</h2>
        <p class="muted">Dépenses filtrables par propriété, libellé et statut.</p>
      </div>
      <button type="button" class="private-create-button" data-private-dialog-open="<?php echo htmlspecialchars($createDialogId, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $propertyNames === [] ? ' disabled' : ''; ?>>Créer une charge</button>
    </div>
    <?php if ($propertyNames === []): ?>
      <p class="muted">Créer d'abord une propriété autorisée.</p>
    <?php endif; ?>
    <?php if ($expenses === []): ?>
      <p class="muted">Aucune charge enregistree.</p>
    <?php else: ?>
      <div class="private-list-tools">
        <div class="private-list-filter-grid">
          <label>Recherche <input type="search" placeholder="Libellé ou propriété" data-private-filter="text" /></label>
          <label>Statut
            <select data-private-filter="status">
              <option value="all">Tous</option>
              <?php foreach ($expenseStatuses as $value => $label): ?>
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
        <thead><tr><th>Date</th><th>Propriété</th><th>Libelle</th><th>Montant</th><th>Nature</th><th>Statut</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($expenses as $expense): ?>
            <?php if (!is_array($expense)) { continue; } ?>
            <?php $expenseId = is_numeric($expense['id'] ?? null) ? (int) $expense['id'] : 0; ?>
            <tr data-private-filter-row data-filter-text="<?php echo htmlspecialchars(strtolower(trim((string) ($expense['expenseDate'] ?? '') . ' ' . (string) ($expense['propertyName'] ?? '') . ' ' . (string) ($expense['label'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?>" data-filter-status="<?php echo htmlspecialchars((string) ($expense['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
              <td><?php echo htmlspecialchars((string) ($expense['expenseDate'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($expense['propertyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($expense['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(number_format((float) ($expense['amount'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</td>
              <td>
                <?php echo ((int) ($expense['isRecoverable'] ?? 0) === 1) ? 'recuperable' : 'non recuperable'; ?>,
                <?php echo ((int) ($expense['isDeductibleCandidate'] ?? 0) === 1) ? 'potentiellement deductible' : 'non deductible'; ?>
              </td>
              <td><?php echo htmlspecialchars((string) ($expenseStatuses[(string) ($expense['status'] ?? '')] ?? ($expense['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php if ($expenseId > 0): ?>
                  <form method="post" action="<?php echo htmlspecialchars((string) ($urls['expenses'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
                    <input type="hidden" name="action" value="delete_expense" />
                    <input type="hidden" name="expense_id" value="<?php echo htmlspecialchars((string) $expenseId, ENT_QUOTES, 'UTF-8'); ?>" />
                    <button class="button-small button-danger" type="submit">Supprimer</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <tr class="private-empty-row" data-private-filter-empty hidden><td colspan="7">Aucune charge ne correspond aux filtres.</td></tr>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </section>

  <dialog class="private-dialog" id="<?php echo htmlspecialchars($createDialogId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="<?php echo htmlspecialchars($createDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">
    <div class="private-dialog-panel">
      <header class="private-dialog-header">
        <h3 id="<?php echo htmlspecialchars($createDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">Créer une charge</h3>
        <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
      </header>
      <?php if ($propertyNames === []): ?>
        <p class="muted">Créer d'abord une propriété autorisée.</p>
      <?php else: ?>
        <form method="post" action="<?php echo htmlspecialchars((string) ($urls['expenses'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-rental-expense-form>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
          <input type="hidden" name="action" value="create_expense" />
          <label>Propriété
            <select name="rental_property_id" required>
              <?php foreach ($propertyNames as $id => $name): ?>
                <option value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Bien locatif optionnel
            <select name="rental_unit_id" data-rental-expense-unit-select>
              <option value="">Propriété entière</option>
              <?php foreach ($units as $unit): ?>
                <?php if (!is_array($unit) || !is_numeric($unit['id'] ?? null)) { continue; } ?>
                <?php $unitPropertyId = is_numeric($unit['rentalPropertyId'] ?? null) ? (int) $unit['rentalPropertyId'] : 0; ?>
                <option value="<?php echo htmlspecialchars((string) (int) $unit['id'], ENT_QUOTES, 'UTF-8'); ?>" data-property-id="<?php echo htmlspecialchars((string) $unitPropertyId, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($unit['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Date <input type="date" name="expense_date" required /></label>
          <label>Libelle <input type="text" name="label" maxlength="160" required /></label>
          <label>Montant <input type="number" name="amount" min="0.01" step="0.01" required /></label>
          <label><input type="checkbox" name="is_recoverable" value="1" /> Charge recuperable</label>
          <label><input type="checkbox" name="is_deductible_candidate" value="1" /> Potentiellement deductible</label>
          <label>Statut
            <select name="status">
              <?php foreach ($expenseStatuses as $value => $label): ?>
                <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Notes <textarea name="notes" maxlength="2000"></textarea></label>
          <button type="submit">Créer la charge</button>
        </form>
      <?php endif; ?>
    </div>
  </dialog>
  <script>
    (() => {
      document.querySelectorAll('[data-rental-expense-form]').forEach((form) => {
        const propertySelect = form.querySelector('[name="rental_property_id"]');
        const unitSelect = form.querySelector('[data-rental-expense-unit-select]');
        if (!(propertySelect instanceof HTMLSelectElement) || !(unitSelect instanceof HTMLSelectElement)) {
          return;
        }

        const unitOptions = Array.from(unitSelect.options);
        const refreshUnits = () => {
          const selectedPropertyId = propertySelect.value;
          let selectedStillVisible = unitSelect.value === '';

          unitOptions.forEach((option) => {
            const isPlaceholder = option.value === '';
            const matches = selectedPropertyId !== '' && option.dataset.propertyId === selectedPropertyId;
            option.hidden = !isPlaceholder && !matches;
            option.disabled = !isPlaceholder && !matches;
            if (option.selected && (isPlaceholder || matches)) {
              selectedStillVisible = true;
            }
          });

          if (!selectedStillVisible) {
            unitSelect.value = '';
          }
        };

        propertySelect.addEventListener('change', refreshUnits);
        refreshUnits();
      });
    })();
  </script>
</section>
