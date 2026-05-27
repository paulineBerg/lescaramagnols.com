<?php
$properties = is_array($viewModel['rentalProperties'] ?? null) ? $viewModel['rentalProperties'] : [];
$members = is_array($viewModel['rentalMembers'] ?? null) ? $viewModel['rentalMembers'] : [];
$csrfToken = is_string($viewModel['rentalCsrfToken'] ?? null) ? (string) $viewModel['rentalCsrfToken'] : '';
$notice = is_string($viewModel['rentalNotice'] ?? null) ? (string) $viewModel['rentalNotice'] : '';
$error = is_string($viewModel['rentalError'] ?? null) ? (string) $viewModel['rentalError'] : '';
$propertiesUrl = is_string($viewModel['rentalPropertiesUrl'] ?? null) ? (string) $viewModel['rentalPropertiesUrl'] : private_portal_url('rental_properties');
$unitsUrl = is_string($viewModel['rentalUnitsUrl'] ?? null) ? (string) $viewModel['rentalUnitsUrl'] : private_portal_url('rental_units');
$membersUrl = is_string($viewModel['rentalMembersUrl'] ?? null) ? (string) $viewModel['rentalMembersUrl'] : private_portal_url('rental_property_members');
$roles = ['owner' => 'Propriétaire', 'co_owner' => 'Copropriétaire', 'manager' => 'Gestionnaire', 'occupant' => 'Occupant'];
$propertyNames = [];
foreach ($properties as $property) {
    if (is_array($property) && is_numeric($property['id'] ?? null)) {
        $propertyNames[(int) $property['id']] = (string) ($property['name'] ?? ('Bien #' . (int) $property['id']));
    }
}
?>
<section>
  <p class="muted">
    <a href="<?php echo htmlspecialchars($propertiesUrl, ENT_QUOTES, 'UTF-8'); ?>">Biens</a>
    · <a href="<?php echo htmlspecialchars($unitsUrl, ENT_QUOTES, 'UTF-8'); ?>">Lots</a>
    · <a href="<?php echo htmlspecialchars($membersUrl, ENT_QUOTES, 'UTF-8'); ?>">Membres</a>
  </p>

  <?php if ($notice !== ''): ?>
    <p class="notice notice-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <p class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <section class="card">
    <h2>Ajouter un membre sur un bien</h2>
    <?php if ($properties === []): ?>
      <p class="muted">Aucun bien locatif disponible pour affecter un membre.</p>
    <?php else: ?>
      <form method="post" action="<?php echo htmlspecialchars($membersUrl, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="action" value="create_member" />
        <label>Bien
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
  </section>

  <section class="card">
    <h2>Membres autorisés</h2>
    <?php if ($members === []): ?>
      <p class="muted">Aucun membre locatif actif.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Bien</th>
            <th>Compte</th>
            <th>Rôle</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($members as $member): ?>
            <?php if (!is_array($member)) {
                continue;
            } ?>
            <tr>
              <td><?php echo htmlspecialchars((string) ($member['propertyName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($member['privateUserEmail'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($roles[(string) ($member['role'] ?? '')] ?? (string) ($member['role'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($member['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</section>
