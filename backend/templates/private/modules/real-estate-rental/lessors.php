<?php
$lessors = is_array($viewModel['rentalLessors'] ?? null) ? $viewModel['rentalLessors'] : [];
$csrfToken = is_string($viewModel['rentalCsrfToken'] ?? null) ? (string) $viewModel['rentalCsrfToken'] : '';
$notice = is_string($viewModel['rentalNotice'] ?? null) ? (string) $viewModel['rentalNotice'] : '';
$error = is_string($viewModel['rentalError'] ?? null) ? (string) $viewModel['rentalError'] : '';
$urls = is_array($viewModel['rentalUrls'] ?? null) ? $viewModel['rentalUrls'] : [];
$lessorsUrl = is_string($urls['lessors'] ?? null) ? (string) $urls['lessors'] : private_portal_url('rental_lessors');
$createDialogId = 'rental-lessor-create-dialog';
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>

  <?php if ($notice !== ''): ?><p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

  <section class="card private-list-section" data-private-filter-scope>
    <div class="private-list-header">
      <div>
        <h2>Bailleurs</h2>
        <p class="muted">Coordonnées utilisées pour rattacher les propriétés locatives.</p>
      </div>
      <button type="button" class="private-create-button" data-private-dialog-open="<?php echo htmlspecialchars($createDialogId, ENT_QUOTES, 'UTF-8'); ?>">Créer un bailleur</button>
    </div>

    <?php if ($lessors === []): ?>
      <p class="muted">Aucun bailleur enregistré.</p>
    <?php else: ?>
      <div class="private-list-tools">
        <div class="private-list-filter-grid">
          <label>Recherche <input type="search" placeholder="Nom, email, téléphone" data-private-filter="text" /></label>
          <div class="private-list-filter-actions">
            <button type="button" class="private-button-secondary" data-private-filter-reset>Réinitialiser</button>
          </div>
        </div>
      </div>
      <div class="private-table-wrap">
        <table>
          <thead><tr><th>Nom</th><th>Contact</th><th>Adresse</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($lessors as $lessor): ?>
              <?php if (!is_array($lessor) || !is_numeric($lessor['id'] ?? null)) { continue; } ?>
              <?php
              $id = (int) $lessor['id'];
              $dialogId = 'rental-lessor-dialog-' . $id;
              $fullName = (string) (($lessor['fullName'] ?? '') ?: trim((string) ($lessor['firstName'] ?? '') . ' ' . (string) ($lessor['lastName'] ?? '')));
              ?>
              <tr data-private-filter-row data-filter-text="<?php echo htmlspecialchars(strtolower(trim($fullName . ' ' . (string) ($lessor['email'] ?? '') . ' ' . (string) ($lessor['phone'] ?? '') . ' ' . (string) ($lessor['address'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?>">
                <td><strong><?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></strong></td>
                <td><?php echo htmlspecialchars(trim((string) ($lessor['email'] ?? '') . ' ' . (string) ($lessor['phone'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) ($lessor['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><button type="button" class="private-row-action" data-private-dialog-open="<?php echo htmlspecialchars($dialogId, ENT_QUOTES, 'UTF-8'); ?>">Modifier</button></td>
              </tr>
            <?php endforeach; ?>
            <tr class="private-empty-row" data-private-filter-empty hidden><td colspan="4">Aucun bailleur ne correspond aux filtres.</td></tr>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <?php
  $form = static function (array $lessor, string $action, string $dialogId, string $title) use ($csrfToken, $lessorsUrl): void {
      $id = is_numeric($lessor['id'] ?? null) ? (int) $lessor['id'] : 0;
      ?>
      <dialog class="private-dialog" id="<?php echo htmlspecialchars($dialogId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="<?php echo htmlspecialchars($dialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">
        <div class="private-dialog-panel">
          <header class="private-dialog-header">
            <h3 id="<?php echo htmlspecialchars($dialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h3>
            <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
          </header>
          <form method="post" action="<?php echo htmlspecialchars($lessorsUrl, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
            <input type="hidden" name="action" value="<?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>" />
            <?php if ($id > 0): ?><input type="hidden" name="lessor_id" value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>" /><?php endif; ?>
            <label>Nom <input type="text" name="last_name" maxlength="120" value="<?php echo htmlspecialchars((string) ($lessor['lastName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
            <label>Prénom <input type="text" name="first_name" maxlength="120" value="<?php echo htmlspecialchars((string) ($lessor['firstName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
            <label>Adresse <textarea name="address" maxlength="500"><?php echo htmlspecialchars((string) ($lessor['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea></label>
            <label>Téléphone <input type="tel" name="phone" maxlength="80" value="<?php echo htmlspecialchars((string) ($lessor['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
            <label>Email <input type="email" name="email" maxlength="254" value="<?php echo htmlspecialchars((string) ($lessor['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
            <button type="submit"><?php echo $action === 'update_lessor' ? 'Enregistrer' : 'Créer'; ?></button>
          </form>
          <?php if ($id > 0): ?>
            <form method="post" action="<?php echo htmlspecialchars($lessorsUrl, ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="delete_lessor" />
              <input type="hidden" name="lessor_id" value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>" />
              <button class="private-button-danger" type="submit">Supprimer</button>
            </form>
            <p class="muted">La suppression n'est possible que si ce bailleur n'est rattaché à aucune propriété active.</p>
          <?php endif; ?>
        </div>
      </dialog>
      <?php
  };
  $form([], 'create_lessor', $createDialogId, 'Créer un bailleur');
  foreach ($lessors as $lessor) {
      if (is_array($lessor) && is_numeric($lessor['id'] ?? null)) {
          $form($lessor, 'update_lessor', 'rental-lessor-dialog-' . (int) $lessor['id'], 'Modifier le bailleur');
      }
  }
  ?>
</section>
