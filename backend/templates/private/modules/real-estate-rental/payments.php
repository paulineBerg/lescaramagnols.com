<?php
$leases = is_array($viewModel['rentalLeases'] ?? null) ? $viewModel['rentalLeases'] : [];
$payments = is_array($viewModel['rentalPayments'] ?? null) ? $viewModel['rentalPayments'] : [];
$csrfToken = is_string($viewModel['rentalCsrfToken'] ?? null) ? (string) $viewModel['rentalCsrfToken'] : '';
$notice = is_string($viewModel['rentalNotice'] ?? null) ? (string) $viewModel['rentalNotice'] : '';
$error = is_string($viewModel['rentalError'] ?? null) ? (string) $viewModel['rentalError'] : '';
$urls = is_array($viewModel['rentalUrls'] ?? null) ? $viewModel['rentalUrls'] : [];
$paymentStatuses = ['draft' => 'Brouillon', 'validated' => 'Valide', 'cancelled' => 'Annule'];
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>
  <?php if ($notice !== ''): ?><p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <section class="card">
    <h2>Ajouter un loyer ou paiement</h2>
    <?php if ($leases === []): ?>
      <p class="muted">Créer d'abord un bail.</p>
    <?php else: ?>
      <form method="post" action="<?php echo htmlspecialchars((string) ($urls['payments'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-rental-payment-form>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="action" value="create_payment" />
        <label>Bail
          <select name="rental_lease_id" required data-rental-lease-select>
            <?php foreach ($leases as $lease): ?>
              <?php if (!is_array($lease) || !is_numeric($lease['id'] ?? null)) { continue; } ?>
              <?php $monthlyRent = is_numeric($lease['monthlyRent'] ?? null) ? number_format((float) $lease['monthlyRent'], 2, '.', '') : ''; ?>
              <option value="<?php echo htmlspecialchars((string) (int) $lease['id'], ENT_QUOTES, 'UTF-8'); ?>" data-monthly-rent="<?php echo htmlspecialchars($monthlyRent, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars((string) ($lease['propertyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars((string) ($lease['unitLabel'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Date paiement <input type="date" name="payment_date" required /></label>
        <label>Annee <input type="number" name="period_year" min="2000" max="2100" value="<?php echo htmlspecialchars(date('Y'), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
        <label>Mois <input type="number" name="period_month" min="1" max="12" value="<?php echo htmlspecialchars(date('n'), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
        <label>Montant attendu <input type="number" name="amount_due" min="0" step="0.01" required data-rental-amount-due /></label>
        <label>Montant encaisse <input type="number" name="amount_paid" min="0" step="0.01" required /></label>
        <label>Statut
          <select name="status">
            <?php foreach ($paymentStatuses as $value => $label): ?>
              <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Notes <textarea name="notes" maxlength="2000"></textarea></label>
        <button type="submit">Créer le paiement</button>
      </form>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2>Loyers et paiements</h2>
    <?php if ($payments === []): ?>
      <p class="muted">Aucun paiement enregistre.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Periode</th><th>Bien</th><th>Attendu</th><th>Encaisse</th><th>Statut</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($payments as $payment): ?>
            <?php if (!is_array($payment)) { continue; } ?>
            <?php $paymentId = is_numeric($payment['id'] ?? null) ? (int) $payment['id'] : 0; ?>
            <tr>
              <td><?php echo htmlspecialchars((string) ($payment['periodYear'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>-<?php echo htmlspecialchars(str_pad((string) ($payment['periodMonth'] ?? ''), 2, '0', STR_PAD_LEFT), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($payment['propertyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(number_format((float) ($payment['amountDue'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</td>
              <td><?php echo htmlspecialchars(number_format((float) ($payment['amountPaid'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</td>
              <td><?php echo htmlspecialchars((string) ($paymentStatuses[(string) ($payment['status'] ?? '')] ?? ($payment['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php if ($paymentId > 0): ?>
                  <form method="post" action="<?php echo htmlspecialchars((string) ($urls['payments'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
                    <input type="hidden" name="action" value="email_receipt" />
                    <input type="hidden" name="payment_id" value="<?php echo htmlspecialchars((string) $paymentId, ENT_QUOTES, 'UTF-8'); ?>" />
                    <input type="hidden" name="subject" value="Quittance de loyer" />
                    <label>Email quittance <input type="email" name="recipient_email" maxlength="190" /></label>
                    <button class="button-small" type="submit">Envoyer quittance</button>
                  </form>
                  <form method="post" action="<?php echo htmlspecialchars((string) ($urls['payments'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
                    <input type="hidden" name="action" value="delete_payment" />
                    <input type="hidden" name="payment_id" value="<?php echo htmlspecialchars((string) $paymentId, ENT_QUOTES, 'UTF-8'); ?>" />
                    <button class="button-small button-danger" type="submit">Supprimer</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
  <script>
    (() => {
      document.querySelectorAll('[data-rental-payment-form]').forEach((form) => {
        const leaseSelect = form.querySelector('[data-rental-lease-select]');
        const amountDue = form.querySelector('[data-rental-amount-due]');
        if (!(leaseSelect instanceof HTMLSelectElement) || !(amountDue instanceof HTMLInputElement)) {
          return;
        }

        const fillAmountDue = () => {
          const selectedOption = leaseSelect.selectedOptions[0] ?? null;
          if (!(selectedOption instanceof HTMLOptionElement)) {
            return;
          }

          const rent = selectedOption.dataset.monthlyRent ?? '';
          if (rent !== '') {
            amountDue.value = rent;
          }
        };

        leaseSelect.addEventListener('change', fillAmountDue);
        fillAmountDue();
      });
    })();
  </script>
</section>
