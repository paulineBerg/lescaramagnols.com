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
        $propertyNames[(int) $property['id']] = (string) ($property['name'] ?? ('Bien #' . (int) $property['id']));
    }
}
$expenseStatuses = ['draft' => 'Brouillon', 'validated' => 'Valide', 'cancelled' => 'Annule'];
?>
<section>
  <p class="muted">
    <a href="<?php echo htmlspecialchars((string) ($urls['payments'] ?? private_portal_url('rental_payments')), ENT_QUOTES, 'UTF-8'); ?>">Loyers</a>
    · <a href="<?php echo htmlspecialchars((string) ($urls['expenses'] ?? private_portal_url('rental_expenses')), ENT_QUOTES, 'UTF-8'); ?>">Charges</a>
    · <a href="<?php echo htmlspecialchars((string) ($urls['documents'] ?? private_portal_url('rental_documents')), ENT_QUOTES, 'UTF-8'); ?>">Documents</a>
    · <a href="<?php echo htmlspecialchars((string) ($urls['summary'] ?? private_portal_url('rental_summary')), ENT_QUOTES, 'UTF-8'); ?>">Synthese</a>
  </p>
  <?php if ($notice !== ''): ?><p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <section class="card">
    <h2>Ajouter une charge</h2>
    <?php if ($propertyNames === []): ?>
      <p class="muted">Créer d'abord un bien locatif autorise.</p>
    <?php else: ?>
      <form method="post" action="<?php echo htmlspecialchars((string) ($urls['expenses'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="action" value="create_expense" />
        <label>Bien
          <select name="rental_property_id" required>
            <?php foreach ($propertyNames as $id => $name): ?>
              <option value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Lot optionnel
          <select name="rental_unit_id">
            <option value="">Bien entier</option>
            <?php foreach ($units as $unit): ?>
              <?php if (!is_array($unit) || !is_numeric($unit['id'] ?? null)) { continue; } ?>
              <option value="<?php echo htmlspecialchars((string) (int) $unit['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($unit['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
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
  </section>

  <section class="card">
    <h2>Charges</h2>
    <?php if ($expenses === []): ?>
      <p class="muted">Aucune charge enregistree.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Date</th><th>Bien</th><th>Libelle</th><th>Montant</th><th>Nature</th><th>Statut</th></tr></thead>
        <tbody>
          <?php foreach ($expenses as $expense): ?>
            <?php if (!is_array($expense)) { continue; } ?>
            <tr>
              <td><?php echo htmlspecialchars((string) ($expense['expenseDate'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($expense['propertyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($expense['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(number_format((float) ($expense['amount'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</td>
              <td>
                <?php echo ((int) ($expense['isRecoverable'] ?? 0) === 1) ? 'recuperable' : 'non recuperable'; ?>,
                <?php echo ((int) ($expense['isDeductibleCandidate'] ?? 0) === 1) ? 'potentiellement deductible' : 'non deductible'; ?>
              </td>
              <td><?php echo htmlspecialchars((string) ($expenseStatuses[(string) ($expense['status'] ?? '')] ?? ($expense['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</section>
