<?php
$rents = is_array($viewModel['rentalRents'] ?? null) ? $viewModel['rentalRents'] : [];
$payments = is_array($viewModel['rentalPayments'] ?? null) ? $viewModel['rentalPayments'] : [];
$csrfToken = is_string($viewModel['rentalCsrfToken'] ?? null) ? (string) $viewModel['rentalCsrfToken'] : '';
$notice = is_string($viewModel['rentalNotice'] ?? null) ? (string) $viewModel['rentalNotice'] : '';
$error = is_string($viewModel['rentalError'] ?? null) ? (string) $viewModel['rentalError'] : '';
$urls = is_array($viewModel['rentalUrls'] ?? null) ? $viewModel['rentalUrls'] : [];
$prefillRentId = is_numeric($viewModel['rentalPaymentPrefillRentId'] ?? null) ? (int) $viewModel['rentalPaymentPrefillRentId'] : 0;
$paymentStatuses = ['draft' => 'Brouillon', 'validated' => 'Valide', 'cancelled' => 'Annule'];
$paymentKinds = ['tenant' => 'Locataire', 'caf' => 'CAF', 'refund' => 'Remboursement', 'adjustment' => 'Régularisation'];
$paymentMethods = ['' => 'Non précisé', 'bank_transfer' => 'Virement', 'cash' => 'Espèces', 'cheque' => 'Chèque', 'card' => 'Carte', 'direct_debit' => 'Prélèvement', 'other' => 'Autre'];
$createDialogId = 'rental-payment-create-dialog';
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>
  <?php if ($notice !== ''): ?><p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <section class="card private-list-section" data-private-filter-scope>
    <div class="private-list-header">
      <div>
        <h2>Paiements</h2>
        <p class="muted">Encaissements rattachés à un loyer. Plusieurs paiements peuvent solder une même échéance.</p>
      </div>
      <button type="button" class="private-create-button" data-private-dialog-open="<?php echo htmlspecialchars($createDialogId, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $rents === [] ? ' disabled' : ''; ?>>Créer un paiement</button>
    </div>
    <?php if ($rents === []): ?>
      <p class="muted">Créer d'abord un loyer.</p>
    <?php endif; ?>
    <?php if ($payments === []): ?>
      <p class="muted">Aucun paiement enregistre.</p>
    <?php else: ?>
      <div class="private-list-tools">
        <div class="private-list-filter-grid">
          <label>Recherche <input type="search" placeholder="Période, propriété, locataire" data-private-filter="text" /></label>
          <label>Statut
            <select data-private-filter="status">
              <option value="all">Tous</option>
              <?php foreach ($paymentStatuses as $value => $label): ?>
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
        <thead><tr><th>Loyer</th><th>Propriété</th><th>Date paiement</th><th>Montant</th><th>Type</th><th>Mode</th><th>Référence</th><th>Statut</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($payments as $payment): ?>
            <?php if (!is_array($payment)) { continue; } ?>
            <?php
            $paymentId = is_numeric($payment['id'] ?? null) ? (int) $payment['id'] : 0;
            $periodYear = (string) (($payment['rentPeriodYear'] ?? null) ?: ($payment['periodYear'] ?? ''));
            $periodMonth = (string) (($payment['rentPeriodMonth'] ?? null) ?: ($payment['periodMonth'] ?? ''));
            $period = $periodYear . '-' . str_pad($periodMonth, 2, '0', STR_PAD_LEFT);
            ?>
            <tr data-private-filter-row data-filter-text="<?php echo htmlspecialchars(strtolower(trim($period . ' ' . (string) ($payment['propertyName'] ?? '') . ' ' . (string) ($payment['unitLabel'] ?? '') . ' ' . (string) ($payment['tenantName'] ?? '') . ' ' . (string) ($payment['paymentKind'] ?? '') . ' ' . (string) ($payment['paymentReference'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?>" data-filter-status="<?php echo htmlspecialchars((string) ($payment['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
              <td><?php echo htmlspecialchars($period, ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php echo htmlspecialchars((string) ($payment['propertyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                <?php if (($payment['unitLabel'] ?? '') !== ''): ?><br /><small><?php echo htmlspecialchars((string) $payment['unitLabel'], ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>
              </td>
              <td><?php echo htmlspecialchars((string) ($payment['paymentDate'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(number_format((float) ($payment['amountPaid'] ?? 0), 2, ',', ' '), ENT_QUOTES, 'UTF-8'); ?> €</td>
              <td><?php echo htmlspecialchars((string) ($paymentKinds[(string) ($payment['paymentKind'] ?? 'tenant')] ?? ($payment['paymentKind'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($paymentMethods[(string) ($payment['paymentMethod'] ?? '')] ?? ($payment['paymentMethod'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($payment['paymentReference'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($paymentStatuses[(string) ($payment['status'] ?? '')] ?? ($payment['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php if ($paymentId > 0): ?>
                  <button class="button-small" type="button" data-private-dialog-open="rental-payment-edit-dialog-<?php echo htmlspecialchars((string) $paymentId, ENT_QUOTES, 'UTF-8'); ?>">Corriger</button>
                  <?php if (($payment['status'] ?? '') === 'validated'): ?>
                    <?php $documentType = (string) ($payment['rentStatus'] ?? '') === 'paid' ? 'receipt' : 'partial_receipt'; ?>
                    <?php $documentLabel = $documentType === 'receipt' ? 'quittance' : 'reçu partiel'; ?>
                    <form method="post" action="<?php echo htmlspecialchars((string) ($urls['payments'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="download_receipt" />
                      <input type="hidden" name="payment_id" value="<?php echo htmlspecialchars((string) $paymentId, ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="document_type" value="<?php echo htmlspecialchars($documentType, ENT_QUOTES, 'UTF-8'); ?>" />
                      <button class="button-small" type="submit">Télécharger <?php echo htmlspecialchars($documentLabel, ENT_QUOTES, 'UTF-8'); ?></button>
                    </form>
                    <form method="post" action="<?php echo htmlspecialchars((string) ($urls['payments'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="email_receipt" />
                      <input type="hidden" name="payment_id" value="<?php echo htmlspecialchars((string) $paymentId, ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="document_type" value="<?php echo htmlspecialchars($documentType, ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="subject" value="<?php echo $documentType === 'receipt' ? 'Quittance de loyer' : 'Reçu partiel de loyer'; ?>" />
                      <label>Email document <input type="email" name="recipient_email" maxlength="190" /></label>
                      <button class="button-small" type="submit">Envoyer <?php echo htmlspecialchars($documentLabel, ENT_QUOTES, 'UTF-8'); ?></button>
                    </form>
                  <?php endif; ?>
                  <?php if (($payment['status'] ?? '') !== 'cancelled'): ?>
                    <form method="post" action="<?php echo htmlspecialchars((string) ($urls['payments'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="cancel_payment" />
                      <input type="hidden" name="payment_id" value="<?php echo htmlspecialchars((string) $paymentId, ENT_QUOTES, 'UTF-8'); ?>" />
                      <button class="button-small button-danger" type="submit">Annuler</button>
                    </form>
                  <?php endif; ?>
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
          <tr class="private-empty-row" data-private-filter-empty hidden><td colspan="9">Aucun paiement ne correspond aux filtres.</td></tr>
        </tbody>
      </table>
      <?php foreach ($payments as $payment): ?>
        <?php if (!is_array($payment) || !is_numeric($payment['id'] ?? null)) { continue; } ?>
        <?php $paymentId = (int) $payment['id']; ?>
        <dialog class="private-dialog" id="rental-payment-edit-dialog-<?php echo htmlspecialchars((string) $paymentId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="rental-payment-edit-dialog-<?php echo htmlspecialchars((string) $paymentId, ENT_QUOTES, 'UTF-8'); ?>-title">
          <div class="private-dialog-panel">
            <header class="private-dialog-header">
              <h3 id="rental-payment-edit-dialog-<?php echo htmlspecialchars((string) $paymentId, ENT_QUOTES, 'UTF-8'); ?>-title">Corriger un paiement</h3>
              <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
            </header>
            <form method="post" action="<?php echo htmlspecialchars((string) ($urls['payments'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="update_payment" />
              <input type="hidden" name="payment_id" value="<?php echo htmlspecialchars((string) $paymentId, ENT_QUOTES, 'UTF-8'); ?>" />
              <label>Date paiement <input type="date" name="payment_date" value="<?php echo htmlspecialchars((string) ($payment['paymentDate'] ?? date('Y-m-d')), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
              <label>Montant <input type="number" name="amount_paid" min="0.01" step="0.01" value="<?php echo htmlspecialchars(number_format((float) ($payment['amountPaid'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
              <label>Type
                <select name="payment_kind">
                  <?php foreach ($paymentKinds as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo (string) ($payment['paymentKind'] ?? 'tenant') === $value ? ' selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Mode
                <select name="payment_method">
                  <?php foreach ($paymentMethods as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo (string) ($payment['paymentMethod'] ?? '') === $value ? ' selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Référence <input type="text" name="payment_reference" maxlength="160" value="<?php echo htmlspecialchars((string) ($payment['paymentReference'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
              <label>Statut
                <select name="status">
                  <?php foreach ($paymentStatuses as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo (string) ($payment['status'] ?? '') === $value ? ' selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label><input type="checkbox" name="confirm_overpayment" value="1" /> Confirmer le surpaiement si le montant dépasse le solde du loyer</label>
              <label>Notes <textarea name="notes" maxlength="2000"><?php echo htmlspecialchars((string) ($payment['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea></label>
              <button type="submit">Enregistrer la correction</button>
            </form>
          </div>
        </dialog>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <dialog class="private-dialog" id="<?php echo htmlspecialchars($createDialogId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="<?php echo htmlspecialchars($createDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>" data-rental-payment-auto-open="<?php echo $prefillRentId > 0 ? '1' : '0'; ?>">
    <div class="private-dialog-panel">
      <header class="private-dialog-header">
        <h3 id="<?php echo htmlspecialchars($createDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">Créer un paiement</h3>
        <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
      </header>
      <?php if ($rents === []): ?>
        <p class="muted">Créer d'abord un loyer.</p>
      <?php else: ?>
        <form method="post" action="<?php echo htmlspecialchars((string) ($urls['payments'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-rental-payment-form>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
          <input type="hidden" name="action" value="create_payment" />
          <label>Loyer
            <select name="rental_rent_id" required data-rental-rent-select>
              <?php foreach ($rents as $rent): ?>
                <?php if (!is_array($rent) || !is_numeric($rent['id'] ?? null) || ($rent['status'] ?? '') === 'cancelled') { continue; } ?>
                <?php
                $amountDue = (float) ($rent['amountDue'] ?? 0);
                $amountPaid = (float) ($rent['amountPaid'] ?? 0);
                $balance = number_format(max(0.0, $amountDue - $amountPaid), 2, '.', '');
                $period = (string) ($rent['periodYear'] ?? '') . '-' . str_pad((string) ($rent['periodMonth'] ?? ''), 2, '0', STR_PAD_LEFT);
                $rentId = (int) $rent['id'];
                $dueDate = is_string($rent['dueDate'] ?? null) ? (string) $rent['dueDate'] : '';
                ?>
                <option value="<?php echo htmlspecialchars((string) $rentId, ENT_QUOTES, 'UTF-8'); ?>" data-balance="<?php echo htmlspecialchars($balance, ENT_QUOTES, 'UTF-8'); ?>" data-due-date="<?php echo htmlspecialchars($dueDate, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $prefillRentId === $rentId ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($period . ' - ' . (string) ($rent['propertyName'] ?? '') . ' - ' . (string) ($rent['unitLabel'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Date paiement <input type="date" name="payment_date" value="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" required data-rental-payment-date data-rental-payment-date-auto="1" /></label>
          <label>Montant <input type="number" name="amount_paid" min="0.01" step="0.01" required data-rental-amount-paid /></label>
          <label>Type
            <select name="payment_kind">
              <?php foreach ($paymentKinds as $value => $label): ?>
                <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Mode
            <select name="payment_method">
              <?php foreach ($paymentMethods as $value => $label): ?>
                <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Référence <input type="text" name="payment_reference" maxlength="160" /></label>
          <label>Statut
            <select name="status">
              <?php foreach ($paymentStatuses as $value => $label): ?>
                <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label><input type="checkbox" name="confirm_overpayment" value="1" /> Confirmer le surpaiement si le montant dépasse le solde du loyer</label>
          <label>Notes <textarea name="notes" maxlength="2000"></textarea></label>
          <button type="submit">Créer le paiement</button>
        </form>
      <?php endif; ?>
    </div>
  </dialog>
  <script>
    (() => {
      document.querySelectorAll('[data-rental-payment-form]').forEach((form) => {
        const rentSelect = form.querySelector('[data-rental-rent-select]');
        const amountPaid = form.querySelector('[data-rental-amount-paid]');
        const paymentDate = form.querySelector('[data-rental-payment-date]');
        if (!(rentSelect instanceof HTMLSelectElement) || !(amountPaid instanceof HTMLInputElement)) {
          return;
        }

        const fillAmountPaid = () => {
          const selectedOption = rentSelect.selectedOptions[0] ?? null;
          if (selectedOption instanceof HTMLOptionElement && (selectedOption.dataset.balance ?? '') !== '') {
            amountPaid.value = selectedOption.dataset.balance ?? '';
          }
          if (
            paymentDate instanceof HTMLInputElement
            && selectedOption instanceof HTMLOptionElement
            && (paymentDate.value === '' || paymentDate.dataset.rentalPaymentDateAuto === '1')
            && (selectedOption.dataset.dueDate ?? '') !== ''
          ) {
            paymentDate.value = selectedOption.dataset.dueDate ?? '';
            paymentDate.dataset.rentalPaymentDateAuto = '1';
          }
        };

        rentSelect.addEventListener('change', fillAmountPaid);
        if (paymentDate instanceof HTMLInputElement) {
          paymentDate.addEventListener('input', () => {
            paymentDate.dataset.rentalPaymentDateAuto = '0';
          });
        }
        fillAmountPaid();
      });

      const autoDialog = document.querySelector('[data-rental-payment-auto-open="1"]');
      if (autoDialog instanceof HTMLDialogElement && !autoDialog.open) {
        autoDialog.showModal();
      }
    })();
  </script>
</section>
