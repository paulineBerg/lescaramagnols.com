<?php
$properties = is_array($viewModel['rentalProperties'] ?? null) ? $viewModel['rentalProperties'] : [];
$units = is_array($viewModel['rentalUnits'] ?? null) ? $viewModel['rentalUnits'] : [];
$regularizations = is_array($viewModel['rentalChargeRegularizations'] ?? null) ? $viewModel['rentalChargeRegularizations'] : [];
$preview = is_array($viewModel['rentalChargeRegularizationPreview'] ?? null) ? $viewModel['rentalChargeRegularizationPreview'] : null;
$csrfToken = is_string($viewModel['rentalCsrfToken'] ?? null) ? (string) $viewModel['rentalCsrfToken'] : '';
$notice = is_string($viewModel['rentalNotice'] ?? null) ? (string) $viewModel['rentalNotice'] : '';
$error = is_string($viewModel['rentalError'] ?? null) ? (string) $viewModel['rentalError'] : '';
$urls = is_array($viewModel['rentalUrls'] ?? null) ? $viewModel['rentalUrls'] : [];
$regularizationsUrl = (string) ($urls['regularizations'] ?? private_portal_url('rental_regularizations'));
$propertyNames = [];
foreach ($properties as $property) {
    if (is_array($property) && is_numeric($property['id'] ?? null)) {
        $propertyNames[(int) $property['id']] = (string) ($property['name'] ?? ('Propriété #' . (int) $property['id']));
    }
}
$money = static fn (mixed $value): string => number_format(is_numeric($value) ? (float) $value : 0.0, 2, ',', ' ');
$directionLabel = static function (string $direction): string {
    return match ($direction) {
        'tenant_due' => 'Solde à demander',
        'tenant_refund' => 'Remboursement locataire',
        default => 'Solde nul',
    };
};
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>
  <?php if ($notice !== ''): ?><p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <section class="card private-list-section">
    <div class="private-list-header">
      <div>
        <h2>Régularisations</h2>
        <p class="muted">Calcul annuel des provisions, charges récupérables et soldes.</p>
      </div>
    </div>
    <?php if ($propertyNames === []): ?>
      <p class="muted">Créer d'abord une propriété autorisée.</p>
    <?php else: ?>
      <form method="post" action="<?php echo htmlspecialchars($regularizationsUrl, ENT_QUOTES, 'UTF-8'); ?>" data-rental-regularization-form>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
        <div class="private-list-filter-grid">
          <label>Propriété
            <select name="rental_property_id" required>
              <?php foreach ($propertyNames as $id => $name): ?>
                <option value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Bien locatif
            <select name="rental_unit_id" data-rental-regularization-unit-select>
              <option value="">Propriété entière</option>
              <?php foreach ($units as $unit): ?>
                <?php if (!is_array($unit) || !is_numeric($unit['id'] ?? null)) { continue; } ?>
                <?php $unitPropertyId = is_numeric($unit['rentalPropertyId'] ?? null) ? (int) $unit['rentalPropertyId'] : 0; ?>
                <option value="<?php echo htmlspecialchars((string) (int) $unit['id'], ENT_QUOTES, 'UTF-8'); ?>" data-property-id="<?php echo htmlspecialchars((string) $unitPropertyId, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($unit['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Année <input type="number" name="year" min="2000" max="2100" value="<?php echo htmlspecialchars((string) date('Y'), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
          <label>Part locataire (%) <input type="number" name="tenant_share_percent" min="0" max="100" step="0.01" value="100" required /></label>
          <div class="private-list-filter-actions">
            <button type="submit" name="action" value="preview_regularization" class="private-button-secondary">Calculer</button>
            <button type="submit" name="action" value="generate_regularization">Générer le PDF</button>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </section>

  <?php if ($preview !== null): ?>
    <section class="card">
      <h2>Calcul provisoire</h2>
      <dl>
        <dt>Provisions demandées</dt><dd><?php echo htmlspecialchars($money($preview['provisionsAmount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?> €</dd>
        <dt>Charges récupérables réelles</dt><dd><?php echo htmlspecialchars($money($preview['recoverableExpensesAmount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?> €</dd>
        <dt>Part locataire</dt><dd><?php echo htmlspecialchars($money($preview['tenantSharePercent'] ?? 0), ENT_QUOTES, 'UTF-8'); ?> %</dd>
        <dt>Part récupérable locataire</dt><dd><?php echo htmlspecialchars($money($preview['tenantRecoverableAmount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?> €</dd>
        <dt>Solde</dt><dd><?php echo htmlspecialchars($money($preview['balanceAmount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?> € - <?php echo htmlspecialchars($directionLabel((string) ($preview['balanceDirection'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></dd>
      </dl>
      <?php $expenseRows = is_array($preview['expenseRows'] ?? null) ? $preview['expenseRows'] : []; ?>
      <?php if ($expenseRows !== []): ?>
        <div class="private-table-wrap">
          <table>
            <thead><tr><th>Date</th><th>Catégorie</th><th>Libellé</th><th>Montant</th><th>Justificatifs</th></tr></thead>
            <tbody>
              <?php foreach ($expenseRows as $expense): ?>
                <?php if (!is_array($expense) || empty($expense['recoverable'])) { continue; } ?>
                <?php $supportingDocuments = is_array($expense['supportingDocuments'] ?? null) ? $expense['supportingDocuments'] : []; ?>
                <tr>
                  <td><?php echo htmlspecialchars((string) ($expense['date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string) ($expense['categoryLabel'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string) ($expense['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars($money($expense['amount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?> €</td>
                  <td><?php echo htmlspecialchars((string) count($supportingDocuments), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <section class="card private-list-section">
    <div class="private-list-header">
      <div>
        <h2>Documents générés</h2>
        <p class="muted">Snapshots conservés avec empreinte SHA-256.</p>
      </div>
    </div>
    <?php if ($regularizations === []): ?>
      <p class="muted">Aucune régularisation générée.</p>
    <?php else: ?>
      <div class="private-table-wrap">
        <table>
          <thead><tr><th>Année</th><th>Propriété</th><th>Bien</th><th>Provisions</th><th>Charges</th><th>Solde</th><th>Document</th></tr></thead>
          <tbody>
            <?php foreach ($regularizations as $regularization): ?>
              <?php if (!is_array($regularization)) { continue; } ?>
              <?php $documentId = is_string($regularization['documentId'] ?? null) ? (string) $regularization['documentId'] : ''; ?>
              <tr>
                <td><?php echo htmlspecialchars((string) (int) ($regularization['periodYear'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) ($regularization['propertyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) (($regularization['unitLabel'] ?? '') ?: 'Propriété entière'), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($money($regularization['provisionsAmount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?> €</td>
                <td><?php echo htmlspecialchars($money($regularization['tenantRecoverableAmount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?> €</td>
                <td><?php echo htmlspecialchars($money($regularization['balanceAmount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?> €</td>
                <td>
                  <?php if ($documentId !== ''): ?>
                    <a href="<?php echo htmlspecialchars(rtrim($regularizationsUrl, '/') . '/' . rawurlencode($documentId), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($regularization['originalName'] ?? $documentId), ENT_QUOTES, 'UTF-8'); ?></a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
  <script>
    (() => {
      document.querySelectorAll('[data-rental-regularization-form]').forEach((form) => {
        const propertySelect = form.querySelector('[name="rental_property_id"]');
        const unitSelect = form.querySelector('[data-rental-regularization-unit-select]');
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
