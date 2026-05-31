<?php
$translate = static function (string $key, string $fallback): string {
    if (!function_exists('t')) {
        return $fallback;
    }

    $translated = t($key);
    if (!is_string($translated) || $translated === '' || $translated === $key || $translated === '[[' . $key . ']]') {
        return $fallback;
    }

    return $translated;
};
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$conversations = is_array($viewModel['conversations'] ?? null) ? $viewModel['conversations'] : [];
$members = is_array($viewModel['members'] ?? null) ? $viewModel['members'] : [];
$csrfToken = is_string($viewModel['discussionCsrfToken'] ?? null) ? (string) $viewModel['discussionCsrfToken'] : '';
$urls = is_array($viewModel['discussionUrls'] ?? null) ? $viewModel['discussionUrls'] : [];
$indexUrl = (string) ($urls['index'] ?? private_portal_url('discussion_index'));
$error = is_string($viewModel['error'] ?? null) ? (string) $viewModel['error'] : '';
$notice = is_string($viewModel['notice'] ?? null) ? (string) $viewModel['notice'] : '';
$inviteDefaults = is_array($viewModel['discussionInviteDefaults'] ?? null) ? $viewModel['discussionInviteDefaults'] : [];

$formatDate = static function (mixed $value): string {
    $raw = is_string($value) ? trim($value) : '';
    if ($raw === '' || strtotime($raw) === false) {
        return '';
    }

    return date('d/m/Y H:i', (int) strtotime($raw));
};

$conversationTitle = static function (array $conversation): string {
    $title = is_string($conversation['title'] ?? null) ? trim((string) $conversation['title']) : '';
    if ($title !== '') {
        return $title;
    }

    $directEmail = is_string($conversation['directMemberEmail'] ?? null)
        ? trim((string) $conversation['directMemberEmail'])
        : '';
    if (($conversation['type'] ?? '') === 'direct' && $directEmail !== '') {
        return $directEmail;
    }

    return 'Conversation directe';
};

$shortText = static function (string $value, int $maxLength = 90): string {
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($value, 'UTF-8') > $maxLength
            ? mb_substr($value, 0, $maxLength, 'UTF-8') . '...'
            : $value;
    }

    return strlen($value) > $maxLength ? substr($value, 0, $maxLength) . '...' : $value;
};

$memberLabel = static function (array $member): string {
    $name = is_string($member['fullName'] ?? null) ? trim((string) $member['fullName']) : '';
    $email = is_string($member['email'] ?? null) ? trim((string) $member['email']) : '';
    if ($name !== '' && $email !== '') {
        return $name . ' - ' . $email;
    }
    if ($email !== '') {
        return $email;
    }

    return 'Membre #' . (int) ($member['id'] ?? 0);
};
$directDialogId = 'discussion-direct-create-dialog';
$groupDialogId = 'discussion-group-create-dialog';
$inviteDialogId = 'discussion-invite-create-dialog';
$conversationTypeLabel = static fn (string $type): string => $type === 'group' ? 'Groupe' : 'Directe';
?>
<section class="private-discussion-module">
  <nav class="private-module-nav" aria-label="Navigation discussions">
    <div class="private-module-nav-row">
      <a class="active" href="<?php echo $h($indexUrl); ?>">Conversations</a>
      <button type="button" data-private-dialog-open="<?php echo $h($directDialogId); ?>"<?php echo $members === [] ? ' disabled' : ''; ?>>Nouvelle discussion</button>
      <button type="button" data-private-dialog-open="<?php echo $h($groupDialogId); ?>"<?php echo $members === [] ? ' disabled' : ''; ?>>Nouveau groupe</button>
      <button type="button" data-private-dialog-open="<?php echo $h($inviteDialogId); ?>">Inviter</button>
    </div>
  </nav>

  <?php if ($notice !== ''): ?>
    <p class="notice notice-success">
      <?php
      $noticeMessage = match ($notice) {
          'invite_sent' => 'Invitation envoyée.',
          default => $notice,
      };
      echo $h($noticeMessage);
      ?>
    </p>
  <?php endif; ?>

  <?php if ($error !== ''): ?>
    <p class="notice notice-error">
      <?php
      $errorMessage = match ($error) {
          'csrf' => 'Session expirée, veuillez recommencer.',
          'rate_limited' => 'Trop de créations successives, veuillez patienter.',
          'invite' => 'Invitation impossible.',
          default => 'La conversation n’a pas pu être créée.',
      };
      echo $h($errorMessage);
      ?>
    </p>
  <?php endif; ?>

  <section class="card private-card-wide private-list-section" id="private-discussion-conversations" data-private-filter-scope>
    <div class="private-list-header">
      <div>
        <span class="tag">Messages</span>
        <h2>Conversations</h2>
        <p class="muted">Liste filtrable des échanges directs et des groupes.</p>
      </div>
      <div class="private-list-filter-actions">
        <button type="button" class="private-create-button" data-private-dialog-open="<?php echo $h($directDialogId); ?>"<?php echo $members === [] ? ' disabled' : ''; ?>>Nouvelle discussion</button>
        <button type="button" class="private-button-secondary" data-private-dialog-open="<?php echo $h($groupDialogId); ?>"<?php echo $members === [] ? ' disabled' : ''; ?>>Nouveau groupe</button>
        <button type="button" class="private-button-secondary" data-private-dialog-open="<?php echo $h($inviteDialogId); ?>">Inviter</button>
      </div>
    </div>
    <?php if ($conversations === []): ?>
      <p class="muted">Aucune conversation pour le moment.</p>
    <?php else: ?>
      <div class="private-list-tools">
        <div class="private-list-filter-grid">
          <label>Recherche
            <input type="search" placeholder="Titre ou dernier message" data-private-filter="text" />
          </label>
          <label>Type
            <select data-private-filter="type">
              <option value="all">Tous</option>
              <option value="direct">Directes</option>
              <option value="group">Groupes</option>
            </select>
          </label>
          <label>Lecture
            <select data-private-filter="status">
              <option value="all">Tous</option>
              <option value="unread">Non lus</option>
              <option value="read">Lus</option>
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
              <th>Discussion</th>
              <th>Type</th>
              <th>Dernier message</th>
              <th>Non lus</th>
              <th>Activité</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($conversations as $conversation): ?>
              <?php if (!is_array($conversation) || !is_numeric($conversation['id'] ?? null)) { continue; } ?>
              <?php
              $conversationId = (int) $conversation['id'];
              $conversationType = is_string($conversation['type'] ?? null) ? (string) $conversation['type'] : 'direct';
              $unreadCount = max(0, (int) ($conversation['unreadCount'] ?? 0));
              $lastBody = is_string($conversation['lastBody'] ?? null) ? trim((string) $conversation['lastBody']) : '';
              $title = $conversationTitle($conversation);
              ?>
              <tr data-private-filter-row data-filter-text="<?php echo $h($title . ' ' . $lastBody); ?>" data-filter-type="<?php echo $h($conversationType); ?>" data-filter-status="<?php echo $unreadCount > 0 ? 'unread' : 'read'; ?>">
                <td>
                  <a href="<?php echo $h(rtrim($indexUrl, '/') . '/' . $conversationId); ?>">
                    <?php echo $h($title); ?>
                  </a>
                </td>
                <td><span class="tag"><?php echo $h($conversationTypeLabel($conversationType)); ?></span></td>
                <td><?php echo $h($lastBody !== '' ? $shortText($lastBody) : 'Aucun message'); ?></td>
                <td><?php echo $h((string) $unreadCount); ?></td>
                <td><?php echo $h($formatDate($conversation['lastMessageAt'] ?? $conversation['updatedAt'] ?? '')); ?></td>
              </tr>
            <?php endforeach; ?>
            <tr class="private-empty-row" data-private-filter-empty hidden>
              <td colspan="5">Aucune conversation ne correspond aux filtres.</td>
            </tr>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <aside class="notice private-discussion-security" aria-label="<?php echo $h($translate('TXT_PRIVATE_DISCUSSION_SECURITY_TITLE', 'Chiffrement des discussions')); ?>">
    <strong><?php echo $h($translate('TXT_PRIVATE_DISCUSSION_SECURITY_TITLE', 'Chiffrement des discussions')); ?></strong>
    <ul>
      <li><?php echo $h($translate('TXT_PRIVATE_DISCUSSION_SECURITY_TEXT', 'Les nouveaux messages texte sont chiffrés dans le navigateur avant envoi: le serveur ne stocke pas leur corps en clair.')); ?></li>
      <li><?php echo $h($translate('TXT_PRIVATE_DISCUSSION_SECURITY_FILES', 'Les images et fichiers joints sont chiffrés sur disque côté serveur, stockés hors webroot, puis déchiffrés seulement lors d’un téléchargement autorisé.')); ?></li>
      <li><?php echo $h($translate('TXT_PRIVATE_DISCUSSION_SECURITY_METADATA', 'Les métadonnées techniques restent nécessaires au fonctionnement: participants, dates, titres de groupes, noms de fichiers, types et tailles.')); ?></li>
      <li><?php echo $h($translate('TXT_PRIVATE_DISCUSSION_SECURITY_RETENTION', 'Les messages et fichiers gardent une rétention courte de 60 jours, avec purge automatique et suppression manuelle possible par message.')); ?></li>
    </ul>
  </aside>

  <dialog class="private-dialog" id="<?php echo $h($directDialogId); ?>" aria-labelledby="<?php echo $h($directDialogId . '-title'); ?>">
    <div class="private-dialog-panel">
      <header class="private-dialog-header">
        <h3 id="<?php echo $h($directDialogId . '-title'); ?>">Nouvelle discussion</h3>
        <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
      </header>
      <?php if ($members === []): ?>
        <p class="muted">Aucun autre membre actif disponible.</p>
      <?php else: ?>
        <form method="post" action="<?php echo $h($indexUrl); ?>">
          <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
          <input type="hidden" name="type" value="direct" />
          <fieldset>
            <legend><?php echo $h($translate('TXT_PRIVATE_DISCUSSION_ACCEPTED_MEMBERS_LEGEND', 'Membres ayant accepté l’invitation')); ?></legend>
            <?php foreach ($members as $member): ?>
              <?php if (!is_array($member) || !is_numeric($member['id'] ?? null)) { continue; } ?>
              <label class="private-checkbox-inline">
                <input type="checkbox" name="recipient_ids[]" value="<?php echo $h((string) (int) $member['id']); ?>" />
                <span><?php echo $h($memberLabel($member)); ?></span>
              </label>
            <?php endforeach; ?>
          </fieldset>
          <p class="muted">
            <?php echo $h($translate('TXT_PRIVATE_DISCUSSION_DIRECT_CHECKBOX_HELP', 'Cochez un seul membre pour ouvrir une discussion privée.')); ?>
          </p>
          <button type="submit">Créer</button>
        </form>
      <?php endif; ?>
    </div>
  </dialog>

  <dialog class="private-dialog" id="<?php echo $h($groupDialogId); ?>" aria-labelledby="<?php echo $h($groupDialogId . '-title'); ?>">
    <div class="private-dialog-panel">
      <header class="private-dialog-header">
        <h3 id="<?php echo $h($groupDialogId . '-title'); ?>">Nouveau groupe</h3>
        <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
      </header>
      <?php if ($members === []): ?>
        <p class="muted">Ajoutez d'autres membres actifs avant de créer un groupe.</p>
      <?php else: ?>
        <form method="post" action="<?php echo $h($indexUrl); ?>">
          <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
          <input type="hidden" name="type" value="group" />
          <label for="discussion-group-title">Nom du groupe</label>
          <input id="discussion-group-title" type="text" name="title" maxlength="160" required />
          <fieldset>
            <legend>Membres</legend>
            <?php foreach ($members as $member): ?>
              <?php if (!is_array($member) || !is_numeric($member['id'] ?? null)) { continue; } ?>
              <label class="private-checkbox-inline">
                <input type="checkbox" name="member_ids[]" value="<?php echo $h((string) (int) $member['id']); ?>" />
                <span><?php echo $h($memberLabel($member)); ?></span>
              </label>
            <?php endforeach; ?>
          </fieldset>
          <button type="submit">Créer le groupe</button>
        </form>
      <?php endif; ?>
    </div>
  </dialog>

  <dialog class="private-dialog" id="<?php echo $h($inviteDialogId); ?>" aria-labelledby="<?php echo $h($inviteDialogId . '-title'); ?>">
    <div class="private-dialog-panel">
      <header class="private-dialog-header">
        <h3 id="<?php echo $h($inviteDialogId . '-title'); ?>">Inviter par email</h3>
        <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="Fermer">×</button>
      </header>
      <form method="post" action="<?php echo $h($indexUrl); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
        <input type="hidden" name="action" value="invite_member" />
        <label for="discussion-invite-email">Email</label>
        <input id="discussion-invite-email" type="email" name="recipient_email" maxlength="190" required />
        <label for="discussion-invite-subject">Objet</label>
        <input id="discussion-invite-subject" type="text" name="subject" maxlength="180" value="<?php echo $h((string) ($inviteDefaults['subject'] ?? 'Invitation à rejoindre les discussions famille')); ?>" />
        <label for="discussion-invite-message">Message</label>
        <textarea id="discussion-invite-message" name="message" maxlength="4000"><?php echo $h((string) ($inviteDefaults['message'] ?? '')); ?></textarea>
        <button type="submit">Envoyer l'invitation</button>
      </form>
    </div>
  </dialog>
</section>
