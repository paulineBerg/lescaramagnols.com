<?php
$conversation = is_array($viewModel['conversation'] ?? null) ? $viewModel['conversation'] : [];
$messages = is_array($viewModel['messages'] ?? null) ? $viewModel['messages'] : [];
$members = is_array($viewModel['members'] ?? null) ? $viewModel['members'] : [];
$currentUserId = is_numeric($viewModel['discussionCurrentUserId'] ?? null) ? (int) $viewModel['discussionCurrentUserId'] : 0;
$csrfToken = is_string($viewModel['discussionCsrfToken'] ?? null) ? (string) $viewModel['discussionCsrfToken'] : '';
$urls = is_array($viewModel['discussionUrls'] ?? null) ? $viewModel['discussionUrls'] : [];
$indexUrl = (string) ($urls['index'] ?? private_portal_url('discussion_index'));
$filesUrl = rtrim((string) ($urls['files'] ?? private_portal_url('discussion_files')), '/');
$conversationId = is_numeric($conversation['id'] ?? null) ? (int) $conversation['id'] : 0;
$conversationUrl = $conversationId > 0 ? rtrim($indexUrl, '/') . '/' . $conversationId : $indexUrl;
$apiMessagesUrl = rtrim((string) ($urls['apiConversations'] ?? private_portal_url('discussion_api_conversations')), '/') . '/' . $conversationId . '/messages';
$apiReadUrl = rtrim((string) ($urls['apiConversations'] ?? private_portal_url('discussion_api_conversations')), '/') . '/' . $conversationId . '/read';
$notice = is_string($viewModel['notice'] ?? null) ? (string) $viewModel['notice'] : '';
$error = is_string($viewModel['error'] ?? null) ? (string) $viewModel['error'] : '';
$memberEmails = [];
foreach ($members as $member) {
    if (!is_array($member) || !is_numeric($member['id'] ?? null)) {
        continue;
    }

    $memberEmails[(int) $member['id']] = (string) ($member['email'] ?? ('Membre #' . (int) $member['id']));
}

$title = is_string($conversation['title'] ?? null) && trim((string) $conversation['title']) !== ''
    ? trim((string) $conversation['title'])
    : 'Conversation directe';

$formatDate = static function (mixed $value): string {
    $raw = is_string($value) ? trim($value) : '';
    if ($raw === '' || strtotime($raw) === false) {
        return '';
    }

    return date('d/m/Y H:i', (int) strtotime($raw));
};

$messageAuthor = static function (int $senderId) use ($currentUserId, $memberEmails): string {
    if ($senderId === $currentUserId) {
        return 'Moi';
    }

    return $memberEmails[$senderId] ?? ('Membre #' . $senderId);
};

$lastMessageId = 0;
foreach ($messages as $message) {
    if (is_array($message) && is_numeric($message['id'] ?? null)) {
        $lastMessageId = max($lastMessageId, (int) $message['id']);
    }
}
$cspNonce = is_string($GLOBALS['csp_nonce'] ?? null) ? (string) $GLOBALS['csp_nonce'] : '';
?>
<section>
  <p class="muted">
    <a href="<?php echo htmlspecialchars($indexUrl, ENT_QUOTES, 'UTF-8'); ?>">Discussions</a>
    · <?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>
  </p>

  <?php if ($notice !== ''): ?>
    <p class="notice notice-success"><?php echo htmlspecialchars($notice === 'sent' ? 'Message envoye.' : $notice, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <p class="notice notice-error">
      <?php
      $errorMessage = match ($error) {
          'csrf' => 'Session expiree, veuillez recommencer.',
          'rate_limited' => 'Trop de messages successifs, veuillez patienter.',
          default => 'Le message n\'a pas pu etre envoye.',
      };
      echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8');
      ?>
    </p>
  <?php endif; ?>

  <section class="card private-card-wide">
    <h2><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h2>
    <div id="discussion-messages" data-current-user-id="<?php echo htmlspecialchars((string) $currentUserId, ENT_QUOTES, 'UTF-8'); ?>" data-files-url="<?php echo htmlspecialchars($filesUrl, ENT_QUOTES, 'UTF-8'); ?>">
      <?php if ($messages === []): ?>
        <p class="muted" id="discussion-empty">Aucun message pour le moment.</p>
      <?php else: ?>
        <?php foreach ($messages as $message): ?>
          <?php if (!is_array($message) || !is_numeric($message['id'] ?? null)) { continue; } ?>
          <?php
          $senderId = is_numeric($message['senderPrivateUserId'] ?? null) ? (int) $message['senderPrivateUserId'] : 0;
          $body = is_string($message['body'] ?? null) ? (string) $message['body'] : '';
          $attachments = is_array($message['attachments'] ?? null) ? $message['attachments'] : [];
          ?>
          <article class="notice" data-message-id="<?php echo htmlspecialchars((string) (int) $message['id'], ENT_QUOTES, 'UTF-8'); ?>">
            <p class="muted">
              <strong><?php echo htmlspecialchars($messageAuthor($senderId), ENT_QUOTES, 'UTF-8'); ?></strong>
              · <?php echo htmlspecialchars($formatDate($message['createdAt'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
            </p>
            <?php if ($body !== ''): ?>
              <p><?php echo nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')); ?></p>
            <?php endif; ?>
            <?php if ($attachments !== []): ?>
              <ul>
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
                      <a href="<?php echo htmlspecialchars($fileHref, ENT_QUOTES, 'UTF-8'); ?>">
                        <img src="<?php echo htmlspecialchars($previewHref, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($filename, ENT_QUOTES, 'UTF-8'); ?>" style="max-width: 220px; max-height: 160px; display: block; margin: 0.5rem 0;" loading="lazy" />
                      </a>
                    <?php endif; ?>
                    <a href="<?php echo htmlspecialchars($fileHref, ENT_QUOTES, 'UTF-8'); ?>">
                      <?php echo htmlspecialchars($filename, ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

  <section class="card private-card-wide">
    <h2>Envoyer un message</h2>
    <form method="post" action="<?php echo htmlspecialchars($conversationUrl, ENT_QUOTES, 'UTF-8'); ?>" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
      <label for="discussion-message-body">Message</label>
      <textarea id="discussion-message-body" name="body" rows="4" maxlength="4000"></textarea>
      <label for="discussion-message-files">Images ou fichiers</label>
      <input id="discussion-message-files" type="file" name="discussion_files[]" multiple />
      <button type="submit">Envoyer</button>
    </form>
  </section>

  <script<?php echo $cspNonce !== '' ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
  (() => {
    const root = document.getElementById('discussion-messages');
    if (!root) {
      return;
    }

    let lastId = <?php echo (int) $lastMessageId; ?>;
    const apiUrl = <?php echo json_encode($apiMessagesUrl, JSON_UNESCAPED_SLASHES); ?>;
    const readUrl = <?php echo json_encode($apiReadUrl, JSON_UNESCAPED_SLASHES); ?>;
    const csrfToken = <?php echo json_encode($csrfToken, JSON_UNESCAPED_SLASHES); ?>;
    const filesUrl = root.dataset.filesUrl || '';
    const currentUserId = Number(root.dataset.currentUserId || 0);

    const labelFor = (senderId) => senderId === currentUserId ? 'Moi' : `Membre #${senderId}`;
    const appendText = (parent, tag, value, className = '') => {
      const element = document.createElement(tag);
      if (className !== '') {
        element.className = className;
      }
      element.textContent = value;
      parent.appendChild(element);
      return element;
    };

    const appendMessage = (message) => {
      const id = Number(message.id || 0);
      if (id <= lastId || id <= 0) {
        return;
      }

      const empty = document.getElementById('discussion-empty');
      if (empty) {
        empty.remove();
      }

      const article = document.createElement('article');
      article.className = 'notice';
      article.dataset.messageId = String(id);
      const meta = appendText(article, 'p', `${labelFor(Number(message.senderPrivateUserId || 0))} · ${message.createdAt || ''}`, 'muted');
      meta.style.fontWeight = '600';
      if (message.body) {
        appendText(article, 'p', String(message.body));
      }

      const attachments = Array.isArray(message.attachments) ? message.attachments : [];
      if (attachments.length > 0) {
        const list = document.createElement('ul');
        attachments.forEach((attachment) => {
          if (!attachment || !attachment.attachmentId) {
            return;
          }
          const item = document.createElement('li');
          const href = `${filesUrl}/${encodeURIComponent(String(attachment.attachmentId))}`;
          if (String(attachment.mimeType || '').startsWith('image/')) {
            const image = document.createElement('img');
            image.src = `${href}/preview`;
            image.alt = String(attachment.originalFilename || 'piece-jointe');
            image.loading = 'lazy';
            image.style.maxWidth = '220px';
            image.style.maxHeight = '160px';
            image.style.display = 'block';
            image.style.margin = '0.5rem 0';
            item.appendChild(image);
          }
          const link = document.createElement('a');
          link.href = href;
          link.textContent = String(attachment.originalFilename || 'piece-jointe');
          item.appendChild(link);
          list.appendChild(item);
        });
        article.appendChild(list);
      }

      root.appendChild(article);
      lastId = id;
    };

    const poll = async () => {
      try {
        const response = await fetch(`${apiUrl}?after_message_id=${lastId}`, {
          headers: { Accept: 'application/json' },
          credentials: 'same-origin'
        });
        if (!response.ok) {
          return;
        }
        const payload = await response.json();
        (Array.isArray(payload.messages) ? payload.messages : []).forEach(appendMessage);
        await fetch(readUrl, {
          method: 'POST',
          headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
          credentials: 'same-origin'
        });
      } catch (error) {
      }
    };

    window.setInterval(poll, 5000);
  })();
  </script>
</section>
