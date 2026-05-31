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
$isConversationOwner = is_numeric($conversation['createdByPrivateUserId'] ?? null)
    && (int) $conversation['createdByPrivateUserId'] === $currentUserId;
$apiMessagesUrl = rtrim((string) ($urls['apiConversations'] ?? private_portal_url('discussion_api_conversations')), '/') . '/' . $conversationId . '/messages';
$apiEventsUrl = rtrim((string) ($urls['apiConversations'] ?? private_portal_url('discussion_api_conversations')), '/') . '/' . $conversationId . '/events';
$apiReadUrl = rtrim((string) ($urls['apiConversations'] ?? private_portal_url('discussion_api_conversations')), '/') . '/' . $conversationId . '/read';
$apiKeysUrl = rtrim((string) ($urls['apiConversations'] ?? private_portal_url('discussion_api_conversations')), '/') . '/' . $conversationId . '/keys';
$apiDevicesUrl = (string) ($urls['apiCryptoDevices'] ?? private_portal_url('discussion_api_crypto_devices'));
$notice = is_string($viewModel['notice'] ?? null) ? (string) $viewModel['notice'] : '';
$error = is_string($viewModel['error'] ?? null) ? (string) $viewModel['error'] : '';
$memberEmails = [];
foreach ($members as $member) {
    if (!is_array($member) || !is_numeric($member['id'] ?? null)) {
        continue;
    }

    $memberEmails[(int) $member['id']] = (string) ($member['email'] ?? ('Membre #' . (int) $member['id']));
}

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
$title = $conversationTitle($conversation);

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
  <nav class="private-module-nav" aria-label="Navigation discussions">
    <div class="private-module-nav-row">
      <a href="<?php echo htmlspecialchars($indexUrl, ENT_QUOTES, 'UTF-8'); ?>">Tableau de bord</a>
      <a class="active" href="<?php echo htmlspecialchars($conversationUrl, ENT_QUOTES, 'UTF-8'); ?>">Conversation</a>
    </div>
  </nav>

  <aside class="notice private-discussion-security" aria-label="<?php echo htmlspecialchars($translate('TXT_PRIVATE_DISCUSSION_SECURITY_TITLE', 'Chiffrement des discussions'), ENT_QUOTES, 'UTF-8'); ?>">
    <strong><?php echo htmlspecialchars($translate('TXT_PRIVATE_DISCUSSION_SECURITY_TITLE', 'Chiffrement des discussions'), ENT_QUOTES, 'UTF-8'); ?></strong>
    <ul>
      <li><?php echo htmlspecialchars($translate('TXT_PRIVATE_DISCUSSION_SECURITY_TEXT', 'Les nouveaux messages texte sont chiffrés dans le navigateur avant envoi: le serveur ne stocke pas leur corps en clair.'), ENT_QUOTES, 'UTF-8'); ?></li>
      <li><?php echo htmlspecialchars($translate('TXT_PRIVATE_DISCUSSION_SECURITY_FILES', 'Les images et fichiers joints sont chiffrés sur disque côté serveur, stockés hors webroot, puis déchiffrés seulement lors d’un téléchargement autorisé.'), ENT_QUOTES, 'UTF-8'); ?></li>
      <li><?php echo htmlspecialchars($translate('TXT_PRIVATE_DISCUSSION_SECURITY_METADATA', 'Les métadonnées techniques restent nécessaires au fonctionnement: participants, dates, titres de groupes, noms de fichiers, types et tailles.'), ENT_QUOTES, 'UTF-8'); ?></li>
      <li><?php echo htmlspecialchars($translate('TXT_PRIVATE_DISCUSSION_SECURITY_RETENTION', 'Les messages et fichiers gardent une rétention courte de 60 jours, avec purge automatique et suppression manuelle possible par message.'), ENT_QUOTES, 'UTF-8'); ?></li>
    </ul>
  </aside>

  <?php if ($notice !== ''): ?>
    <?php
    $noticeMessage = match ($notice) {
        'sent' => 'Message envoyé.',
        'deleted' => 'Suppression effectuée.',
        default => $notice,
    };
    $isToastNotice = $notice === 'sent';
    ?>
    <p class="notice notice-success<?php echo $isToastNotice ? ' private-toast-notice' : ''; ?>"<?php echo $isToastNotice ? ' data-private-toast role="status" aria-live="polite"' : ''; ?>>
      <?php echo htmlspecialchars($noticeMessage, ENT_QUOTES, 'UTF-8'); ?>
    </p>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <p class="notice notice-error">
      <?php
      $errorMessage = match ($error) {
          'csrf' => 'Session expiree, veuillez recommencer.',
          'rate_limited' => 'Trop de messages successifs, veuillez patienter.',
          'delete' => 'Suppression impossible.',
          default => 'Le message n\'a pas pu etre envoye.',
      };
      echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8');
      ?>
    </p>
  <?php endif; ?>

  <section class="card private-card-wide">
    <h2><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h2>
    <div id="discussion-messages" data-current-user-id="<?php echo htmlspecialchars((string) $currentUserId, ENT_QUOTES, 'UTF-8'); ?>" data-files-url="<?php echo htmlspecialchars($filesUrl, ENT_QUOTES, 'UTF-8'); ?>" data-conversation-id="<?php echo htmlspecialchars((string) $conversationId, ENT_QUOTES, 'UTF-8'); ?>">
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
            class="notice"
            <?php echo $isLastMessage ? 'id="discussion-message-last" data-discussion-last-message="1"' : ''; ?>
            data-message-id="<?php echo htmlspecialchars((string) $messageId, ENT_QUOTES, 'UTF-8'); ?>"
            data-encryption-mode="<?php echo htmlspecialchars($encryptionMode, ENT_QUOTES, 'UTF-8'); ?>"
            data-encrypted-payload="<?php echo htmlspecialchars($encryptedPayload, ENT_QUOTES, 'UTF-8'); ?>"
            data-encryption-metadata="<?php echo htmlspecialchars($encryptionMetadata, ENT_QUOTES, 'UTF-8'); ?>"
          >
            <div class="private-discussion-message-header">
              <p class="muted private-discussion-message-meta">
                <strong><?php echo htmlspecialchars($messageAuthor($senderId), ENT_QUOTES, 'UTF-8'); ?></strong>
                · <?php echo htmlspecialchars($formatDate($message['createdAt'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
              </p>
              <?php if ($canDeleteMessage): ?>
                <form class="private-discussion-delete-form" method="post" action="<?php echo htmlspecialchars($conversationUrl, ENT_QUOTES, 'UTF-8'); ?>">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
                  <input type="hidden" name="action" value="delete_message" />
                  <input type="hidden" name="message_id" value="<?php echo htmlspecialchars((string) $messageId, ENT_QUOTES, 'UTF-8'); ?>" />
                  <button class="private-discussion-delete-button" type="submit" aria-label="Supprimer le message">Supprimer</button>
                </form>
              <?php endif; ?>
            </div>
            <?php if ($encryptionMode !== 'none'): ?>
              <p data-encrypted-body>Message chiffre sur cet appareil.</p>
            <?php elseif ($body !== ''): ?>
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
                        <img class="discussion-attachment-preview" src="<?php echo htmlspecialchars($previewHref, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($filename, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" />
                      </a>
                    <?php endif; ?>
                    <a href="<?php echo htmlspecialchars($fileHref, ENT_QUOTES, 'UTF-8'); ?>">
                      <?php echo htmlspecialchars($filename, ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                    <?php if ($canDeleteMessage): ?>
                      <form method="post" action="<?php echo htmlspecialchars($conversationUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
                        <input type="hidden" name="action" value="delete_attachment" />
                        <input type="hidden" name="attachment_id" value="<?php echo htmlspecialchars($attachmentId, ENT_QUOTES, 'UTF-8'); ?>" />
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
  </section>

  <section class="card private-card-wide">
    <h2>Envoyer un message</h2>
    <p class="notice" id="discussion-crypto-status">Initialisation du chiffrement local...</p>
    <form id="discussion-message-form" method="post" action="<?php echo htmlspecialchars($conversationUrl, ENT_QUOTES, 'UTF-8'); ?>" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
      <input type="hidden" name="action" value="send_message" />
      <input type="hidden" name="client_message_id" value="" />
      <input type="hidden" name="encryption_mode" value="" />
      <input type="hidden" name="encrypted_payload" value="" />
      <input type="hidden" name="encryption_metadata" value="" />
      <label for="discussion-message-body">Message</label>
      <textarea id="discussion-message-body" name="body" rows="4" maxlength="4000"></textarea>
      <label for="discussion-message-files">Images ou fichiers</label>
      <input id="discussion-message-files" type="file" name="discussion_files[]" multiple />
      <p class="muted"><?php echo htmlspecialchars($translate('TXT_PRIVATE_DISCUSSION_FORM_SECURITY_HELP', 'Les messages texte sont chiffrés dans le navigateur. Les fichiers joints sont chiffrés sur disque et restent servis uniquement par contrôle d’accès serveur.'), ENT_QUOTES, 'UTF-8'); ?></p>
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
    const eventsUrl = <?php echo json_encode($apiEventsUrl, JSON_UNESCAPED_SLASHES); ?>;
    const readUrl = <?php echo json_encode($apiReadUrl, JSON_UNESCAPED_SLASHES); ?>;
    const keysUrl = <?php echo json_encode($apiKeysUrl, JSON_UNESCAPED_SLASHES); ?>;
    const devicesUrl = <?php echo json_encode($apiDevicesUrl, JSON_UNESCAPED_SLASHES); ?>;
    const csrfToken = <?php echo json_encode($csrfToken, JSON_UNESCAPED_SLASHES); ?>;
    const filesUrl = root.dataset.filesUrl || '';
    const conversationId = Number(root.dataset.conversationId || 0);
    const currentUserId = Number(root.dataset.currentUserId || 0);
    const status = document.getElementById('discussion-crypto-status');
    const form = document.getElementById('discussion-message-form');
    let lastMessageElement = document.querySelector('[data-discussion-last-message]');
    const cryptoOk = window.isSecureContext && window.crypto && window.crypto.subtle && window.indexedDB;
    let cryptoState = null;
    let lastEventId = 0;

    const labelFor = (senderId) => senderId === currentUserId ? 'Moi' : `Membre #${senderId}`;
    const setStatus = (text, failure = false) => {
      if (!status) {
        return;
      }
      status.textContent = text;
      status.className = failure ? 'notice notice-error' : 'notice notice-success';
    };
    const bytesToBase64 = (buffer) => {
      let binary = '';
      const bytes = new Uint8Array(buffer);
      bytes.forEach((byte) => { binary += String.fromCharCode(byte); });
      return window.btoa(binary);
    };
    const base64ToBytes = (value) => {
      const binary = window.atob(String(value || ''));
      const bytes = new Uint8Array(binary.length);
      for (let index = 0; index < binary.length; index += 1) {
        bytes[index] = binary.charCodeAt(index);
      }
      return bytes;
    };
    const openCryptoDb = () => new Promise((resolve, reject) => {
      const request = indexedDB.open('caramagnols-family-discussion-crypto', 1);
      request.onupgradeneeded = () => {
        const db = request.result;
        if (!db.objectStoreNames.contains('items')) {
          db.createObjectStore('items', { keyPath: 'id' });
        }
      };
      request.onerror = () => reject(request.error);
      request.onsuccess = () => resolve(request.result);
    });
    const dbGet = (db, id) => new Promise((resolve, reject) => {
      const tx = db.transaction('items', 'readonly');
      const request = tx.objectStore('items').get(id);
      request.onerror = () => reject(request.error);
      request.onsuccess = () => resolve(request.result || null);
    });
    const dbPut = (db, value) => new Promise((resolve, reject) => {
      const tx = db.transaction('items', 'readwrite');
      const request = tx.objectStore('items').put(value);
      request.onerror = () => reject(request.error);
      tx.oncomplete = () => resolve(true);
    });
    const deviceId = () => {
      const storageKey = 'caramagnolsDiscussionDeviceId';
      let id = localStorage.getItem(storageKey) || '';
      if (!/^[A-Za-z0-9._-]{16,64}$/.test(id)) {
        id = Array.from(crypto.getRandomValues(new Uint8Array(24)))
          .map((byte) => byte.toString(16).padStart(2, '0'))
          .join('');
        localStorage.setItem(storageKey, id);
      }
      return id;
    };
    const registerDevice = async (db) => {
      const id = deviceId();
      let device = await dbGet(db, 'device');
      if (!device || device.deviceId !== id) {
        const pair = await crypto.subtle.generateKey(
          {
            name: 'RSA-OAEP',
            modulusLength: 2048,
            publicExponent: new Uint8Array([1, 0, 1]),
            hash: 'SHA-256'
          },
          false,
          ['encrypt', 'decrypt']
        );
        const publicKeyJwk = await crypto.subtle.exportKey('jwk', pair.publicKey);
        device = { id: 'device', deviceId: id, privateKey: pair.privateKey, publicKeyJwk };
        await dbPut(db, device);
      }
      await fetch(devicesUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
        body: new URLSearchParams({
          csrf_token: csrfToken,
          conversation_id: String(conversationId),
          device_id: id,
          device_label: navigator.userAgent.slice(0, 100),
          public_key_jwk: JSON.stringify(device.publicKeyJwk)
        }),
        credentials: 'same-origin'
      });
      return device;
    };
    const importAesKey = (raw) => crypto.subtle.importKey('raw', raw, { name: 'AES-GCM' }, true, ['encrypt', 'decrypt']);
    const exportAesKey = (key) => crypto.subtle.exportKey('raw', key);
    const fetchConversationKeys = async () => {
      const response = await fetch(keysUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      if (!response.ok) {
        return { keys: [], knownKeyCount: 0 };
      }
      const payload = await response.json();
      return {
        keys: Array.isArray(payload.keys) ? payload.keys : [],
        knownKeyCount: Number(payload.knownKeyCount || 0)
      };
    };
    const fetchConversationDevices = async () => {
      const response = await fetch(`${devicesUrl}?conversation_id=${conversationId}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      if (!response.ok) {
        return [];
      }
      const payload = await response.json();
      return Array.isArray(payload.devices) ? payload.devices : [];
    };
    const shareKey = async (aesKey, devices) => {
      const rawKey = await exportAesKey(aesKey);
      const wrappers = [];
      for (const device of devices) {
        try {
          const publicKey = await crypto.subtle.importKey(
            'jwk',
            JSON.parse(device.publicKeyJwk || '{}'),
            { name: 'RSA-OAEP', hash: 'SHA-256' },
            false,
            ['encrypt']
          );
          const encrypted = await crypto.subtle.encrypt({ name: 'RSA-OAEP' }, publicKey, rawKey);
          wrappers.push({
            privateUserId: Number(device.privateUserId || 0),
            deviceId: String(device.deviceId || ''),
            encryptedKey: bytesToBase64(encrypted)
          });
        } catch (error) {
        }
      }
      if (wrappers.length === 0) {
        return;
      }
      await fetch(keysUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json; charset=UTF-8', 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
        body: JSON.stringify({ csrf_token: csrfToken, keys: wrappers }),
        credentials: 'same-origin'
      });
    };
    const conversationKey = async () => {
      if (!cryptoOk || conversationId <= 0) {
        return null;
      }
      if (cryptoState && cryptoState.aesKey) {
        return cryptoState.aesKey;
      }
      const db = await openCryptoDb();
      const device = await registerDevice(db);
      const localKey = await dbGet(db, `conversation:${conversationId}`);
      if (localKey && localKey.aesKey) {
        cryptoState = { db, device, aesKey: localKey.aesKey };
        shareKey(localKey.aesKey, await fetchConversationDevices());
        return localKey.aesKey;
      }
      const keyPayload = await fetchConversationKeys();
      for (const wrapped of keyPayload.keys) {
        if (String(wrapped.deviceId || '') !== device.deviceId) {
          continue;
        }
        try {
          const raw = await crypto.subtle.decrypt({ name: 'RSA-OAEP' }, device.privateKey, base64ToBytes(wrapped.encryptedKey));
          const aesKey = await importAesKey(raw);
          await dbPut(db, { id: `conversation:${conversationId}`, aesKey });
          cryptoState = { db, device, aesKey };
          return aesKey;
        } catch (error) {
        }
      }
      if (keyPayload.knownKeyCount > 0) {
        return null;
      }
      const aesKey = await crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, true, ['encrypt', 'decrypt']);
      await dbPut(db, { id: `conversation:${conversationId}`, aesKey });
      await shareKey(aesKey, await fetchConversationDevices());
      cryptoState = { db, device, aesKey };
      return aesKey;
    };
    const encryptText = async (text) => {
      const key = await conversationKey();
      if (!key) {
        return null;
      }
      const iv = crypto.getRandomValues(new Uint8Array(12));
      const cipher = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, new TextEncoder().encode(text));
      return {
        payload: bytesToBase64(cipher),
        metadata: JSON.stringify({ algorithm: 'AES-GCM', iv: bytesToBase64(iv), version: 1 })
      };
    };
    const decryptText = async (payload, metadata) => {
      const key = await conversationKey();
      if (!key) {
        return null;
      }
      const meta = JSON.parse(metadata || '{}');
      const plain = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: base64ToBytes(meta.iv || '') }, key, base64ToBytes(payload));
      return new TextDecoder().decode(plain);
    };
    const decryptArticle = async (article) => {
      if (!article || article.dataset.encryptionMode !== 'client_aes_gcm_v1') {
        return;
      }
      const target = article.querySelector('[data-encrypted-body]');
      if (!target) {
        return;
      }
      try {
        const text = await decryptText(article.dataset.encryptedPayload || '', article.dataset.encryptionMetadata || '');
        target.textContent = text || 'Message chiffre illisible sur cet appareil.';
      } catch (error) {
        target.textContent = 'Message chiffre illisible sur cet appareil.';
      }
    };
    const appendText = (parent, tag, value, className = '') => {
      const element = document.createElement(tag);
      if (className !== '') {
        element.className = className;
      }
      element.textContent = value;
      parent.appendChild(element);
      return element;
    };
    const scrollToLastMessage = () => {
      if (lastMessageElement instanceof HTMLElement) {
        lastMessageElement.scrollIntoView({ block: 'center' });
      }
    };
    const randomClientId = () => {
      const bytes = new Uint8Array(16);
      crypto.getRandomValues(bytes);
      return Array.from(bytes).map((byte) => byte.toString(16).padStart(2, '0')).join('');
    };
    if (lastMessageElement instanceof HTMLElement) {
      window.requestAnimationFrame(scrollToLastMessage);
      window.setTimeout(scrollToLastMessage, 250);
    }
    document.querySelectorAll('[data-private-toast]').forEach((toast) => {
      window.setTimeout(() => {
        if (!(toast instanceof HTMLElement)) {
          return;
        }
        toast.classList.add('private-toast-notice-hidden');
        window.setTimeout(() => {
          toast.hidden = true;
        }, 320);
      }, 3200);
    });

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
      article.dataset.encryptionMode = String(message.encryptionMode || 'none');
      article.dataset.encryptedPayload = String(message.encryptedPayload || '');
      article.dataset.encryptionMetadata = String(message.encryptionMetadata || '');
      if (lastMessageElement instanceof HTMLElement) {
        lastMessageElement.removeAttribute('id');
        lastMessageElement.removeAttribute('data-discussion-last-message');
      }
      article.id = 'discussion-message-last';
      article.dataset.discussionLastMessage = '1';
      const meta = appendText(article, 'p', `${labelFor(Number(message.senderPrivateUserId || 0))} · ${message.createdAt || ''}`, 'muted');
      meta.style.fontWeight = '600';
      if (article.dataset.encryptionMode !== 'none') {
        const encrypted = appendText(article, 'p', 'Message chiffre sur cet appareil.');
        encrypted.setAttribute('data-encrypted-body', '1');
      } else if (message.body) {
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
      lastMessageElement = article;
      lastId = id;
      decryptArticle(article);
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

    if (form) {
      form.addEventListener('submit', async (event) => {
        const body = form.querySelector('textarea[name="body"]');
        const clientMessage = form.querySelector('input[name="client_message_id"]');
        const mode = form.querySelector('input[name="encryption_mode"]');
        const payload = form.querySelector('input[name="encrypted_payload"]');
        const metadata = form.querySelector('input[name="encryption_metadata"]');
        const text = body ? String(body.value || '').trim() : '';
        if (!text || !mode || !payload || !metadata) {
          return;
        }

        if (!cryptoOk) {
          event.preventDefault();
          setStatus('Chiffrement local indisponible sur ce navigateur. Message texte non envoye.', true);
          return;
        }

        event.preventDefault();
        try {
          const encrypted = await encryptText(text);
          if (!encrypted) {
            setStatus('Chiffrement local indisponible. Message non envoye.', true);
            return;
          }
          mode.value = 'client_aes_gcm_v1';
          payload.value = encrypted.payload;
          metadata.value = encrypted.metadata;
          if (clientMessage && !clientMessage.value) {
            clientMessage.value = randomClientId();
          }
          body.value = '';
          form.submit();
        } catch (error) {
          setStatus('Chiffrement local impossible. Message non envoye.', true);
        }
      });
    }

    if (!cryptoOk) {
      setStatus('Chiffrement local indisponible sur ce navigateur. Les messages texte sont bloques.', true);
    } else {
      conversationKey()
        .then((key) => {
          setStatus(key ? 'Chiffrement local actif pour les messages texte.' : 'Chiffrement local non initialise.', !key);
          document.querySelectorAll('[data-encryption-mode="client_aes_gcm_v1"]').forEach((article) => decryptArticle(article));
        })
        .catch(() => setStatus('Chiffrement local non initialise sur cet appareil.', true));
    }

    const connectEvents = () => {
      if (!window.EventSource || !eventsUrl) {
        return false;
      }

      const source = new EventSource(`${eventsUrl}?after_event_id=${lastEventId}`);
      const refresh = (event) => {
        lastEventId = Number(event.lastEventId || lastEventId || 0);
        poll();
      };
      source.addEventListener('message.created', refresh);
      source.addEventListener('message.deleted', refresh);
      source.addEventListener('attachment.deleted', refresh);
      source.addEventListener('member.added', refresh);
      source.addEventListener('member.left', refresh);
      source.onerror = () => {
        source.close();
        window.setTimeout(connectEvents, 10000);
      };
      return true;
    };

    const eventStreamActive = connectEvents();
    window.setInterval(poll, eventStreamActive ? 15000 : 5000);
  })();
  </script>
</section>
