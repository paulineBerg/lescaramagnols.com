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

$conversation = is_array($viewModel['conversation'] ?? null) ? $viewModel['conversation'] : [];
$conversations = is_array($viewModel['conversations'] ?? null) ? $viewModel['conversations'] : [];
$messages = is_array($viewModel['messages'] ?? null) ? $viewModel['messages'] : [];
$conversationMembers = is_array($viewModel['conversationMembers'] ?? null) ? $viewModel['conversationMembers'] : [];
$mediaGallery = is_array($viewModel['discussionMediaGallery'] ?? null) ? $viewModel['discussionMediaGallery'] : [];
$members = is_array($viewModel['members'] ?? null) ? $viewModel['members'] : [];
$timeline = is_array($viewModel['discussionTimeline'] ?? null) ? $viewModel['discussionTimeline'] : [];
$notificationPreference = is_array($viewModel['notificationPreference'] ?? null) ? $viewModel['notificationPreference'] : [];
$currentUserId = is_numeric($viewModel['discussionCurrentUserId'] ?? null) ? (int) $viewModel['discussionCurrentUserId'] : 0;
$csrfToken = is_string($viewModel['discussionCsrfToken'] ?? null) ? (string) $viewModel['discussionCsrfToken'] : '';
$urls = is_array($viewModel['discussionUrls'] ?? null) ? $viewModel['discussionUrls'] : [];
$indexUrl = (string) ($urls['index'] ?? private_portal_url('discussion_index'));
$filesUrl = rtrim((string) ($urls['files'] ?? private_portal_url('discussion_files')), '/');
$conversationId = is_numeric($conversation['id'] ?? null) ? (int) $conversation['id'] : 0;
$conversationUrl = $conversationId > 0 ? rtrim($indexUrl, '/') . '/' . $conversationId : $indexUrl;
$isConversationOwner = is_numeric($conversation['createdByPrivateUserId'] ?? null)
    && (int) $conversation['createdByPrivateUserId'] === $currentUserId;
$apiMessagesUrl = rtrim((string) ($urls['apiConversations'] ?? private_portal_url('discussion_api_conversations')), '/') . '/' . $conversationId . '/messages';
$apiEventsUrl = rtrim((string) ($urls['apiConversations'] ?? private_portal_url('discussion_api_conversations')), '/') . '/' . $conversationId . '/events';
$apiClientEventsUrl = (string) ($urls['apiClientEvents'] ?? private_portal_url('discussion_api_client_events'));
$apiReadUrl = rtrim((string) ($urls['apiConversations'] ?? private_portal_url('discussion_api_conversations')), '/') . '/' . $conversationId . '/read';
$apiKeysUrl = rtrim((string) ($urls['apiConversations'] ?? private_portal_url('discussion_api_conversations')), '/') . '/' . $conversationId . '/keys';
$apiDevicesUrl = (string) ($urls['apiCryptoDevices'] ?? private_portal_url('discussion_api_crypto_devices'));
$notice = is_string($viewModel['notice'] ?? null) ? (string) $viewModel['notice'] : '';
$error = is_string($viewModel['error'] ?? null) ? (string) $viewModel['error'] : '';

$formatDate = static function (mixed $value): string {
    $raw = is_string($value) ? trim($value) : '';
    if ($raw === '' || strtotime($raw) === false) {
        return '';
    }

    return date('d/m/Y H:i', (int) strtotime($raw));
};

$shortText = static function (string $value, int $maxLength = 90): string {
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($value, 'UTF-8') > $maxLength
            ? mb_substr($value, 0, $maxLength, 'UTF-8') . '...'
            : $value;
    }

    return strlen($value) > $maxLength ? substr($value, 0, $maxLength) . '...' : $value;
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

$memberEmails = [];
foreach (array_merge($members, $conversationMembers) as $member) {
    if (!is_array($member) || !is_numeric($member['id'] ?? null)) {
        continue;
    }

    $name = is_string($member['fullName'] ?? null) ? trim((string) $member['fullName']) : '';
    $email = is_string($member['email'] ?? null) ? trim((string) $member['email']) : '';
    $label = $name !== '' && $email !== '' ? $name . ' - ' . $email : ($email !== '' ? $email : 'Membre #' . (int) $member['id']);
    $memberEmails[(int) $member['id']] = $label;
}

$messageAuthor = static function (int $senderId) use ($currentUserId, $memberEmails): string {
    if ($senderId === $currentUserId) {
        return 'Moi';
    }

    return $memberEmails[$senderId] ?? ('Membre #' . $senderId);
};

$memberLabels = [(string) $currentUserId => 'Moi'];
foreach (array_keys($memberEmails) as $memberId) {
    $memberLabels[(string) $memberId] = $messageAuthor($memberId);
}
$memberLabelsJson = json_encode($memberLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$lastMessageId = 0;
$firstMessageId = 0;
foreach ($messages as $message) {
    if (!is_array($message) || !is_numeric($message['id'] ?? null)) {
        continue;
    }

    $messageId = (int) $message['id'];
    $lastMessageId = max($lastMessageId, $messageId);
    $firstMessageId = $firstMessageId === 0 ? $messageId : min($firstMessageId, $messageId);
}

$timelineCursors = is_array($timeline['cursors'] ?? null) ? $timeline['cursors'] : [];
$beforeCursor = is_numeric($timelineCursors['before'] ?? null) ? (int) $timelineCursors['before'] : $firstMessageId;
$hasMoreBefore = (bool) ($timeline['hasMoreBefore'] ?? false);
$title = $conversationTitle($conversation);
$conversationType = is_string($conversation['type'] ?? null) ? (string) $conversation['type'] : 'direct';
$conversationTypeLabel = $conversationType === 'group' ? 'Groupe' : 'Directe';
$notificationMode = is_string($notificationPreference['mode'] ?? null) ? (string) $notificationPreference['mode'] : 'notify';
$notificationLabels = [
    'notify' => 'Notifier',
    'muted' => 'Muet',
    'digest' => 'Digest futur',
    'never' => 'Jamais',
];
?>
<section
  class="private-discussion-module private-discussion-conversation"
  data-private-discussion-app
  data-api-messages-url="<?php echo $h($apiMessagesUrl); ?>"
  data-api-events-url="<?php echo $h($apiEventsUrl); ?>"
  data-api-client-events-url="<?php echo $h($apiClientEventsUrl); ?>"
  data-api-read-url="<?php echo $h($apiReadUrl); ?>"
  data-api-keys-url="<?php echo $h($apiKeysUrl); ?>"
  data-api-devices-url="<?php echo $h($apiDevicesUrl); ?>"
  data-csrf-token="<?php echo $h($csrfToken); ?>"
  data-files-url="<?php echo $h($filesUrl); ?>"
  data-conversation-id="<?php echo $h((string) $conversationId); ?>"
  data-current-user-id="<?php echo $h((string) $currentUserId); ?>"
  data-member-labels="<?php echo $h(is_string($memberLabelsJson) ? $memberLabelsJson : '{}'); ?>"
  data-last-message-id="<?php echo $h((string) $lastMessageId); ?>"
  data-before-message-id="<?php echo $h((string) $beforeCursor); ?>"
  data-has-more-before="<?php echo $hasMoreBefore ? '1' : '0'; ?>"
>
  <nav class="private-module-nav" aria-label="Navigation discussions">
    <div class="private-module-nav-row">
      <a href="<?php echo $h($indexUrl); ?>">Conversations</a>
      <a class="active" href="<?php echo $h($conversationUrl); ?>">Fil actif</a>
    </div>
  </nav>

  <?php if ($notice !== ''): ?>
    <?php
    $noticeMessage = match ($notice) {
        'sent' => 'Message envoyé.',
        'deleted' => 'Suppression effectuée.',
        'notifications_updated' => 'Préférence de notification enregistrée.',
        default => $notice,
    };
    $isToastNotice = $notice === 'sent';
    ?>
    <p class="notice notice-success<?php echo $isToastNotice ? ' private-toast-notice' : ''; ?>"<?php echo $isToastNotice ? ' data-private-toast role="status" aria-live="polite"' : ''; ?>>
      <?php echo $h($noticeMessage); ?>
    </p>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <p class="notice notice-error">
      <?php
      $errorMessage = match ($error) {
          'csrf' => 'Session expiree, veuillez recommencer.',
          'rate_limited' => 'Trop de messages successifs, veuillez patienter.',
          'delete' => 'Suppression impossible.',
          'notification' => 'Préférence de notification impossible.',
          default => 'Le message n\'a pas pu etre envoye.',
      };
      echo $h($errorMessage);
      ?>
    </p>
  <?php endif; ?>

  <div class="private-discussion-shell">
    <aside class="private-discussion-sidebar" aria-label="Conversations et détails">
      <section class="private-discussion-sidebar-section">
        <div class="private-discussion-sidebar-heading">
          <span class="tag">Messages</span>
          <h2>Conversations</h2>
        </div>
        <?php if ($conversations === []): ?>
          <p class="muted">Aucune conversation.</p>
        <?php else: ?>
          <ul class="private-discussion-conversation-list">
            <?php foreach ($conversations as $row): ?>
              <?php if (!is_array($row) || !is_numeric($row['id'] ?? null)) { continue; } ?>
              <?php
              $rowId = (int) $row['id'];
              $rowType = is_string($row['type'] ?? null) ? (string) $row['type'] : 'direct';
              $rowUnreadCount = max(0, (int) ($row['unreadCount'] ?? 0));
              $rowLastBody = is_string($row['lastBody'] ?? null) ? trim((string) $row['lastBody']) : '';
              $rowTitle = $conversationTitle($row);
              ?>
              <li>
                <a class="<?php echo $rowId === $conversationId ? 'active' : ''; ?>" href="<?php echo $h(rtrim($indexUrl, '/') . '/' . $rowId); ?>"<?php echo $rowId === $conversationId ? ' aria-current="page"' : ''; ?>>
                  <span class="private-discussion-conversation-title"><?php echo $h($rowTitle); ?></span>
                  <span class="private-discussion-conversation-meta">
                    <?php echo $h($rowType === 'group' ? 'Groupe' : 'Directe'); ?>
                    <?php if ($rowUnreadCount > 0): ?>
                      <strong><?php echo $h((string) $rowUnreadCount); ?></strong>
                    <?php endif; ?>
                  </span>
                  <span class="private-discussion-conversation-preview"><?php echo $h($rowLastBody !== '' ? $shortText($rowLastBody, 48) : 'Aucun message'); ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

      <section class="private-discussion-sidebar-section">
        <div class="private-discussion-sidebar-heading">
          <span class="tag"><?php echo $h($conversationTypeLabel); ?></span>
          <h2>Détails</h2>
        </div>
        <?php if ($conversationMembers !== []): ?>
          <ul class="private-discussion-member-list">
            <?php foreach ($conversationMembers as $member): ?>
              <?php if (!is_array($member) || !is_numeric($member['id'] ?? null)) { continue; } ?>
              <?php $memberId = (int) $member['id']; ?>
              <li>
                <span><?php echo $h($messageAuthor($memberId)); ?></span>
                <?php if ($memberId === $currentUserId): ?>
                  <strong>Vous</strong>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <form class="private-discussion-notification-form" method="post" action="<?php echo $h($conversationUrl); ?>">
          <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
          <input type="hidden" name="action" value="update_notification_preference" />
          <label for="discussion-notification-mode">Notifications</label>
          <select id="discussion-notification-mode" name="notification_mode">
            <?php foreach ($notificationLabels as $mode => $label): ?>
              <option value="<?php echo $h($mode); ?>"<?php echo $notificationMode === $mode ? ' selected' : ''; ?>><?php echo $h($label); ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="private-button-secondary">Enregistrer</button>
        </form>
      </section>

      <?php if ($mediaGallery !== []): ?>
        <section class="private-discussion-sidebar-section">
          <div class="private-discussion-sidebar-heading">
            <span class="tag">Fichiers</span>
            <h2>Galerie</h2>
          </div>
          <ul class="private-discussion-media-gallery">
            <?php foreach ($mediaGallery as $media): ?>
              <?php if (!is_array($media)) { continue; } ?>
              <?php
              $mediaId = is_string($media['attachmentId'] ?? null) ? (string) $media['attachmentId'] : '';
              $mediaName = is_string($media['originalFilename'] ?? null) ? (string) $media['originalFilename'] : 'piece-jointe';
              $mediaMime = is_string($media['mimeType'] ?? null) ? (string) $media['mimeType'] : '';
              if ($mediaId === '') {
                  continue;
              }
              $mediaHref = $filesUrl . '/' . rawurlencode($mediaId);
              $mediaPreviewHref = $mediaHref . '/preview';
              ?>
              <li>
                <a href="<?php echo $h($mediaHref); ?>">
                  <?php if (str_starts_with($mediaMime, 'image/')): ?>
                    <img src="<?php echo $h($mediaPreviewHref); ?>" alt="<?php echo $h($mediaName); ?>" loading="lazy" />
                  <?php else: ?>
                    <span aria-hidden="true">Fichier</span>
                  <?php endif; ?>
                  <span><?php echo $h($shortText($mediaName, 42)); ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endif; ?>

      <aside class="notice private-discussion-security" aria-label="<?php echo $h($translate('TXT_PRIVATE_DISCUSSION_SECURITY_TITLE', 'Chiffrement des discussions')); ?>">
        <strong><?php echo $h($translate('TXT_PRIVATE_DISCUSSION_SECURITY_TITLE', 'Chiffrement des discussions')); ?></strong>
        <ul>
          <li><?php echo $h($translate('TXT_PRIVATE_DISCUSSION_SECURITY_TEXT', 'Les nouveaux messages texte sont chiffrés dans le navigateur avant envoi: le serveur ne stocke pas leur corps en clair.')); ?></li>
          <li><?php echo $h($translate('TXT_PRIVATE_DISCUSSION_SECURITY_FILES', 'Les images et fichiers joints sont chiffrés sur disque côté serveur, stockés hors webroot, puis déchiffrés seulement lors d’un téléchargement autorisé.')); ?></li>
          <li><?php echo $h($translate('TXT_PRIVATE_DISCUSSION_SECURITY_METADATA', 'Les métadonnées techniques restent nécessaires au fonctionnement: participants, dates, titres de groupes, noms de fichiers, types et tailles.')); ?></li>
          <li><?php echo $h($translate('TXT_PRIVATE_DISCUSSION_SECURITY_RETENTION', 'Les messages et fichiers gardent une rétention courte de 60 jours, avec purge automatique et suppression manuelle possible par message.')); ?></li>
        </ul>
      </aside>
    </aside>

    <section class="private-discussion-thread" aria-labelledby="private-discussion-thread-title">
      <div class="private-discussion-thread-panel">
        <header class="private-discussion-thread-header">
          <div>
            <span class="tag"><?php echo $h($conversationTypeLabel); ?></span>
            <h2 id="private-discussion-thread-title"><?php echo $h($title); ?></h2>
          </div>
          <label class="private-discussion-search">
            <span>Recherche locale</span>
            <input type="search" placeholder="Messages chargés" data-discussion-local-search />
          </label>
        </header>

        <div class="private-discussion-timeline">
          <button class="private-button-secondary private-discussion-load-more" type="button" data-discussion-load-before<?php echo $hasMoreBefore ? '' : ' hidden'; ?>>
            Charger les messages précédents
          </button>

          <div class="private-discussion-messages" id="discussion-messages" data-discussion-messages>
            <?php if ($messages === []): ?>
              <p class="muted" id="discussion-empty">Aucun message pour le moment.</p>
            <?php else: ?>
              <?php foreach ($messages as $message): ?>
                <?php if (!is_array($message) || !is_numeric($message['id'] ?? null)) { continue; } ?>
                <?php
                $senderId = is_numeric($message['senderPrivateUserId'] ?? null) ? (int) $message['senderPrivateUserId'] : 0;
                $body = is_string($message['body'] ?? null) ? (string) $message['body'] : '';
                $encryptionMode = is_string($message['encryptionMode'] ?? null) ? (string) $message['encryptionMode'] : 'none';
                $encryptedPayload = is_string($message['encryptedPayload'] ?? null) ? (string) $message['encryptedPayload'] : '';
                $encryptionMetadata = is_string($message['encryptionMetadata'] ?? null) ? (string) $message['encryptionMetadata'] : '';
                $attachments = is_array($message['attachments'] ?? null) ? $message['attachments'] : [];
                $messageId = (int) $message['id'];
                $canDeleteMessage = $senderId === $currentUserId || $isConversationOwner;
                $isLastMessage = $messageId === $lastMessageId;
                ?>
                <article
                  class="notice private-discussion-message<?php echo $senderId === $currentUserId ? ' private-discussion-message-own' : ''; ?>"
                  <?php echo $isLastMessage ? 'id="discussion-message-last" data-discussion-last-message="1"' : ''; ?>
                  data-message-id="<?php echo $h((string) $messageId); ?>"
                  data-sender-id="<?php echo $h((string) $senderId); ?>"
                  data-created-at="<?php echo $h((string) ($message['createdAt'] ?? '')); ?>"
                  data-encryption-mode="<?php echo $h($encryptionMode); ?>"
                  data-encrypted-payload="<?php echo $h($encryptedPayload); ?>"
                  data-encryption-metadata="<?php echo $h($encryptionMetadata); ?>"
                  data-search-text="<?php echo $h($encryptionMode === 'none' ? $body : ''); ?>"
                >
                  <div class="private-discussion-message-header">
                    <p class="muted private-discussion-message-meta">
                      <strong><?php echo $h($messageAuthor($senderId)); ?></strong>
                      <span><?php echo $h($formatDate($message['createdAt'] ?? '')); ?></span>
                    </p>
                    <?php if ($canDeleteMessage): ?>
                      <form class="private-discussion-delete-form" method="post" action="<?php echo $h($conversationUrl); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
                        <input type="hidden" name="action" value="delete_message" />
                        <input type="hidden" name="message_id" value="<?php echo $h((string) $messageId); ?>" />
                        <button class="private-discussion-delete-button" type="submit" aria-label="Supprimer le message">Supprimer</button>
                      </form>
                    <?php endif; ?>
                  </div>
                  <?php if ($encryptionMode !== 'none'): ?>
                    <p data-encrypted-body>Message chiffre sur cet appareil.</p>
                  <?php elseif ($body !== ''): ?>
                    <p><?php echo nl2br($h($body)); ?></p>
                  <?php endif; ?>
                  <?php if ($attachments !== []): ?>
                    <ul class="private-discussion-attachments">
                      <?php foreach ($attachments as $attachment): ?>
                        <?php if (!is_array($attachment)) { continue; } ?>
                        <?php
                        $attachmentId = is_string($attachment['attachmentId'] ?? null) ? (string) $attachment['attachmentId'] : '';
                        $filename = is_string($attachment['originalFilename'] ?? null) ? (string) $attachment['originalFilename'] : 'piece-jointe';
                        $mimeType = is_string($attachment['mimeType'] ?? null) ? (string) $attachment['mimeType'] : '';
                        if ($attachmentId === '') {
                            continue;
                        }
                        $fileHref = $filesUrl . '/' . rawurlencode($attachmentId);
                        $previewHref = $fileHref . '/preview';
                        ?>
                        <li>
                          <?php if (str_starts_with($mimeType, 'image/')): ?>
                            <a href="<?php echo $h($fileHref); ?>">
                              <img class="discussion-attachment-preview" src="<?php echo $h($previewHref); ?>" alt="<?php echo $h($filename); ?>" loading="lazy" />
                            </a>
                          <?php endif; ?>
                          <a href="<?php echo $h($fileHref); ?>"><?php echo $h($filename); ?></a>
                          <?php if ($canDeleteMessage): ?>
                            <form method="post" action="<?php echo $h($conversationUrl); ?>">
                              <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
                              <input type="hidden" name="action" value="delete_attachment" />
                              <input type="hidden" name="attachment_id" value="<?php echo $h($attachmentId); ?>" />
                              <button class="button-small button-danger" type="submit">Supprimer la pièce jointe</button>
                            </form>
                          <?php endif; ?>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <section class="private-discussion-composer" aria-label="Envoyer un message">
          <p class="notice" id="discussion-crypto-status" data-discussion-crypto-status>Initialisation du chiffrement local...</p>
          <div class="private-discussion-device-panel" data-discussion-device-panel hidden>
            <p class="muted" data-discussion-device-summary></p>
            <div class="private-discussion-device-actions">
              <button type="button" class="private-button-secondary" data-discussion-regenerate-keys>Régénérer les clés</button>
              <button type="button" class="private-discussion-delete-button" data-discussion-revoke-device>Révoquer cet appareil</button>
            </div>
          </div>
          <form id="discussion-message-form" class="private-discussion-message-form" method="post" action="<?php echo $h($conversationUrl); ?>" enctype="multipart/form-data" data-discussion-message-form>
            <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>" />
            <input type="hidden" name="action" value="send_message" />
            <input type="hidden" name="client_message_id" value="" />
            <input type="hidden" name="encryption_mode" value="" />
            <input type="hidden" name="encrypted_payload" value="" />
            <input type="hidden" name="encryption_metadata" value="" />
            <div class="private-discussion-textbox">
              <div class="private-discussion-textbar">
                <label for="discussion-message-body">Message</label>
                <button
                  type="button"
                  class="private-button-secondary private-discussion-emoji-toggle"
                  data-discussion-emoji-toggle
                  aria-expanded="false"
                  aria-controls="discussion-emoji-picker"
                >Émojis</button>
              </div>
              <textarea id="discussion-message-body" name="body" rows="3" maxlength="4000" data-discussion-draft></textarea>
              <div
                id="discussion-emoji-picker"
                class="private-discussion-emoji-picker"
                data-discussion-emoji-picker
                role="group"
                aria-label="Insérer un émoji"
                hidden
              ></div>
            </div>
            <div class="private-discussion-upload" data-discussion-dropzone>
              <label for="discussion-message-files">Images ou fichiers</label>
              <input id="discussion-message-files" type="file" name="discussion_files[]" multiple data-discussion-file-input />
              <p class="muted">Déposez un fichier ici, collez une image, ou choisissez un fichier.</p>
              <ul class="private-discussion-file-preview" data-discussion-file-preview aria-live="polite"></ul>
            </div>
            <p class="muted"><?php echo $h($translate('TXT_PRIVATE_DISCUSSION_FORM_SECURITY_HELP', 'Les messages texte sont chiffrés dans le navigateur. Les fichiers joints sont chiffrés sur disque et restent servis uniquement par contrôle d’accès serveur.')); ?></p>
            <p class="muted private-discussion-submit-status" data-discussion-submit-status role="status" aria-live="polite"></p>
            <button type="submit" data-discussion-submit-button>Envoyer</button>
          </form>
        </section>
      </div>
    </section>
  </div>

  <?php echo function_exists('vite_tags') ? vite_tags('src/js/private-discussion.ts') : ''; ?>
</section>
