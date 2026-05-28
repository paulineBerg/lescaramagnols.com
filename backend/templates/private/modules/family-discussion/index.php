<?php
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

    $id = is_numeric($conversation['id'] ?? null) ? (int) $conversation['id'] : 0;

    return $id > 0 ? 'Conversation directe #' . $id : 'Conversation directe';
};

$shortText = static function (string $value, int $maxLength = 90): string {
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($value, 'UTF-8') > $maxLength
            ? mb_substr($value, 0, $maxLength, 'UTF-8') . '...'
            : $value;
    }

    return strlen($value) > $maxLength ? substr($value, 0, $maxLength) . '...' : $value;
};
?>
<section>
  <p class="muted">
    <a href="<?php echo htmlspecialchars($indexUrl, ENT_QUOTES, 'UTF-8'); ?>">Discussions</a>
    · conservation automatique des messages et fichiers pendant 60 jours
  </p>

  <?php if ($notice !== ''): ?>
    <p class="notice notice-success">
      <?php
      $noticeMessage = match ($notice) {
          'invite_sent' => 'Invitation envoyee.',
          default => $notice,
      };
      echo htmlspecialchars($noticeMessage, ENT_QUOTES, 'UTF-8');
      ?>
    </p>
  <?php endif; ?>

  <?php if ($error !== ''): ?>
    <p class="notice notice-error">
      <?php
      $errorMessage = match ($error) {
          'csrf' => 'Session expiree, veuillez recommencer.',
          'rate_limited' => 'Trop de creations successives, veuillez patienter.',
          'invite' => 'Invitation impossible.',
          default => 'La conversation n\'a pas pu etre creee.',
      };
      echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8');
      ?>
    </p>
  <?php endif; ?>

  <div class="cards-grid">
    <section class="card">
      <span class="tag">Message prive</span>
      <h2>Nouvelle discussion</h2>
      <?php if ($members === []): ?>
        <p class="muted">Aucun autre membre actif disponible.</p>
      <?php else: ?>
        <form method="post" action="<?php echo htmlspecialchars($indexUrl, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
          <input type="hidden" name="type" value="direct" />
          <label for="discussion-recipient">Membre</label>
          <select id="discussion-recipient" name="recipient_id" required>
            <option value="">Choisir un membre</option>
            <?php foreach ($members as $member): ?>
              <?php if (!is_array($member) || !is_numeric($member['id'] ?? null)) { continue; } ?>
              <option value="<?php echo htmlspecialchars((string) (int) $member['id'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars((string) ($member['email'] ?? ('Membre #' . (int) $member['id'])), ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="submit">Creer</button>
        </form>
      <?php endif; ?>
    </section>

    <section class="card">
      <span class="tag">Groupe</span>
      <h2>Nouveau groupe</h2>
      <?php if ($members === []): ?>
        <p class="muted">Ajoutez d'autres membres actifs avant de creer un groupe.</p>
      <?php else: ?>
        <form method="post" action="<?php echo htmlspecialchars($indexUrl, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
          <input type="hidden" name="type" value="group" />
          <label for="discussion-group-title">Nom du groupe</label>
          <input id="discussion-group-title" type="text" name="title" maxlength="160" required />
          <fieldset>
            <legend>Membres</legend>
            <?php foreach ($members as $member): ?>
              <?php if (!is_array($member) || !is_numeric($member['id'] ?? null)) { continue; } ?>
              <label>
                <input type="checkbox" name="member_ids[]" value="<?php echo htmlspecialchars((string) (int) $member['id'], ENT_QUOTES, 'UTF-8'); ?>" />
                <?php echo htmlspecialchars((string) ($member['email'] ?? ('Membre #' . (int) $member['id'])), ENT_QUOTES, 'UTF-8'); ?>
              </label>
            <?php endforeach; ?>
          </fieldset>
          <button type="submit">Creer le groupe</button>
        </form>
      <?php endif; ?>
    </section>

    <section class="card">
      <span class="tag">Invitation</span>
      <h2>Inviter par email</h2>
      <form method="post" action="<?php echo htmlspecialchars($indexUrl, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="action" value="invite_member" />
        <label for="discussion-invite-email">Email</label>
        <input id="discussion-invite-email" type="email" name="recipient_email" maxlength="190" required />
        <label for="discussion-invite-subject">Objet</label>
        <input id="discussion-invite-subject" type="text" name="subject" maxlength="180" value="<?php echo htmlspecialchars((string) ($inviteDefaults['subject'] ?? 'Invitation à rejoindre les discussions famille'), ENT_QUOTES, 'UTF-8'); ?>" />
        <label for="discussion-invite-message">Message</label>
        <textarea id="discussion-invite-message" name="message" maxlength="4000"><?php echo htmlspecialchars((string) ($inviteDefaults['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        <button type="submit">Envoyer l'invitation</button>
      </form>
    </section>

    <section class="card private-card-wide">
      <h2>Conversations</h2>
      <?php if ($conversations === []): ?>
        <p class="muted">Aucune conversation pour le moment.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Discussion</th>
              <th>Dernier message</th>
              <th>Non lus</th>
              <th>Activite</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($conversations as $conversation): ?>
              <?php if (!is_array($conversation) || !is_numeric($conversation['id'] ?? null)) { continue; } ?>
              <?php
              $conversationId = (int) $conversation['id'];
              $unreadCount = max(0, (int) ($conversation['unreadCount'] ?? 0));
              $lastBody = is_string($conversation['lastBody'] ?? null) ? trim((string) $conversation['lastBody']) : '';
              ?>
              <tr>
                <td>
                  <a href="<?php echo htmlspecialchars(rtrim($indexUrl, '/') . '/' . $conversationId, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($conversationTitle($conversation), ENT_QUOTES, 'UTF-8'); ?>
                  </a>
                </td>
                <td><?php echo htmlspecialchars($lastBody !== '' ? $shortText($lastBody) : 'Aucun message', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) $unreadCount, ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($formatDate($conversation['lastMessageAt'] ?? $conversation['updatedAt'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  </div>
</section>
