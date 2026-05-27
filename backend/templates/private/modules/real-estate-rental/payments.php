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
  <p class="muted">
    <a href="<?php echo htmlspecialchars((string) ($urls['leases'] ?? private_portal_url('rental_leases')), ENT_QUOTES, 'UTF-8'); ?>">Baux</a>
    · <a href="<?php echo htmlspecialchars((string) ($urls['payments'] ?? private_portal_url('rental_payments')), ENT_QUOTES, 'UTF-8'); ?>">Loyers</a>
    · <a href="<?php echo htmlspecialchars((string) ($urls['expenses'] ?? private_portal_url('rental_expenses')), ENT_QUOTES, 'UTF-8'); ?>">Charges</a>
    · <a href="<?php echo htmlspecialchars((string) ($urls['summary'] ?? private_portal_url('rental_summary')), ENT_QUOTES, 'UTF-8'); ?>">Synthese</a>
  </p>
  <?php if ($notice !== ''): ?><p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <section class="card">
    <h2>Ajouter un loyer ou paiement</h2>
    <?php if ($leases === []): ?>
      <p class="muted">Créer d'abord un bail.</p>
    <?php else: ?>
      <form method="post" action="<?php echo htmlspecialchars((string) ($urls['payments'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="action" value="create_payment" />
        <label>Bail
          <select name="rental_lease_id" required>
            <?php foreach ($leases as $lease): ?>
              <?php if (!is_array($lease) || !is_numeric($lease['id'] ?? null)) { continue; } ?>
              <option value="<?php echo htmlspecialchars((string) (int) $lease['id'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars((string) ($lease['propertyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars((string) ($lease['unitLabel'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Date paiement <input type="date" name="payment_date" required /></label>
        <label>Annee <input type="number" name="period_year" min="2000" max="2100" value="<?php echo htmlspecialchars(date('Y'), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
        <label>Mois <input type="number" name="period_month" min="1" max="12" value="<?php echo htmlspecialchars(date('n'), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
        <label>Montant attendu <input type="number" name="amount_due" min="0" step="0.01" required /></label>
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
        <thead><tr><th>Periode</th><th>Bien</th><th>Attendu</th><th>Encaisse</th><th>Statut</th></tr></thead>
        <tbody>
          <?php foreach ($payments as $payment): ?>
            <?php if (!is_array($payment)) { continue; } ?>
            <tr>
              <td><?php echo htmlspecialchars((string) ($payment['periodYear'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>-<?php echo htmlspecialchars(str_pad((string) ($payment['periodMonth'] ?? ''), 2, '0', STR_PAD_LEFT), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($payment['propertyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(number_format((float) ($payment['amountDue'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</td>
              <td><?php echo htmlspecialchars(number_format((float) ($payment['amountPaid'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</td>
              <td><?php echo htmlspecialchars((string) ($paymentStatuses[(string) ($payment['status'] ?? '')] ?? ($payment['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</section>
