<?php
$members = is_array($privateMembers ?? null) ? $privateMembers : [];
$stats = is_array($privateMembersStats ?? null) ? $privateMembersStats : [];
$moduleRegistry = is_array($privateModuleRegistry ?? null) ? $privateModuleRegistry : [];
$statusFilter = is_string($statusFilter ?? null) ? $statusFilter : '';
$searchQuery = is_string($searchQuery ?? null) ? $searchQuery : '';
$membersUrl = is_string($adminPrivateMembersUrl ?? null) ? $adminPrivateMembersUrl : '#';
$privatePortalLoginUrl = is_string($adminPrivatePortalLoginUrl ?? null) && trim((string) $adminPrivatePortalLoginUrl) !== ''
    ? trim((string) $adminPrivatePortalLoginUrl)
    : (function_exists('private_portal_url') ? private_portal_url('login') : '#');
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';
$message = is_string($message ?? null) && trim((string) $message) !== '' ? (string) $message : null;
$error = is_string($error ?? null) && trim((string) $error) !== '' ? (string) $error : null;
$membersTotal = (int) ($stats['total'] ?? 0);
$activeMembers = (int) ($stats['active'] ?? 0);

$translate = static function (string $key, string $fallback): string {
    if (function_exists('admin_translate')) {
        return admin_translate($key, $fallback);
    }

    if (!function_exists('t')) {
        return $fallback;
    }

    $translated = t($key);
    if (!is_string($translated) || $translated === '' || $translated === '[[' . $key . ']]') {
        return $fallback;
    }

    return $translated;
};

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$statusLabels = [
    'invited' => $translate('TXT_ADMIN_PRIVATE_MEMBER_STATUS_INVITED', 'Invité'),
    'active' => $translate('TXT_ADMIN_PRIVATE_MEMBER_STATUS_ACTIVE', 'Actif'),
    'suspended' => $translate('TXT_ADMIN_PRIVATE_MEMBER_STATUS_SUSPENDED', 'Suspendu'),
    'disabled' => $translate('TXT_ADMIN_PRIVATE_MEMBER_STATUS_DISABLED', 'Désactivé'),
    'deleted' => $translate('TXT_ADMIN_PRIVATE_MEMBER_STATUS_DELETED', 'Anonymisé'),
];
?>

<?php if ($message !== null): ?>
  <div class="notice notice-success" role="status"><?php echo $escape($message); ?></div>
<?php endif; ?>

<?php if ($error !== null): ?>
  <div class="notice notice-error" role="alert"><?php echo $escape($error); ?></div>
<?php endif; ?>

<section class="card">
  <div class="admin-private-members-intro-header">
    <div>
      <span class="tag"><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_TAG', 'Espace privé')); ?></span>
      <h2><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_TITLE', 'Membres de l’espace privé')); ?></h2>
    </div>
    <a class="button-link admin-private-members-login-link" href="<?php echo $escape($privatePortalLoginUrl); ?>" target="_blank" rel="noopener noreferrer">
      <?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_LOGIN_LINK', 'Connexion à l’espace privé')); ?>
    </a>
  </div>
  <p class="notice notice-success">
    <?php
    echo $escape(
        sprintf(
            $translate('TXT_ADMIN_PRIVATE_MEMBERS_SUMMARY', 'Comptes membres actifs : %d'),
            $activeMembers
        )
    );
    ?>
  </p>

  <form method="POST" action="<?php echo $escape($membersUrl); ?>" class="filters-form" aria-label="<?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_INVITE_FORM', 'Inviter un membre privé')); ?>">
    <div class="inline-form">
      <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>" />
      <input type="hidden" name="private_member_action" value="invite" />
      <label for="private-member-email"><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_INVITE_EMAIL', 'Email à inviter')); ?></label>
      <input id="private-member-email" type="email" name="email" placeholder="<?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_SEARCH_PLACEHOLDER', 'adresse@email.fr')); ?>" required />
      <button type="submit"><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_INVITE_SUBMIT', 'Inviter')); ?></button>
    </div>
  </form>

  <form method="GET" action="<?php echo $escape($membersUrl); ?>" class="filters-form" aria-label="<?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_FILTERS', 'Filtres membres privés')); ?>">
    <div class="inline-form">
      <label for="member-status"><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_FILTER_STATUS', 'Filtrer le statut')); ?></label>
      <select id="member-status" name="status">
        <option value=""><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_FILTER_STATUS_ALL', 'Tous')); ?></option>
        <?php foreach (['invited', 'active', 'suspended', 'disabled', 'deleted'] as $statusOption): ?>
          <option value="<?php echo $escape($statusOption); ?>" <?php echo $statusOption === $statusFilter ? 'selected' : ''; ?>>
            <?php echo $escape($statusLabels[$statusOption] ?? $statusOption); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label for="member-search"><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_FILTER_SEARCH', 'Rechercher un email')); ?></label>
      <input id="member-search" type="search" name="q" value="<?php echo $escape($searchQuery); ?>" placeholder="<?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_SEARCH_PLACEHOLDER', 'adresse@email.fr')); ?>" />

      <button type="submit"><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_FILTER_APPLY', 'Filtrer')); ?></button>
      <a class="button-link button-link-muted" href="<?php echo $escape($membersUrl); ?>"><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_FILTER_RESET', 'Réinitialiser')); ?></a>
    </div>
  </form>
</section>

<section class="card">
  <h2><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MODULES_REGISTRY_TITLE', 'Registre des modules privés')); ?></h2>
  <?php if ($moduleRegistry === []): ?>
    <p class="notice-muted"><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MODULES_REGISTRY_EMPTY', 'Aucun module privé déclaré.')); ?></p>
  <?php else: ?>
    <ul>
      <?php foreach ($moduleRegistry as $module): ?>
        <?php
        $moduleName = is_string($module['name'] ?? null) ? (string) $module['name'] : (string) ($module['code'] ?? '');
        $moduleCode = is_string($module['code'] ?? null) ? (string) $module['code'] : '';
        $moduleActive = (bool) ($module['active'] ?? false);
        ?>
        <li>
          <strong><?php echo $escape($moduleName); ?></strong>
          <span class="notice-muted">
            <?php echo $escape($moduleCode); ?> -
            <?php echo $escape($moduleActive ? $translate('TXT_ADMIN_PRIVATE_MODULE_ACTIVE', 'actif') : $translate('TXT_ADMIN_PRIVATE_MODULE_INACTIVE', 'inactif')); ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="card admin-private-members-card">
  <h2><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_LIST_TITLE', 'Liste des comptes')); ?></h2>
  <p class="admin-private-members-count"><?php echo $escape(sprintf($translate('TXT_ADMIN_PRIVATE_MEMBERS_LIST_COUNT', 'Total : %d compte(s).'), $membersTotal)); ?></p>
  <div class="table-shell admin-private-members-table-shell">
    <table class="admin-table admin-private-members-table">
      <thead>
        <tr>
          <th><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_COL_EMAIL', 'Email')); ?></th>
          <th><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_COL_STATUS', 'Statut')); ?></th>
          <th><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_COL_MODULES', 'Modules')); ?></th>
          <th><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_COL_UPDATED', 'MAJ')); ?></th>
          <th><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_COL_LAST_LOGIN', 'Dernière connexion')); ?></th>
          <th><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_COL_ACTIONS', 'Actions')); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($members === []): ?>
          <tr>
            <td colspan="6"><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_EMPTY', 'Aucun membre correspondant.')); ?></td>
          </tr>
        <?php else: ?>
          <?php foreach ($members as $member): ?>
            <?php
            $memberId = is_numeric($member['id'] ?? null) ? (int) $member['id'] : 0;
            $statusValue = is_string($member['status'] ?? null) ? trim($member['status']) : '';
            $statusLabel = $statusLabels[$statusValue] ?? $statusValue;
            $moduleStates = is_array($member['moduleStates'] ?? null) ? $member['moduleStates'] : [];
            $isDeleted = $statusValue === 'deleted';
            $memberFragment = $memberId > 0 ? 'private-member-' . $memberId : '';
            ?>
            <tr<?php echo $memberFragment !== '' ? ' id="' . $escape($memberFragment) . '"' : ''; ?>>
              <td class="admin-private-members-email"><?php echo $escape((string) ($member['email'] ?? '-')); ?></td>
              <td>
                <span class="admin-private-members-status admin-private-members-status-<?php echo $escape($statusValue !== '' ? $statusValue : 'unknown'); ?>">
                  <?php echo $escape($statusLabel !== '' ? $statusLabel : $translate('TXT_ADMIN_PRIVATE_MEMBERS_STATUS_UNKNOWN', 'Inconnu')); ?>
                </span>
              </td>
              <td class="admin-private-members-modules">
                <form method="POST" action="<?php echo $escape($membersUrl); ?>" class="admin-private-members-modules-form">
                  <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>" />
                  <input type="hidden" name="private_member_action" value="modules" />
                  <input type="hidden" name="private_user_id" value="<?php echo $memberId; ?>" />
                  <?php if ($memberFragment !== ''): ?>
                    <input type="hidden" name="private_member_return_fragment" value="<?php echo $escape($memberFragment); ?>" />
                  <?php endif; ?>
                  <div class="admin-private-members-module-grid">
                    <?php foreach ($moduleStates as $module): ?>
                      <?php
                      $moduleCode = is_string($module['code'] ?? null) ? (string) $module['code'] : '';
                      if ($moduleCode === '') {
                          continue;
                      }
                      $moduleName = is_string($module['name'] ?? null) ? (string) $module['name'] : $moduleCode;
                      $moduleActive = (bool) ($module['active'] ?? false);
                      $moduleAssigned = (bool) ($module['assigned'] ?? false);
                      ?>
                      <label class="admin-private-members-module-option">
                        <input type="checkbox" name="modules[]" value="<?php echo $escape($moduleCode); ?>" <?php echo $moduleAssigned ? 'checked' : ''; ?> <?php echo ($isDeleted || !$moduleActive) ? 'disabled' : ''; ?> />
                        <span class="admin-private-members-module-label"><?php echo $escape($moduleName); ?></span>
                        <?php if (!$moduleActive): ?>
                          <span class="admin-private-members-module-state is-inactive">
                            <?php echo $escape($translate('TXT_ADMIN_PRIVATE_MODULE_INACTIVE', 'inactif')); ?>
                          </span>
                        <?php endif; ?>
                      </label>
                    <?php endforeach; ?>
                  </div>
                  <button class="button-small" type="submit" <?php echo $isDeleted ? 'disabled' : ''; ?>>
                    <?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_MODULES_SAVE', 'Enregistrer')); ?>
                  </button>
                </form>
              </td>
              <td class="admin-private-members-date"><?php echo $escape((string) ($member['updatedAt'] ?? '')); ?></td>
              <td class="admin-private-members-date"><?php echo $escape((string) ($member['lastLoginAt'] ?? '')); ?></td>
              <td class="admin-private-members-actions-cell">
                <div class="admin-private-members-actions">
                  <?php if ($statusValue === 'invited'): ?>
                    <form method="POST" action="<?php echo $escape($membersUrl); ?>">
                      <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>" />
                      <input type="hidden" name="private_member_action" value="resend" />
                      <input type="hidden" name="private_user_id" value="<?php echo $memberId; ?>" />
                      <?php if ($memberFragment !== ''): ?>
                        <input type="hidden" name="private_member_return_fragment" value="<?php echo $escape($memberFragment); ?>" />
                      <?php endif; ?>
                      <button class="button-small" type="submit"><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_ACTION_RESEND', 'Renvoyer')); ?></button>
                    </form>
                  <?php endif; ?>

                  <?php if (!$isDeleted && $statusValue !== 'suspended'): ?>
                    <form method="POST" action="<?php echo $escape($membersUrl); ?>">
                      <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>" />
                      <input type="hidden" name="private_member_action" value="suspend" />
                      <input type="hidden" name="private_user_id" value="<?php echo $memberId; ?>" />
                      <?php if ($memberFragment !== ''): ?>
                        <input type="hidden" name="private_member_return_fragment" value="<?php echo $escape($memberFragment); ?>" />
                      <?php endif; ?>
                      <button class="button-small button-muted" type="submit"><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_ACTION_SUSPEND', 'Suspendre')); ?></button>
                    </form>
                  <?php endif; ?>

                  <?php if (!$isDeleted): ?>
                    <form method="POST" action="<?php echo $escape($membersUrl); ?>">
                      <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>" />
                      <input type="hidden" name="private_member_action" value="reset" />
                      <input type="hidden" name="private_user_id" value="<?php echo $memberId; ?>" />
                      <?php if ($memberFragment !== ''): ?>
                        <input type="hidden" name="private_member_return_fragment" value="<?php echo $escape($memberFragment); ?>" />
                      <?php endif; ?>
                      <button class="button-small button-muted" type="submit"><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_ACTION_RESET', 'Reset')); ?></button>
                    </form>

                    <form method="POST" action="<?php echo $escape($membersUrl); ?>">
                      <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>" />
                      <input type="hidden" name="private_member_action" value="anonymize" />
                      <input type="hidden" name="private_user_id" value="<?php echo $memberId; ?>" />
                      <?php if ($memberFragment !== ''): ?>
                        <input type="hidden" name="private_member_return_fragment" value="<?php echo $escape($memberFragment); ?>" />
                      <?php endif; ?>
                      <button class="button-small button-danger" type="submit"><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_ACTION_ANONYMIZE', 'Anonymiser')); ?></button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
