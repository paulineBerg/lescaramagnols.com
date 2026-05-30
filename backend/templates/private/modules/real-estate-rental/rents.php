<?php
$leases = is_array($viewModel['rentalLeases'] ?? null) ? $viewModel['rentalLeases'] : [];
$rents = is_array($viewModel['rentalRents'] ?? null) ? $viewModel['rentalRents'] : [];
$csrfToken = is_string($viewModel['rentalCsrfToken'] ?? null) ? (string) $viewModel['rentalCsrfToken'] : '';
$notice = is_string($viewModel['rentalNotice'] ?? null) ? (string) $viewModel['rentalNotice'] : '';
$error = is_string($viewModel['rentalError'] ?? null) ? (string) $viewModel['rentalError'] : '';
$urls = is_array($viewModel['rentalUrls'] ?? null) ? $viewModel['rentalUrls'] : [];
$rentStatuses = ['draft' => 'Brouillon', 'validated' => 'Valide', 'cancelled' => 'Annule'];
$createDialogId = 'rental-rent-create-dialog';
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>
  <?php if ($notice !== ''): ?><p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <section class="card private-list-section" data-private-filter-scope>
    <div class="private-list-header">
      <div>
        <h2>Loyers</h2>
        <p class="muted">Échéances de loyer par bail, période et statut.</p>
      </div>
      <button type="button" class="private-create-button" data-private-dialog-open="<?php echo htmlspecialchars($createDialogId, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $leases === [] ? ' disabled' : ''; ?>>Créer un loyer</button>
    </div>
    <?php if ($leases === []): ?>
      <p class="muted">Créer d'abord un bail.</p>
    <?php endif; ?>
    <?php if ($rents === []): ?>
      <p class="muted">Aucun loyer enregistre.</p>
    <?php else: ?>
      <div class="private-list-tools">
        <div class="private-list-filter-grid">
          <label>Recherche <input type="search" placeholder="Période, propriété, bien locatif" data-private-filter="text" /></label>
          <label>Statut
            <select data-private-filter="status">
              <option value="all">Tous</option>
              <?php foreach ($rentStatuses as $value => $label): ?>
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
        <thead><tr><th>Période</th><th>Propriété</th><th>Bien locatif</th><th>Locataire</th><th>Attendu</th><th>Payé</th><th>Solde</th><th>Statut</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($rents as $rent): ?>
            <?php if (!is_array($rent)) { continue; } ?>
            <?php
            $rentId = is_numeric($rent['id'] ?? null) ? (int) $rent['id'] : 0;
            $amountDue = (float) ($rent['amountDue'] ?? 0);
            $amountPaid = (float) ($rent['amountPaid'] ?? 0);
            $balance = max(0.0, $amountDue - $amountPaid);
            $period = (string) ($rent['periodYear'] ?? '') . '-' . str_pad((string) ($rent['periodMonth'] ?? ''), 2, '0', STR_PAD_LEFT);
            ?>
            <tr data-private-filter-row data-filter-text="<?php echo htmlspecialchars(strtolower(trim($period . ' ' . (string) ($rent['propertyName'] ?? '') . ' ' . (string) ($rent['unitLabel'] ?? '') . ' ' . (string) ($rent['tenantName'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?>" data-filter-status="<?php echo htmlspecialchars((string) ($rent['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
              <td><?php echo htmlspecialchars($period, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($rent['propertyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($rent['unitLabel'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($rent['tenantName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(number_format($amountDue, 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</td>
              <td><?php echo htmlspecialchars(number_format($amountPaid, 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</td>
              <td><?php echo htmlspecialchars(number_format($balance, 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</td>
              <td><?php echo htmlspecialchars((string) ($rentStatuses[(string) ($rent['status'] ?? '')] ?? ($rent['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php if ($rentId > 0): ?>
                  <form method="post" action="<?php echo htmlspecialchars((string) ($urls['rents'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
                    <input type="hidden" name="action" value="delete_rent" />
                    <input type="hidden" name="rent_id" value="<?php echo htmlspecialchars((string) $rentId, ENT_QUOTES, 'UTF-8'); ?>" />
                    <button class="button-small button-danger" type="submit">Supprimer</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <tr class="private-empty-row" data-private-filter-empty hidden><td colspan="9">Aucun loyer ne correspond aux filtres.</td></tr>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </section>

  <dialog class="private-dialog" id="<?php echo htmlspecialchars($createDialogId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="<?php echo htmlspecialchars($createDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">
    <div class="private-dialog-panel">
      <header class="private-dialog-header">
        <h3 id="<?php echo htmlspecialchars($createDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">Créer un loyer</h3>
        <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
      </header>
      <?php if ($leases === []): ?>
        <p class="muted">Créer d'abord un bail.</p>
      <?php else: ?>
        <form method="post" action="<?php echo htmlspecialchars((string) ($urls['rents'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-rental-rent-form>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
          <input type="hidden" name="action" value="create_rent" />
          <label>Bail
            <select name="rental_lease_id" required data-rental-lease-select>
              <?php foreach ($leases as $lease): ?>
                <?php if (!is_array($lease) || !is_numeric($lease['id'] ?? null)) { continue; } ?>
                <?php $monthlyRent = is_numeric($lease['monthlyRent'] ?? null) ? number_format((float) $lease['monthlyRent'], 2, '.', '') : ''; ?>
                <option value="<?php echo htmlspecialchars((string) (int) $lease['id'], ENT_QUOTES, 'UTF-8'); ?>" data-monthly-rent="<?php echo htmlspecialchars($monthlyRent, ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo htmlspecialchars((string) ($lease['propertyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars((string) ($lease['unitLabel'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars((string) ($lease['tenantName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Date d'échéance <input type="date" name="due_date" value="<?php echo htmlspecialchars(date('Y-m-01'), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
          <label>Année <input type="number" name="period_year" min="2000" max="2100" value="<?php echo htmlspecialchars(date('Y'), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
          <label>Mois <input type="number" name="period_month" min="1" max="12" value="<?php echo htmlspecialchars(date('n'), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
          <label>Montant attendu <input type="number" name="amount_due" min="0.01" step="0.01" required data-rental-amount-due /></label>
          <label>Statut
            <select name="status">
              <?php foreach ($rentStatuses as $value => $label): ?>
                <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Notes <textarea name="notes" maxlength="2000"></textarea></label>
          <button type="submit">Créer le loyer</button>
        </form>
      <?php endif; ?>
    </div>
  </dialog>
  <script>
    (() => {
      document.querySelectorAll('[data-rental-rent-form]').forEach((form) => {
        const leaseSelect = form.querySelector('[data-rental-lease-select]');
        const amountDue = form.querySelector('[data-rental-amount-due]');
        if (!(leaseSelect instanceof HTMLSelectElement) || !(amountDue instanceof HTMLInputElement)) {
          return;
        }

        const fillAmountDue = () => {
          const selectedOption = leaseSelect.selectedOptions[0] ?? null;
          if (selectedOption instanceof HTMLOptionElement && (selectedOption.dataset.monthlyRent ?? '') !== '') {
            amountDue.value = selectedOption.dataset.monthlyRent ?? '';
          }
        };

        leaseSelect.addEventListener('change', fillAmountDue);
        fillAmountDue();
      });
    })();
  </script>
</section>
