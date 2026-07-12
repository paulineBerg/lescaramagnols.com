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

  <?php if ($notice !== '') : ?>
    <p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
  <?php if ($error !== '') : ?>
    <p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <section class="card private-list-section" data-private-filter-scope>
    <div class="private-list-header">
      <div>
        <h2>Bailleurs</h2>
        <p class="muted">Personnes ou entités rattachées aux propriétés locatives.</p>
      </div>
      <button type="button" class="private-create-button" data-private-dialog-open="<?php echo htmlspecialchars($createDialogId, ENT_QUOTES, 'UTF-8'); ?>">Créer un bailleur</button>
    </div>

    <?php if ($lessors === []) : ?>
      <p class="muted">Aucun bailleur enregistré.</p>
    <?php else : ?>
      <div class="private-list-tools">
        <div class="private-list-filter-grid">
          <label>Recherche
            <input type="search" placeholder="Nom, prénom, email" data-private-filter="text" />
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
              <th>Nom</th>
              <th>Prénom</th>
              <th>Adresse</th>
              <th>Téléphone</th>
              <th>Email</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($lessors as $lessor) : ?>
                <?php if (!is_array($lessor) || !is_numeric($lessor['id'] ?? null)) {
                    continue;
                } ?>
                <?php
                $id = (int) $lessor['id'];
                $dialogId = 'rental-lessor-dialog-' . $id;
                $filterText = strtolower(trim(
                    (string) ($lessor['lastName'] ?? '') . ' '
                    . (string) ($lessor['firstName'] ?? '') . ' '
                    . (string) ($lessor['address'] ?? '') . ' '
                    . (string) ($lessor['phone'] ?? '') . ' '
                    . (string) ($lessor['email'] ?? '')
                ));
                ?>
              <tr data-private-filter-row data-filter-text="<?php echo htmlspecialchars($filterText, ENT_QUOTES, 'UTF-8'); ?>">
                <td><strong><?php echo htmlspecialchars((string) ($lessor['lastName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                <td><?php echo htmlspecialchars((string) ($lessor['firstName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) ($lessor['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) ($lessor['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) ($lessor['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                  <button type="button" class="private-row-action" data-private-dialog-open="<?php echo htmlspecialchars($dialogId, ENT_QUOTES, 'UTF-8'); ?>">Modifier</button>
                </td>
              </tr>
            <?php endforeach; ?>
            <tr class="private-empty-row" data-private-filter-empty hidden><td colspan="6">Aucun bailleur ne correspond aux filtres.</td></tr>
          </tbody>
        </table>
      </div>

        <?php foreach ($lessors as $lessor) : ?>
            <?php if (!is_array($lessor) || !is_numeric($lessor['id'] ?? null)) {
                continue;
            } ?>
            <?php
            $id = (int) $lessor['id'];
            $dialogId = 'rental-lessor-dialog-' . $id;
            ?>
        <dialog class="private-dialog" id="<?php echo htmlspecialchars($dialogId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="<?php echo htmlspecialchars($dialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">
          <div class="private-dialog-panel">
            <header class="private-dialog-header">
              <h3 id="<?php echo htmlspecialchars($dialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">Modifier le bailleur</h3>
              <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
            </header>
            <form method="post" action="<?php echo htmlspecialchars($lessorsUrl, ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="update_lessor" />
              <input type="hidden" name="lessor_id" value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>" />
              <label>Nom <input type="text" name="last_name" maxlength="120" value="<?php echo htmlspecialchars((string) ($lessor['lastName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required /></label>
              <label>Prénom <input type="text" name="first_name" maxlength="120" value="<?php echo htmlspecialchars((string) ($lessor['firstName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
              <label>Adresse <input type="text" name="address" maxlength="255" value="<?php echo htmlspecialchars((string) ($lessor['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
              <label>Téléphone <input type="tel" name="phone" maxlength="64" value="<?php echo htmlspecialchars((string) ($lessor['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
              <label>Email <input type="email" name="email" maxlength="190" value="<?php echo htmlspecialchars((string) ($lessor['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></label>
              <button type="submit">Mettre à jour</button>
            </form>
            <form method="post" action="<?php echo htmlspecialchars($lessorsUrl, ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="delete_lessor" />
              <input type="hidden" name="lessor_id" value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>" />
              <button type="submit" class="private-button-danger">Supprimer</button>
            </form>
          </div>
        </dialog>
        <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <dialog class="private-dialog" id="<?php echo htmlspecialchars($createDialogId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="<?php echo htmlspecialchars($createDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">
    <div class="private-dialog-panel">
      <header class="private-dialog-header">
        <h3 id="<?php echo htmlspecialchars($createDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">Créer un bailleur</h3>
        <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
      </header>
      <form method="post" action="<?php echo htmlspecialchars($lessorsUrl, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="action" value="create_lessor" />
        <label>Nom <input type="text" name="last_name" maxlength="120" required /></label>
        <label>Prénom <input type="text" name="first_name" maxlength="120" /></label>
        <label>Adresse <input type="text" name="address" maxlength="255" /></label>
        <label>Téléphone <input type="tel" name="phone" maxlength="64" /></label>
        <label>Email <input type="email" name="email" maxlength="190" /></label>
        <button type="submit">Créer le bailleur</button>
      </form>
    </div>
  </dialog>
</section>
