<?php
$members = is_array($privateMembers ?? null) ? $privateMembers : [];
$memberEmailChoices = is_array($privateMemberEmailChoices ?? null) ? array_values(array_filter($privateMemberEmailChoices, 'is_string')) : [];
$stats = is_array($privateMembersStats ?? null) ? $privateMembersStats : [];
$moduleRegistry = is_array($privateModuleRegistry ?? null) ? $privateModuleRegistry : [];
$privateMail = is_array($privateMail ?? null) ? $privateMail : [];
$privateSecurity = is_array($privateSecurity ?? null) ? $privateSecurity : [];
$privateMailTemplates = is_array($privateMail['templates'] ?? null) ? $privateMail['templates'] : [];
$privateMailTemplateCatalog = is_array($privateMail['templateCatalog'] ?? null) ? $privateMail['templateCatalog'] : [];
$privateMailCommonVariables = is_array($privateMail['commonVariables'] ?? null) ? array_values(array_filter($privateMail['commonVariables'], 'is_string')) : [];
$privateMailPreviews = is_array($privateMail['previews'] ?? null) ? $privateMail['previews'] : [];
$statusFilter = is_string($statusFilter ?? null) ? $statusFilter : '';
$searchQuery = is_string($searchQuery ?? null) ? $searchQuery : '';
$activeTab = is_string($privateMembersActiveTab ?? null) ? (string) $privateMembersActiveTab : 'members';
if (!in_array($activeTab, ['members', 'email'], true)) {
    $activeTab = 'members';
}
$membersUrl = is_string($adminPrivateMembersUrl ?? null) ? $adminPrivateMembersUrl : '#';
$membersTabUrl = $membersUrl;
$emailTabUrl = $membersUrl . (str_contains($membersUrl, '?') ? '&' : '?') . 'tab=email';
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
$renderSecretToggleButton = static function (string $inputId) use ($translate, $escape): string {
    $showLabel = $escape($translate('TXT_ADMIN_PASSWORD_SHOW', 'Afficher'));
    $hideLabel = $escape($translate('TXT_ADMIN_PASSWORD_HIDE', 'Masquer'));
    $safeInputId = $escape($inputId);

    return sprintf(
        '<button class="admin-password-toggle" type="button" data-admin-password-toggle data-admin-password-show="%s" data-admin-password-hide="%s" aria-controls="%s" aria-pressed="false">%s</button>',
        $showLabel,
        $hideLabel,
        $safeInputId,
        $showLabel
    );
};
$secretMaskAttribute = static fn (bool $configured): string => $configured ? ' data-admin-secret-mask="true"' : '';

$statusLabels = [
    'invited' => $translate('TXT_ADMIN_PRIVATE_MEMBER_STATUS_INVITED', 'Invité'),
    'active' => $translate('TXT_ADMIN_PRIVATE_MEMBER_STATUS_ACTIVE', 'Actif'),
    'suspended' => $translate('TXT_ADMIN_PRIVATE_MEMBER_STATUS_SUSPENDED', 'Suspendu'),
    'disabled' => $translate('TXT_ADMIN_PRIVATE_MEMBER_STATUS_DISABLED', 'Désactivé'),
];
?>

<?php if ($message !== null): ?>
  <div class="notice notice-success" role="status"><?php echo $escape($message); ?></div>
<?php endif; ?>

<?php if ($error !== null): ?>
  <div class="notice notice-error" role="alert"><?php echo $escape($error); ?></div>
<?php endif; ?>

<nav class="menu-builder-tabs admin-private-members-tabs" role="tablist" aria-label="<?php echo $escape($translate('TXT_ADMIN_PRIVATE_TABS_LABEL', 'Sections de l’espace privé')); ?>">
  <a
    class="menu-builder-tab<?php echo $activeTab === 'members' ? ' menu-builder-tab-active' : ''; ?>"
    href="<?php echo $escape($membersTabUrl); ?>"
    role="tab"
    aria-selected="<?php echo $activeTab === 'members' ? 'true' : 'false'; ?>"
  >
    <strong><?php echo $escape($translate('TXT_ADMIN_PRIVATE_TAB_MEMBERS', 'Membres')); ?></strong>
    <small><?php echo $escape($translate('TXT_ADMIN_PRIVATE_TAB_MEMBERS_HELP', 'Comptes, modules et accès')); ?></small>
  </a>
  <a
    class="menu-builder-tab<?php echo $activeTab === 'email' ? ' menu-builder-tab-active' : ''; ?>"
    href="<?php echo $escape($emailTabUrl); ?>"
    role="tab"
    aria-selected="<?php echo $activeTab === 'email' ? 'true' : 'false'; ?>"
  >
    <strong><?php echo $escape($translate('TXT_ADMIN_PRIVATE_TAB_EMAIL', 'Email privé IMAP / SMTP')); ?></strong>
    <small><?php echo $escape($translate('TXT_ADMIN_PRIVATE_TAB_EMAIL_HELP', 'Serveur d’envoi privé uniquement')); ?></small>
  </a>
</nav>

<?php if ($activeTab === 'email'): ?>
<section class="card admin-private-mail-card">
  <div class="admin-private-members-intro-header">
    <div>
      <span class="tag"><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_TAG', 'Espace privé')); ?></span>
      <h2><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MAIL_TITLE', 'Configuration email de l’espace privé')); ?></h2>
    </div>
  </div>
  <p class="notice-muted">
    <?php echo $escape($translate('TXT_ADMIN_PRIVATE_MAIL_HELP', 'Cette configuration s’applique uniquement à l’espace privé. IMAP sert à la réception des emails; pour l’envoi depuis les modules privés, la configuration utilisée est le serveur SMTP ci-dessous.')); ?>
  </p>

  <form method="POST" action="<?php echo $escape($membersUrl); ?>" class="admin-form-grid admin-private-mail-form" autocomplete="off" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>" />
    <input type="hidden" name="private_member_action" value="mail_settings" />
    <input type="hidden" name="private_members_tab" value="email" />

    <label class="admin-private-mail-toggle">
      <input type="hidden" name="private_mail[enabled]" value="0" />
      <input type="checkbox" name="private_mail[enabled]" value="1"<?php echo !empty($privateMail['enabled']) ? ' checked' : ''; ?> />
      <span><?php echo $escape($translate('TXT_ADMIN_SETTINGS_PRIVATE_MAIL_ENABLED', 'Activer les envois email de l’espace privé')); ?></span>
    </label>

    <div class="admin-form-grid admin-form-grid-3">
      <div class="field">
        <label for="private_mail_smtp_host"><?php echo $escape($translate('TXT_ADMIN_SETTINGS_PRIVATE_MAIL_HOST', 'Serveur SMTP')); ?></label>
        <input id="private_mail_smtp_host" name="private_mail[smtp_host]" type="text" value="<?php echo $escape((string) ($privateMail['smtpHost'] ?? 'ssl0.ovh.net')); ?>" required />
      </div>
      <div class="field">
        <label for="private_mail_smtp_port"><?php echo $escape($translate('TXT_ADMIN_SETTINGS_PRIVATE_MAIL_PORT', 'Port')); ?></label>
        <input id="private_mail_smtp_port" name="private_mail[smtp_port]" type="number" min="1" max="65535" value="<?php echo (int) ($privateMail['smtpPort'] ?? 465); ?>" required />
      </div>
      <div class="field">
        <label for="private_mail_smtp_encryption"><?php echo $escape($translate('TXT_ADMIN_SETTINGS_PRIVATE_MAIL_ENCRYPTION', 'Chiffrement')); ?></label>
        <select id="private_mail_smtp_encryption" name="private_mail[smtp_encryption]">
          <?php foreach (['ssl' => 'SSL', 'tls' => 'TLS/STARTTLS', 'starttls' => 'STARTTLS', '' => 'Aucun'] as $value => $label): ?>
            <option value="<?php echo $escape($value); ?>"<?php echo (string) ($privateMail['smtpEncryption'] ?? 'ssl') === $value ? ' selected' : ''; ?>>
              <?php echo $escape($label); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="private_mail_smtp_user"><?php echo $escape($translate('TXT_ADMIN_SETTINGS_PRIVATE_MAIL_USER', 'Utilisateur SMTP')); ?></label>
        <input id="private_mail_smtp_user" name="private_mail[smtp_user]" type="text" value="<?php echo $escape((string) ($privateMail['smtpUser'] ?? 'ne-pas-repondre@lescaramagnols.com')); ?>" />
      </div>
      <div class="field">
        <label for="private_mail_smtp_password"><?php echo $escape($translate('TXT_ADMIN_SETTINGS_PRIVATE_MAIL_PASSWORD', 'Mot de passe SMTP')); ?></label>
        <div class="admin-password-field">
          <input id="private_mail_smtp_password" name="private_mail[smtp_password]" type="password" value="<?php echo $escape((string) ($privateMail['smtpPassword'] ?? '')); ?>" autocomplete="new-password"<?php echo $secretMaskAttribute(!empty($privateMail['smtpPasswordConfigured'])); ?> />
          <?php echo $renderSecretToggleButton('private_mail_smtp_password'); ?>
        </div>
        <small><?php echo $escape(!empty($privateMail['smtpPasswordConfigured']) ? $translate('TXT_ADMIN_SETTINGS_PRIVATE_MAIL_PASSWORD_SET', 'Laisser les ********** ou vider le champ pour conserver le mot de passe enregistré.') : $translate('TXT_ADMIN_SETTINGS_PRIVATE_MAIL_PASSWORD_EMPTY', 'Renseigner le mot de passe avant activation en production.')); ?></small>
      </div>
      <div class="field">
        <label for="private_mail_from_address"><?php echo $escape($translate('TXT_ADMIN_SETTINGS_PRIVATE_MAIL_FROM', 'Adresse expéditeur')); ?></label>
        <input id="private_mail_from_address" name="private_mail[from_address]" type="email" value="<?php echo $escape((string) ($privateMail['fromAddress'] ?? 'ne-pas-repondre@lescaramagnols.com')); ?>" required />
      </div>
      <div class="field">
        <label for="private_mail_from_name"><?php echo $escape($translate('TXT_ADMIN_SETTINGS_PRIVATE_MAIL_FROM_NAME', 'Nom expéditeur')); ?></label>
        <input id="private_mail_from_name" name="private_mail[from_name]" type="text" value="<?php echo $escape((string) ($privateMail['fromName'] ?? 'Les Caramagnols')); ?>" required />
      </div>
      <div class="field">
        <label for="private_mail_reply_to"><?php echo $escape($translate('TXT_ADMIN_SETTINGS_PRIVATE_MAIL_REPLY_TO', 'Adresse de réponse')); ?></label>
        <input id="private_mail_reply_to" name="private_mail[reply_to]" type="email" value="<?php echo $escape((string) ($privateMail['replyTo'] ?? 'private@lescaramagnols.com')); ?>" />
      </div>
    </div>

    <details class="admin-private-mail-templates">
      <summary><?php echo $escape($translate('TXT_ADMIN_SETTINGS_PRIVATE_MAIL_TEMPLATES', 'Messages par défaut')); ?></summary>
      <div class="admin-private-mail-template-help">
        <strong><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MAIL_VARIABLES_TITLE', 'Variables utilisables')); ?></strong>
        <p>
          <?php foreach ($privateMailCommonVariables as $variable): ?>
            <code>{{<?php echo $escape($variable); ?>}}</code>
          <?php endforeach; ?>
        </p>
        <small>
          <?php echo $escape($translate('TXT_ADMIN_PRIVATE_MAIL_VARIABLES_HELP', 'Les variables non disponibles pour un message restent inchangées. Exemples : {{activation_url}} sert aux invitations, {{reset_url}} aux réinitialisations, {{delete_after}} aux suppressions programmées.')); ?>
        </small>
      </div>
      <div class="admin-private-mail-template-list">
        <?php foreach ($privateMailTemplateCatalog as $template): ?>
          <?php
          if (!is_array($template)) {
              continue;
          }
          $subjectKey = is_string($template['subject_key'] ?? null) ? (string) $template['subject_key'] : '';
          $bodyKey = is_string($template['body_key'] ?? null) ? (string) $template['body_key'] : '';
          if ($subjectKey === '' || $bodyKey === '') {
              continue;
          }
          $subjectLabel = is_string($template['subject_label'] ?? null) ? (string) $template['subject_label'] : $subjectKey;
          $bodyLabel = is_string($template['body_label'] ?? null) ? (string) $template['body_label'] : $bodyKey;
          $variables = is_array($template['variables'] ?? null) ? array_values(array_filter($template['variables'], 'is_string')) : [];
          $preview = is_array($privateMailPreviews[$bodyKey] ?? null) ? $privateMailPreviews[$bodyKey] : [];
          ?>
        <div class="admin-private-mail-template-pair">
          <div class="field">
            <label for="private_mail_template_<?php echo $escape($subjectKey); ?>"><?php echo $escape($subjectLabel); ?></label>
            <input id="private_mail_template_<?php echo $escape($subjectKey); ?>" name="private_mail[templates][<?php echo $escape($subjectKey); ?>]" type="text" value="<?php echo $escape((string) ($privateMailTemplates[$subjectKey] ?? '')); ?>" />
          </div>
          <div class="field">
            <label for="private_mail_template_<?php echo $escape($bodyKey); ?>"><?php echo $escape($bodyLabel); ?></label>
            <textarea id="private_mail_template_<?php echo $escape($bodyKey); ?>" name="private_mail[templates][<?php echo $escape($bodyKey); ?>]" rows="4"><?php echo $escape((string) ($privateMailTemplates[$bodyKey] ?? '')); ?></textarea>
            <?php if ($variables !== []): ?>
              <small>
                <?php echo $escape($translate('TXT_ADMIN_PRIVATE_MAIL_TEMPLATE_VARIABLES', 'Variables de ce message :')); ?>
                <?php foreach ($variables as $variable): ?>
                  <code>{{<?php echo $escape($variable); ?>}}</code>
                <?php endforeach; ?>
              </small>
            <?php endif; ?>
            <details class="admin-private-mail-preview">
              <summary><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MAIL_PREVIEW_TITLE', 'Aperçu sans envoi')); ?></summary>
              <p><strong><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MAIL_PREVIEW_SUBJECT', 'Sujet')); ?> :</strong> <?php echo $escape((string) ($preview['subject'] ?? '')); ?></p>
              <pre><?php echo $escape((string) ($preview['body'] ?? '')); ?></pre>
            </details>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    </details>

    <div class="actions-inline">
      <button type="submit"><?php echo $escape($translate('TXT_ADMIN_SETTINGS_SAVE', 'Enregistrer')); ?></button>
      <a class="button-link button-link-muted" href="<?php echo $escape($membersTabUrl); ?>"><?php echo $escape($translate('TXT_ADMIN_COMMON_CANCEL', 'Annuler')); ?></a>
    </div>
  </form>
</section>
<?php else: ?>
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

  <form method="POST" action="<?php echo $escape($membersUrl); ?>" class="filters-form admin-private-security-form" autocomplete="off" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>" />
    <input type="hidden" name="private_member_action" value="security_settings" />
    <input type="hidden" name="private_members_tab" value="members" />
    <label class="admin-private-mail-toggle">
      <input type="hidden" name="private_security[mfa_totp_enabled]" value="0" />
      <input type="checkbox" name="private_security[mfa_totp_enabled]" value="1"<?php echo !empty($privateSecurity['mfaTotpEnabled']) ? ' checked' : ''; ?> />
      <span><?php echo $escape($translate('TXT_ADMIN_PRIVATE_SECURITY_2FA_LABEL', 'Code 2FA')); ?></span>
    </label>
    <small><?php echo $escape($translate('TXT_ADMIN_PRIVATE_SECURITY_2FA_HELP', 'Lorsque le Code 2FA est désactivé, le champ n’apparaît pas sur le formulaire de connexion privé.')); ?></small>
    <button type="submit"><?php echo $escape($translate('TXT_ADMIN_SETTINGS_SAVE', 'Enregistrer')); ?></button>
  </form>

  <form method="POST" action="<?php echo $escape($membersUrl); ?>" class="filters-form" aria-label="<?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_INVITE_FORM', 'Inviter un membre privé')); ?>">
    <div class="inline-form">
      <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>" />
      <input type="hidden" name="private_member_action" value="invite" />
      <label for="private-member-email"><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_INVITE_EMAIL', 'Email à inviter')); ?></label>
      <input id="private-member-email" type="email" name="email" placeholder="<?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_SEARCH_PLACEHOLDER', 'adresse@email.fr')); ?>" required />
      <button type="submit"><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_INVITE_SUBMIT', 'Inviter')); ?></button>
    </div>
  </form>

  <form method="GET" action="<?php echo $escape($membersUrl); ?>#private-members-results" class="filters-form" aria-label="<?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_FILTERS', 'Filtres membres privés')); ?>">
    <div class="inline-form">
      <label for="member-status"><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_FILTER_STATUS', 'Filtrer le statut')); ?></label>
      <select id="member-status" name="status">
        <option value=""><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_FILTER_STATUS_ALL', 'Tous')); ?></option>
        <?php foreach (['invited', 'active', 'suspended', 'disabled'] as $statusOption): ?>
          <option value="<?php echo $escape($statusOption); ?>" <?php echo $statusOption === $statusFilter ? 'selected' : ''; ?>>
            <?php echo $escape($statusLabels[$statusOption] ?? $statusOption); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label for="member-search"><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_FILTER_SEARCH', 'Rechercher un email')); ?></label>
      <input id="member-search" type="search" name="q" value="<?php echo $escape($searchQuery); ?>" list="private-member-email-choices" data-private-member-search placeholder="<?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_SEARCH_PLACEHOLDER', 'adresse@email.fr')); ?>" />
      <?php if ($memberEmailChoices !== []): ?>
        <datalist id="private-member-email-choices">
          <?php foreach ($memberEmailChoices as $memberEmailChoice): ?>
            <?php $memberEmailChoice = trim($memberEmailChoice); ?>
            <?php if ($memberEmailChoice === '') {
                continue;
            } ?>
            <option value="<?php echo $escape($memberEmailChoice); ?>"></option>
          <?php endforeach; ?>
        </datalist>
      <?php endif; ?>

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

<section id="private-members-results" class="card admin-private-members-card">
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
            $moduleDataCounts = is_array($member['moduleDataCounts'] ?? null) ? $member['moduleDataCounts'] : [];
            $deletionBackup = is_array($member['deletionBackup'] ?? null) ? $member['deletionBackup'] : null;
            $deletionBackupDeleteAfter = is_array($deletionBackup) && is_string($deletionBackup['deleteAfter'] ?? null)
                ? (string) $deletionBackup['deleteAfter']
                : '';
            $deletionBackupDeleteAfterTimestamp = $deletionBackupDeleteAfter !== '' ? strtotime($deletionBackupDeleteAfter) : false;
            $memberFragment = $memberId > 0 ? 'private-member-' . $memberId : '';
            ?>
            <tr<?php echo $memberFragment !== '' ? ' id="' . $escape($memberFragment) . '"' : ''; ?>>
              <td class="admin-private-members-email" data-private-member-email="<?php echo $escape((string) ($member['email'] ?? '')); ?>"><?php echo $escape((string) ($member['email'] ?? '-')); ?></td>
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
                      $moduleDataCount = max(0, (int) ($moduleDataCounts[$moduleCode] ?? 0));
                      $moduleLocked = $moduleAssigned && $moduleDataCount > 0;
                      ?>
                      <label class="admin-private-members-module-option">
                        <?php if ($moduleLocked): ?>
                          <input type="hidden" name="modules[]" value="<?php echo $escape($moduleCode); ?>" />
                        <?php endif; ?>
                        <input type="checkbox" name="modules[]" value="<?php echo $escape($moduleCode); ?>" <?php echo $moduleAssigned ? 'checked' : ''; ?> <?php echo (!$moduleActive || $moduleLocked) ? 'disabled' : ''; ?> />
                        <span class="admin-private-members-module-label"><?php echo $escape($moduleName); ?></span>
                        <?php if (!$moduleActive): ?>
                          <span class="admin-private-members-module-state is-inactive">
                            <?php echo $escape($translate('TXT_ADMIN_PRIVATE_MODULE_INACTIVE', 'inactif')); ?>
                          </span>
                        <?php elseif ($moduleLocked): ?>
                          <span class="admin-private-members-module-state is-inactive">
                            <?php echo $escape(sprintf('infos existantes : %d', $moduleDataCount)); ?>
                          </span>
                        <?php endif; ?>
                      </label>
                    <?php endforeach; ?>
                  </div>
                  <button class="button-small" type="submit">
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

                  <?php if ($statusValue === 'suspended'): ?>
                    <?php if (!is_array($deletionBackup)): ?>
                      <form method="POST" action="<?php echo $escape($membersUrl); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>" />
                        <input type="hidden" name="private_member_action" value="reactivate" />
                        <input type="hidden" name="private_user_id" value="<?php echo $memberId; ?>" />
                        <?php if ($memberFragment !== ''): ?>
                          <input type="hidden" name="private_member_return_fragment" value="<?php echo $escape($memberFragment); ?>" />
                        <?php endif; ?>
                        <button class="button-small" type="submit"><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_ACTION_REACTIVATE', 'Réactiver le compte')); ?></button>
                      </form>
                    <?php endif; ?>

                    <?php if (is_array($deletionBackup)): ?>
                      <form method="POST" action="<?php echo $escape($membersUrl); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>" />
                        <input type="hidden" name="private_member_action" value="download_backup" />
                        <input type="hidden" name="private_user_id" value="<?php echo $memberId; ?>" />
                        <button class="button-small button-muted" type="submit">
                          <?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_BACKUP_DOWNLOAD', 'Récupérer la sauvegarde ZIP')); ?>
                        </button>
                      </form>
                      <?php if ($deletionBackupDeleteAfterTimestamp !== false): ?>
                        <p class="admin-private-members-backup-note">
                          <?php
                          echo $escape(
                              sprintf(
                                  $translate('TXT_ADMIN_PRIVATE_MEMBERS_BACKUP_DELETE_AFTER', 'Suppression définitive prévue le %s.'),
                                  date('d/m/Y', (int) $deletionBackupDeleteAfterTimestamp)
                              )
                          );
                          ?>
                        </p>
                      <?php endif; ?>
                      <p class="admin-private-members-backup-note">
                        <?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_BACKUP_NO_RESTORE', 'La sauvegarde ZIP contient backup.json, manifest.json et les fichiers retrouvés. Elle ne réactive pas le compte et ne restaure pas automatiquement les données.')); ?>
                      </p>
                    <?php endif; ?>

                    <?php if (!is_array($deletionBackup)): ?>
                      <?php $deleteDialogId = 'admin-private-member-delete-dialog-' . $memberId; ?>
                      <button
                        class="button-small button-danger"
                        type="button"
                        aria-controls="<?php echo $escape($deleteDialogId); ?>"
                        data-admin-private-delete-open="<?php echo $escape($deleteDialogId); ?>"
                      >
                        <?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_DELETE_SUSPENDED_TITLE', 'Suppression du compte')); ?>
                      </button>
                      <div class="admin-private-members-delete-dialog" id="<?php echo $escape($deleteDialogId); ?>" role="dialog" aria-modal="true" hidden>
                        <form method="POST" action="<?php echo $escape($membersUrl); ?>" class="admin-private-members-delete-form">
                          <div class="admin-private-members-delete-dialog-header">
                            <h3><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_DELETE_SUSPENDED_TITLE', 'Suppression du compte')); ?></h3>
                            <button class="button-small button-muted" type="button" data-admin-close-dialog aria-label="<?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_DELETE_SUSPENDED_NO', 'Non')); ?>">×</button>
                          </div>
                          <p class="admin-private-members-delete-dialog-email">
                            <?php echo $escape((string) ($member['email'] ?? '-')); ?>
                          </p>
                          <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>" />
                          <input type="hidden" name="private_member_action" value="delete_suspended" />
                          <input type="hidden" name="private_user_id" value="<?php echo $memberId; ?>" />
                          <input type="hidden" name="private_member_delete_confirm" value="1" />
                          <?php if ($memberFragment !== ''): ?>
                            <input type="hidden" name="private_member_return_fragment" value="<?php echo $escape($memberFragment); ?>" />
                          <?php endif; ?>
                          <div class="admin-private-members-delete-note">
                            <span><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_DELETE_SUSPENDED_HELP', 'Une sauvegarde est créée, les données sont purgées, puis le compte et la sauvegarde seront supprimés par cron après 30 jours.')); ?></span>
                          </div>
                          <p class="admin-private-members-confirm-question">
                            <?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_DELETE_SUSPENDED_QUESTION', 'Voulez-vous supprimer ce compte suspendu ?')); ?>
                          </p>
                          <div class="admin-private-members-confirm-actions">
                            <button class="button-small button-muted" type="button" data-admin-close-dialog>
                              <?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_DELETE_SUSPENDED_NO', 'Non')); ?>
                            </button>
                            <button class="button-small button-danger" type="submit">
                              <?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_DELETE_SUSPENDED_YES', 'Oui, sauvegarder et purger les données')); ?>
                            </button>
                          </div>
                        </form>
                      </div>
                    <?php endif; ?>
                  <?php else: ?>
                    <form method="POST" action="<?php echo $escape($membersUrl); ?>">
                      <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>" />
                      <input type="hidden" name="private_member_action" value="suspend" />
                      <input type="hidden" name="private_user_id" value="<?php echo $memberId; ?>" />
                      <?php if ($memberFragment !== ''): ?>
                        <input type="hidden" name="private_member_return_fragment" value="<?php echo $escape($memberFragment); ?>" />
                      <?php endif; ?>
                      <button class="button-small button-muted" type="submit"><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_ACTION_SUSPEND', 'Suspendre')); ?></button>
                    </form>

                    <form method="POST" action="<?php echo $escape($membersUrl); ?>">
                      <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>" />
                      <input type="hidden" name="private_member_action" value="reset" />
                      <input type="hidden" name="private_user_id" value="<?php echo $memberId; ?>" />
                      <?php if ($memberFragment !== ''): ?>
                        <input type="hidden" name="private_member_return_fragment" value="<?php echo $escape($memberFragment); ?>" />
                      <?php endif; ?>
                      <button class="button-small button-muted" type="submit"><?php echo $escape($translate('TXT_ADMIN_PRIVATE_MEMBERS_ACTION_RESET', 'Reset password')); ?></button>
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
<?php endif; ?>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('click', (event) => {
      const target = event.target instanceof Element ? event.target : null;
      const openButton = target ? target.closest('[data-admin-private-delete-open]') : null;
      if (openButton instanceof HTMLElement) {
        const panelId = openButton.dataset.adminPrivateDeleteOpen || '';
        const panel = panelId !== '' ? document.getElementById(panelId) : null;
        if (panel instanceof HTMLElement) {
          panel.hidden = false;
          const firstButton = panel.querySelector('button');
          if (firstButton instanceof HTMLButtonElement) {
            firstButton.focus();
          }
        }
      }

      const closeDialogButton = target ? target.closest('[data-admin-close-dialog]') : null;
      const panel = closeDialogButton ? closeDialogButton.closest('.admin-private-members-delete-dialog') : null;
      if (panel instanceof HTMLElement) {
        panel.hidden = true;
      }
    });

    const searchInput = document.querySelector('[data-private-member-search]');
    const emailChoices = document.getElementById('private-member-email-choices');
    const filterForm = searchInput ? searchInput.closest('form') : null;
    if (!(searchInput instanceof HTMLInputElement) || !(filterForm instanceof HTMLFormElement)) {
      return;
    }

    const knownEmails = new Set(
      Array.from(emailChoices ? emailChoices.querySelectorAll('option') : [])
        .map((option) => option.value.trim().toLowerCase())
        .filter(Boolean)
    );

    const scrollToMemberEmail = (email) => {
      const normalizedEmail = email.trim().toLowerCase();
      if (normalizedEmail === '') {
        return false;
      }

      const matchingCell = Array.from(document.querySelectorAll('[data-private-member-email]'))
        .find((cell) => cell.dataset.privateMemberEmail.trim().toLowerCase() === normalizedEmail);
      const matchingRow = matchingCell ? matchingCell.closest('tr[id]') : null;
      if (!(matchingRow instanceof HTMLTableRowElement)) {
        return false;
      }

      document.querySelectorAll('.admin-private-members-row-active')
        .forEach((row) => row.classList.remove('admin-private-members-row-active'));
      matchingRow.classList.add('admin-private-members-row-active');
      matchingRow.scrollIntoView({ behavior: 'smooth', block: 'center' });

      return true;
    };

    const handleSelection = () => {
      const email = searchInput.value.trim();
      if (!knownEmails.has(email.toLowerCase())) {
        return;
      }

      if (!scrollToMemberEmail(email)) {
        const statusSelect = filterForm.querySelector('select[name="status"]');
        if (statusSelect instanceof HTMLSelectElement) {
          statusSelect.value = '';
        }
        filterForm.requestSubmit();
      }
    };

    searchInput.addEventListener('input', handleSelection);
    searchInput.addEventListener('change', handleSelection);

    if (searchInput.value.trim() !== '') {
      window.setTimeout(() => {
        scrollToMemberEmail(searchInput.value);
      }, 120);
    }
  });
</script>
