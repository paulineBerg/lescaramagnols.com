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
                  <?php $paymentUrl = (string) ($urls['payments'] ?? ''); ?>
                  <?php if ($paymentUrl !== '' && $balance > 0): ?>
                    <a class="private-row-action" href="<?php echo htmlspecialchars($paymentUrl . '?rent_id=' . rawurlencode((string) $rentId), ENT_QUOTES, 'UTF-8'); ?>">Payer</a>
                  <?php endif; ?>
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
                <?php $chargesProvision = is_numeric($lease['chargesProvision'] ?? null) ? number_format((float) $lease['chargesProvision'], 2, '.', '') : '0.00'; ?>
                <option value="<?php echo htmlspecialchars((string) (int) $lease['id'], ENT_QUOTES, 'UTF-8'); ?>" data-monthly-rent="<?php echo htmlspecialchars($monthlyRent, ENT_QUOTES, 'UTF-8'); ?>" data-charges-provision="<?php echo htmlspecialchars($chargesProvision, ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo htmlspecialchars((string) ($lease['propertyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars((string) ($lease['unitLabel'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars((string) ($lease['tenantName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Période <input type="month" name="period_month_picker" value="<?php echo htmlspecialchars(date('Y-m'), ENT_QUOTES, 'UTF-8'); ?>" required data-rental-period-picker /></label>
          <input type="hidden" name="period_year" value="<?php echo htmlspecialchars(date('Y'), ENT_QUOTES, 'UTF-8'); ?>" data-rental-period-year />
          <input type="hidden" name="period_month" value="<?php echo htmlspecialchars(date('n'), ENT_QUOTES, 'UTF-8'); ?>" data-rental-period-month />
          <label>Date d'échéance <input type="date" name="due_date" value="<?php echo htmlspecialchars(date('Y-m-01'), ENT_QUOTES, 'UTF-8'); ?>" required data-rental-due-date data-rental-due-date-auto="1" /></label>
          <fieldset class="private-fieldset" data-rental-amount-breakdown>
            <legend>Montant du loyer</legend>
            <label><input type="checkbox" name="include_lease_rent" value="1" checked data-rental-component-toggle="rent" /> Loyer du bail <span data-rental-component-label="rent"></span></label>
            <label><input type="checkbox" name="include_lease_charges" value="1" checked data-rental-component-toggle="charges" /> Provision charges <span data-rental-component-label="charges"></span></label>
            <div class="rental-extra-lines" data-rental-extra-lines></div>
            <button type="button" class="private-button-secondary" data-rental-extra-add>Ajouter une ligne diverse</button>
            <label>Total attendu <input type="number" name="amount_due" min="0.01" step="0.01" readonly required data-rental-amount-due /></label>
          </fieldset>
          <label>Statut
            <select name="status">
              <?php foreach ($rentStatuses as $value => $label): ?>
                <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Notes internes <textarea name="notes" maxlength="900"></textarea></label>
          <label>Texte quittance <textarea name="receipt_text" maxlength="700" placeholder="Mention visible dans la quittance si besoin."></textarea></label>
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
        const periodPicker = form.querySelector('[data-rental-period-picker]');
        const periodYear = form.querySelector('[data-rental-period-year]');
        const periodMonth = form.querySelector('[data-rental-period-month]');
        const dueDate = form.querySelector('[data-rental-due-date]');
        const rentToggle = form.querySelector('[data-rental-component-toggle="rent"]');
        const chargesToggle = form.querySelector('[data-rental-component-toggle="charges"]');
        const rentLabel = form.querySelector('[data-rental-component-label="rent"]');
        const chargesLabel = form.querySelector('[data-rental-component-label="charges"]');
        const extraLines = form.querySelector('[data-rental-extra-lines]');
        const extraAdd = form.querySelector('[data-rental-extra-add]');
        if (!(leaseSelect instanceof HTMLSelectElement) || !(amountDue instanceof HTMLInputElement)) {
          return;
        }

        const parseAmount = (value) => {
          const amount = Number.parseFloat(String(value ?? '').replace(',', '.'));

          return Number.isFinite(amount) ? amount : 0;
        };

        const formatAmount = (amount) => amount.toFixed(2);

        const selectedAmounts = () => {
          const selectedOption = leaseSelect.selectedOptions[0] ?? null;
          if (!(selectedOption instanceof HTMLOptionElement)) {
            return {rent: 0, charges: 0};
          }

          return {
            rent: parseAmount(selectedOption.dataset.monthlyRent ?? '0'),
            charges: parseAmount(selectedOption.dataset.chargesProvision ?? '0'),
          };
        };

        const updateTotal = () => {
          const amounts = selectedAmounts();
          if (rentLabel instanceof HTMLElement) {
            rentLabel.textContent = `(${formatAmount(amounts.rent)} €)`;
          }
          if (chargesLabel instanceof HTMLElement) {
            chargesLabel.textContent = `(${formatAmount(amounts.charges)} €)`;
          }

          let total = 0;
          if (rentToggle instanceof HTMLInputElement && rentToggle.checked) {
            total += amounts.rent;
          }
          if (chargesToggle instanceof HTMLInputElement && chargesToggle.checked) {
            total += amounts.charges;
          }

          form.querySelectorAll('[data-rental-extra-amount]').forEach((input) => {
            if (input instanceof HTMLInputElement) {
              total += parseAmount(input.value);
            }
          });
          amountDue.value = formatAmount(Math.max(0, total));
        };

        const syncPeriod = () => {
          if (!(periodPicker instanceof HTMLInputElement) || !/^(\d{4})-(\d{2})$/.test(periodPicker.value)) {
            return;
          }

          const [year, month] = periodPicker.value.split('-');
          if (periodYear instanceof HTMLInputElement) {
            periodYear.value = year;
          }
          if (periodMonth instanceof HTMLInputElement) {
            periodMonth.value = String(Number(month));
          }
          if (dueDate instanceof HTMLInputElement && (dueDate.value === '' || dueDate.dataset.rentalDueDateAuto === '1')) {
            dueDate.value = `${periodPicker.value}-01`;
            dueDate.dataset.rentalDueDateAuto = '1';
          }
        };

        const createExtraLine = () => {
          if (!(extraLines instanceof HTMLElement)) {
            return;
          }

          const row = document.createElement('div');
          row.className = 'rental-extra-line';

          const labelWrap = document.createElement('label');
          labelWrap.textContent = 'Libellé';
          const labelInput = document.createElement('input');
          labelInput.name = 'extra_label[]';
          labelInput.type = 'text';
          labelInput.maxLength = 80;
          labelInput.placeholder = 'Eau, EDF, taxe...';
          labelWrap.append(labelInput);

          const amountWrap = document.createElement('label');
          amountWrap.textContent = 'Montant';
          const amountInput = document.createElement('input');
          amountInput.name = 'extra_amount[]';
          amountInput.type = 'number';
          amountInput.min = '0';
          amountInput.step = '0.01';
          amountInput.dataset.rentalExtraAmount = '1';
          amountInput.addEventListener('input', updateTotal);
          amountWrap.append(amountInput);

          const removeButton = document.createElement('button');
          removeButton.type = 'button';
          removeButton.className = 'private-button-secondary';
          removeButton.textContent = 'Retirer';
          removeButton.addEventListener('click', () => {
            row.remove();
            updateTotal();
          });

          row.append(labelWrap, amountWrap, removeButton);
          extraLines.append(row);
          labelInput.focus();
        };

        leaseSelect.addEventListener('change', updateTotal);
        if (rentToggle instanceof HTMLInputElement) {
          rentToggle.addEventListener('change', updateTotal);
        }
        if (chargesToggle instanceof HTMLInputElement) {
          chargesToggle.addEventListener('change', updateTotal);
        }
        if (periodPicker instanceof HTMLInputElement) {
          periodPicker.addEventListener('change', syncPeriod);
        }
        if (dueDate instanceof HTMLInputElement) {
          dueDate.addEventListener('input', () => {
            dueDate.dataset.rentalDueDateAuto = '0';
          });
        }
        if (extraAdd instanceof HTMLButtonElement) {
          extraAdd.addEventListener('click', createExtraLine);
        }
        syncPeriod();
        updateTotal();
      });
    })();
  </script>
</section>
