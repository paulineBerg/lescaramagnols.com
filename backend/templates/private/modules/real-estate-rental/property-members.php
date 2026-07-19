<?php
$properties = is_array($viewModel['rentalProperties'] ?? null) ? $viewModel['rentalProperties'] : [];
$members = is_array($viewModel['rentalMembers'] ?? null) ? $viewModel['rentalMembers'] : [];
$csrfToken = is_string($viewModel['rentalCsrfToken'] ?? null) ? (string) $viewModel['rentalCsrfToken'] : '';
$currentPrivateUserId = is_numeric($viewModel['rentalCurrentPrivateUserId'] ?? null) ? (int) $viewModel['rentalCurrentPrivateUserId'] : 0;
$notice = is_string($viewModel['rentalNotice'] ?? null) ? (string) $viewModel['rentalNotice'] : '';
$error = is_string($viewModel['rentalError'] ?? null) ? (string) $viewModel['rentalError'] : '';
$urls = is_array($viewModel['rentalUrls'] ?? null) ? $viewModel['rentalUrls'] : [];
$membersUrl = is_string($urls['members'] ?? null) ? (string) $urls['members'] : private_portal_url('rental_property_members');
$roles = ['owner' => 'Propriétaire', 'co_owner' => 'Copropriétaire', 'manager' => 'Gestionnaire', 'occupant' => 'Occupant'];
$createDialogId = 'rental-member-create-dialog';
$propertyNames = [];
foreach ($properties as $property) {
    if (is_array($property) && is_numeric($property['id'] ?? null)) {
        $propertyNames[(int) $property['id']] = (string) ($property['name'] ?? ('Propriété #' . (int) $property['id']));
    }
}
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>

  <?php if ($notice !== ''): ?>
    <p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <section class="card private-list-section" data-private-filter-scope>
    <div class="private-list-header">
      <div>
        <h2>Membres autorisés</h2>
        <p class="muted">Accès par propriété et par rôle.</p>
      </div>
      <button type="button" class="private-create-button" data-private-dialog-open="<?php echo htmlspecialchars($createDialogId, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $properties === [] ? ' disabled' : ''; ?>>Ajouter un membre</button>
    </div>
    <?php if ($properties === []): ?>
      <p class="muted">Aucune propriété disponible pour affecter un membre.</p>
    <?php endif; ?>
    <?php if ($members === []): ?>
      <p class="muted">Aucun membre locatif actif.</p>
    <?php else: ?>
      <div class="private-list-tools">
        <div class="private-list-filter-grid">
          <label>Recherche <input type="search" placeholder="Propriété ou compte" data-private-filter="text" /></label>
          <label>Rôle
            <select data-private-filter="role">
              <option value="all">Tous</option>
              <?php foreach ($roles as $value => $label): ?>
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
        <thead>
          <tr>
            <th>Propriété</th>
            <th>Compte</th>
            <th>Rôle</th>
            <th>Statut</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($members as $member): ?>
            <?php
            if (!is_array($member)) {
                continue;
            }
            $memberId = is_numeric($member['id'] ?? null) ? (int) $member['id'] : 0;
            $memberPrivateUserId = is_numeric($member['privateUserId'] ?? null) ? (int) $member['privateUserId'] : 0;
            $role = is_string($member['role'] ?? null) ? (string) $member['role'] : '';
            $isCurrentUser = $memberPrivateUserId > 0 && $memberPrivateUserId === $currentPrivateUserId;
            $dialogId = 'rental-member-dialog-' . $memberId;
            ?>
            <tr data-private-filter-row data-filter-text="<?php echo htmlspecialchars(strtolower(trim((string) ($member['propertyName'] ?? '') . ' ' . (string) ($member['privateUserEmail'] ?? '') . ' ' . (string) ($member['status'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?>" data-filter-role="<?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>">
              <td><?php echo htmlspecialchars((string) ($member['propertyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($member['privateUserEmail'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($roles[$role] ?? $role, ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($member['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php if ($isCurrentUser): ?>
                  <span class="muted">Compte connecté - protégé</span>
                <?php elseif ($memberId > 0): ?>
                  <button type="button" class="private-row-action" data-private-dialog-open="<?php echo htmlspecialchars($dialogId, ENT_QUOTES, 'UTF-8'); ?>">Modifier</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <tr class="private-empty-row" data-private-filter-empty hidden><td colspan="5">Aucun membre ne correspond aux filtres.</td></tr>
        </tbody>
      </table>
      </div>

      <?php foreach ($members as $member): ?>
        <?php
        if (!is_array($member)) {
            continue;
        }
        $memberId = is_numeric($member['id'] ?? null) ? (int) $member['id'] : 0;
        $memberPrivateUserId = is_numeric($member['privateUserId'] ?? null) ? (int) $member['privateUserId'] : 0;
        if ($memberId <= 0 || ($memberPrivateUserId > 0 && $memberPrivateUserId === $currentPrivateUserId)) {
            continue;
        }
        $role = is_string($member['role'] ?? null) ? (string) $member['role'] : '';
        $dialogId = 'rental-member-dialog-' . $memberId;
        ?>
        <dialog class="private-dialog" id="<?php echo htmlspecialchars($dialogId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="<?php echo htmlspecialchars($dialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">
          <div class="private-dialog-panel">
            <header class="private-dialog-header">
              <h3 id="<?php echo htmlspecialchars($dialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">Modifier l’accès</h3>
              <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
            </header>
            <p class="muted">
              <?php echo htmlspecialchars((string) ($member['privateUserEmail'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
              · <?php echo htmlspecialchars((string) ($member['propertyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
            </p>
            <form method="post" action="<?php echo htmlspecialchars($membersUrl, ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="update_member" />
              <input type="hidden" name="member_id" value="<?php echo htmlspecialchars((string) $memberId, ENT_QUOTES, 'UTF-8'); ?>" />
              <label>Rôle
                <select name="role">
                  <?php foreach ($roles as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $role === $value ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Notes <textarea name="notes" maxlength="2000"><?php echo htmlspecialchars((string) ($member['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea></label>
              <button type="submit">Mettre à jour</button>
            </form>
            <form method="post" action="<?php echo htmlspecialchars($membersUrl, ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="delete_member" />
              <input type="hidden" name="member_id" value="<?php echo htmlspecialchars((string) $memberId, ENT_QUOTES, 'UTF-8'); ?>" />
              <button type="submit" class="private-button-danger">Supprimer l’accès</button>
            </form>
          </div>
        </dialog>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <dialog class="private-dialog" id="<?php echo htmlspecialchars($createDialogId, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="<?php echo htmlspecialchars($createDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">
    <div class="private-dialog-panel">
      <header class="private-dialog-header">
        <h3 id="<?php echo htmlspecialchars($createDialogId . '-title', ENT_QUOTES, 'UTF-8'); ?>">Ajouter un membre sur une propriété</h3>
        <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
      </header>
      <?php if ($properties === []): ?>
        <p class="muted">Aucune propriété disponible pour affecter un membre.</p>
      <?php else: ?>
        <form method="post" action="<?php echo htmlspecialchars($membersUrl, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
          <input type="hidden" name="action" value="create_member" />
          <label>Propriété
            <select name="rental_property_id" required>
              <?php foreach ($propertyNames as $id => $name): ?>
                <option value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Email du compte privé <input type="email" name="private_user_email" maxlength="254" required /></label>
          <label>Rôle
            <select name="role">
              <?php foreach ($roles as $value => $label): ?>
                <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Notes <textarea name="notes" maxlength="2000"></textarea></label>
          <button type="submit">Ajouter le membre</button>
        </form>
      <?php endif; ?>
    </div>
  </dialog>
</section>
