type ConversationKeyPayload = {
  keys: Array<Record<string, unknown>>;
  knownKeyCount: number;
};

type DiscussionMessage = {
  id?: number | string;
  senderPrivateUserId?: number | string;
  body?: string;
  createdAt?: string;
  encryptionMode?: string;
  encryptedPayload?: string;
  encryptionMetadata?: string;
  attachments?: Array<Record<string, unknown>>;
};

type DiscussionEventPayload = {
  type?: string;
  payload?: Record<string, unknown>;
};

type CryptoState = {
  db: IDBDatabase;
  device: {
    deviceId: string;
    privateKey: CryptoKey;
    publicKeyJwk: JsonWebKey;
    trustStatus: string;
  };
  aesKey: CryptoKey;
};

const textEncoder = new TextEncoder();
const textDecoder = new TextDecoder();

const normalize = (value: string): string => {
  return value
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim();
};

const bytesToBase64 = (buffer: ArrayBuffer): string => {
  let binary = '';
  const bytes = new Uint8Array(buffer);
  bytes.forEach((byte) => {
    binary += String.fromCharCode(byte);
  });

  return window.btoa(binary);
};

const base64ToBytes = (value: string): Uint8Array => {
  const binary = window.atob(String(value || ''));
  const bytes = new Uint8Array(binary.length);
  for (let index = 0; index < binary.length; index += 1) {
    bytes[index] = binary.charCodeAt(index);
  }

  return bytes;
};

const fileSizeLabel = (size: number): string => {
  if (size >= 1024 * 1024) {
    return `${(size / (1024 * 1024)).toFixed(1)} Mo`;
  }

  if (size >= 1024) {
    return `${Math.ceil(size / 1024)} Ko`;
  }

  return `${size} o`;
};

const openCryptoDb = (): Promise<IDBDatabase> => new Promise((resolve, reject) => {
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

const dbGet = (db: IDBDatabase, id: string): Promise<Record<string, unknown> | null> => new Promise((resolve, reject) => {
  const tx = db.transaction('items', 'readonly');
  const request = tx.objectStore('items').get(id);
  request.onerror = () => reject(request.error);
  request.onsuccess = () => resolve((request.result as Record<string, unknown> | undefined) || null);
});

const dbPut = (db: IDBDatabase, value: Record<string, unknown>): Promise<boolean> => new Promise((resolve, reject) => {
  const tx = db.transaction('items', 'readwrite');
  const request = tx.objectStore('items').put(value);
  request.onerror = () => reject(request.error);
  tx.oncomplete = () => resolve(true);
});

const dbDelete = (db: IDBDatabase, id: string): Promise<boolean> => new Promise((resolve, reject) => {
  const tx = db.transaction('items', 'readwrite');
  const request = tx.objectStore('items').delete(id);
  request.onerror = () => reject(request.error);
  tx.oncomplete = () => resolve(true);
});

const secureRandomHex = (length = 16): string => {
  if (window.crypto?.getRandomValues) {
    const bytes = new Uint8Array(length);
    window.crypto.getRandomValues(bytes);

    return Array.from(bytes).map((byte) => byte.toString(16).padStart(2, '0')).join('');
  }

  return `${Date.now().toString(16)}${Math.random().toString(16).slice(2)}`;
};

const appendText = (parent: Element, tag: string, value: string, className = ''): HTMLElement => {
  const element = document.createElement(tag);
  if (className !== '') {
    element.className = className;
  }
  element.textContent = value;
  parent.appendChild(element);

  return element;
};

const parseMemberLabels = (root: HTMLElement): Record<string, string> => {
  try {
    const labels = JSON.parse(root.dataset.memberLabels || '{}');
    return labels && typeof labels === 'object' && !Array.isArray(labels)
      ? labels as Record<string, string>
      : {};
  } catch (_error) {
    return {};
  }
};

const initPrivateDiscussion = (root: HTMLElement): void => {
  const messagesRoot = root.querySelector<HTMLElement>('[data-discussion-messages]');
  const form = root.querySelector<HTMLFormElement>('[data-discussion-message-form]');
  const status = root.querySelector<HTMLElement>('[data-discussion-crypto-status]');
  const fileInput = root.querySelector<HTMLInputElement>('[data-discussion-file-input]');
  const dropzone = root.querySelector<HTMLElement>('[data-discussion-dropzone]');
  const previewList = root.querySelector<HTMLElement>('[data-discussion-file-preview]');
  const draftInput = root.querySelector<HTMLTextAreaElement>('[data-discussion-draft]');
  const searchInput = root.querySelector<HTMLInputElement>('[data-discussion-local-search]');
  const submitButton = root.querySelector<HTMLButtonElement>('[data-discussion-submit-button]');
  const submitStatus = root.querySelector<HTMLElement>('[data-discussion-submit-status]');
  const loadBeforeButton = root.querySelector<HTMLButtonElement>('[data-discussion-load-before]');
  const devicePanel = root.querySelector<HTMLElement>('[data-discussion-device-panel]');
  const deviceSummary = root.querySelector<HTMLElement>('[data-discussion-device-summary]');
  const regenerateKeysButton = root.querySelector<HTMLButtonElement>('[data-discussion-regenerate-keys]');
  const revokeDeviceButton = root.querySelector<HTMLButtonElement>('[data-discussion-revoke-device]');

  if (!messagesRoot) {
    return;
  }

  const apiUrl = root.dataset.apiMessagesUrl || '';
  const eventsUrl = root.dataset.apiEventsUrl || '';
  const readUrl = root.dataset.apiReadUrl || '';
  const keysUrl = root.dataset.apiKeysUrl || '';
  const devicesUrl = root.dataset.apiDevicesUrl || '';
  const csrfToken = root.dataset.csrfToken || '';
  const filesUrl = root.dataset.filesUrl || '';
  const conversationId = Number(root.dataset.conversationId || 0);
  const currentUserId = Number(root.dataset.currentUserId || 0);
  const memberLabels = parseMemberLabels(root);
  const cryptoOk = window.isSecureContext && Boolean(window.crypto?.subtle) && Boolean(window.indexedDB);
  const draftKey = `caramagnols.private.discussion.draft.${conversationId}`;

  let lastId = Number(root.dataset.lastMessageId || 0);
  let beforeId = Number(root.dataset.beforeMessageId || 0);
  let lastEventId = 0;
  let lastMessageElement = root.querySelector<HTMLElement>('[data-discussion-last-message]');
  let cryptoState: CryptoState | null = null;
  let currentDb: IDBDatabase | null = null;
  let currentDevice: CryptoState['device'] | null = null;
  let selectedFiles: File[] = [];
  let previewUrls: string[] = [];

  const labelFor = (senderId: number): string => {
    if (senderId === currentUserId) {
      return 'Moi';
    }

    return memberLabels[String(senderId)] || `Membre #${senderId}`;
  };

  const setStatus = (text: string, failure = false): void => {
    if (!status) {
      return;
    }
    status.textContent = text;
    status.className = failure ? 'notice notice-error' : 'notice notice-success';
  };

  const setSubmitState = (state: 'idle' | 'sending' | 'error', message = ''): void => {
    if (form) {
      form.dataset.sendState = state;
    }
    if (submitStatus) {
      submitStatus.textContent = message;
    }
    if (submitButton) {
      submitButton.disabled = state === 'sending';
    }
  };

  const setDevicePanel = (text: string, danger = false): void => {
    if (devicePanel) {
      devicePanel.hidden = false;
      devicePanel.dataset.deviceState = danger ? 'warning' : 'ready';
    }
    if (deviceSummary) {
      deviceSummary.textContent = text;
      deviceSummary.classList.toggle('notice-error', danger);
    }
  };

  const deviceId = (): string => {
    const storageKey = 'caramagnolsDiscussionDeviceId';
    let id = '';
    try {
      id = window.localStorage.getItem(storageKey) || '';
    } catch (_error) {
      id = '';
    }
    if (!/^[A-Za-z0-9._-]{16,64}$/.test(id)) {
      id = secureRandomHex(24);
      try {
        window.localStorage.setItem(storageKey, id);
      } catch (_error) {
        return id;
      }
    }

    return id;
  };

  const registerDevice = async (db: IDBDatabase): Promise<CryptoState['device']> => {
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

    const response = await fetch(devicesUrl, {
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
    let trustStatus = 'pending';
    if (response.ok) {
      const payload = await response.json() as Record<string, unknown>;
      const serverDevice = payload.device && typeof payload.device === 'object'
        ? payload.device as Record<string, unknown>
        : {};
      trustStatus = String(serverDevice.trustStatus || trustStatus);
    }

    return {
      deviceId: String(device.deviceId || ''),
      privateKey: device.privateKey as CryptoKey,
      publicKeyJwk: device.publicKeyJwk as JsonWebKey,
      trustStatus
    };
  };

  const importAesKey = (raw: BufferSource): Promise<CryptoKey> => {
    return crypto.subtle.importKey('raw', raw, { name: 'AES-GCM' }, true, ['encrypt', 'decrypt']);
  };

  const exportAesKey = (key: CryptoKey): Promise<ArrayBuffer> => {
    return crypto.subtle.exportKey('raw', key);
  };

  const fetchConversationKeys = async (): Promise<ConversationKeyPayload> => {
    const response = await fetch(keysUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (!response.ok) {
      return { keys: [], knownKeyCount: 0 };
    }
    const payload = await response.json() as Record<string, unknown>;

    return {
      keys: Array.isArray(payload.keys) ? payload.keys as Array<Record<string, unknown>> : [],
      knownKeyCount: Number(payload.knownKeyCount || 0)
    };
  };

  const fetchConversationDevices = async (): Promise<Array<Record<string, unknown>>> => {
    const response = await fetch(`${devicesUrl}?conversation_id=${conversationId}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    });
    if (!response.ok) {
      return [];
    }
    const payload = await response.json() as Record<string, unknown>;

    return Array.isArray(payload.devices) ? payload.devices as Array<Record<string, unknown>> : [];
  };

  const trustCurrentDevice = async (): Promise<void> => {
    const device = cryptoState?.device || currentDevice;
    if (!device?.deviceId) {
      return;
    }
    await fetch(devicesUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
      body: new URLSearchParams({
        csrf_token: csrfToken,
        action: 'trust_device',
        conversation_id: String(conversationId),
        device_id: device.deviceId
      }),
      credentials: 'same-origin'
    });
    device.trustStatus = 'trusted';
  };

  const shareKey = async (aesKey: CryptoKey, devices: Array<Record<string, unknown>>): Promise<void> => {
    const rawKey = await exportAesKey(aesKey);
    const wrappers = [];
    for (const device of devices) {
      try {
        const publicKey = await crypto.subtle.importKey(
          'jwk',
          JSON.parse(String(device.publicKeyJwk || '{}')) as JsonWebKey,
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
      } catch (_error) {
        // Un appareil mal forme ne doit pas bloquer les autres enveloppes.
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

  const regenerateConversationKey = async (): Promise<void> => {
    if (!cryptoOk || conversationId <= 0) {
      setStatus('Chiffrement local indisponible. Clés non régénérées.', true);
      return;
    }
    const db = currentDb || await openCryptoDb();
    const device = currentDevice || await registerDevice(db);
    currentDb = db;
    currentDevice = device;
    const aesKey = await crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, true, ['encrypt', 'decrypt']);
    await dbPut(db, { id: `conversation:${conversationId}`, aesKey });
    cryptoState = { db, device, aesKey };
    await shareKey(aesKey, await fetchConversationDevices());
    await trustCurrentDevice();
    setStatus('Nouvelle clé locale active pour les prochains messages.');
    setDevicePanel('Cet appareil est connu. Les anciens messages peuvent rester illisibles si leur ancienne clé n existe plus localement.');
  };

  const revokeCurrentDevice = async (): Promise<void> => {
    const device = cryptoState?.device || currentDevice;
    if (!device?.deviceId) {
      setDevicePanel('Aucun appareil courant a revoquer.', true);
      return;
    }
    const response = await fetch(devicesUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
      body: new URLSearchParams({
        csrf_token: csrfToken,
        action: 'revoke_device',
        conversation_id: String(conversationId),
        device_id: device.deviceId
      }),
      credentials: 'same-origin'
    });
    if (!response.ok) {
      setDevicePanel('Révocation impossible pour cet appareil.', true);
      return;
    }
    const db = cryptoState?.db || currentDb;
    if (db) {
      await dbDelete(db, `conversation:${conversationId}`);
      await dbDelete(db, 'device');
    }
    try {
      window.localStorage.removeItem('caramagnolsDiscussionDeviceId');
    } catch (_error) {
      // Le nouvel appareil sera recree au prochain chargement si le stockage est bloque.
    }
    cryptoState = null;
    currentDevice = null;
    setStatus('Appareil révoqué. Rechargez la page pour déclarer un nouvel appareil.', true);
    setDevicePanel('Cet appareil est révoqué pour les discussions.', true);
  };

  const conversationKey = async (): Promise<CryptoKey | null> => {
    if (!cryptoOk || conversationId <= 0) {
      return null;
    }
    if (cryptoState?.aesKey) {
      return cryptoState.aesKey;
    }
    const db = await openCryptoDb();
    const device = await registerDevice(db);
    currentDb = db;
    currentDevice = device;
    const localKey = await dbGet(db, `conversation:${conversationId}`);
    if (localKey?.aesKey) {
      cryptoState = { db, device, aesKey: localKey.aesKey as CryptoKey };
      await shareKey(localKey.aesKey as CryptoKey, await fetchConversationDevices());
      await trustCurrentDevice();
      setDevicePanel('Cet appareil est connu pour cette conversation.');

      return localKey.aesKey as CryptoKey;
    }
    const keyPayload = await fetchConversationKeys();
    for (const wrapped of keyPayload.keys) {
      if (String(wrapped.deviceId || '') !== device.deviceId) {
        continue;
      }
      try {
        const raw = await crypto.subtle.decrypt(
          { name: 'RSA-OAEP' },
          device.privateKey,
          base64ToBytes(String(wrapped.encryptedKey || ''))
        );
        const aesKey = await importAesKey(raw);
        await dbPut(db, { id: `conversation:${conversationId}`, aesKey });
        cryptoState = { db, device, aesKey };
        await trustCurrentDevice();
        setDevicePanel('Cet appareil est connu pour cette conversation.');

        return aesKey;
      } catch (_error) {
        // Le prochain wrapper compatible sera essaye.
      }
    }
    if (keyPayload.knownKeyCount > 0) {
      cryptoState = null;
      setDevicePanel(
        'Nouvel appareil detecte: les anciens messages restent illisibles sans cle locale autorisee.',
        true
      );
      return null;
    }
    const aesKey = await crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, true, ['encrypt', 'decrypt']);
    await dbPut(db, { id: `conversation:${conversationId}`, aesKey });
    await shareKey(aesKey, await fetchConversationDevices());
    cryptoState = { db, device, aesKey };
    await trustCurrentDevice();
    setDevicePanel('Cet appareil est connu pour cette conversation.');

    return aesKey;
  };

  const encryptText = async (text: string): Promise<{ payload: string; metadata: string } | null> => {
    const key = await conversationKey();
    if (!key) {
      return null;
    }
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const cipher = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, textEncoder.encode(text));

    return {
      payload: bytesToBase64(cipher),
      metadata: JSON.stringify({ algorithm: 'AES-GCM', iv: bytesToBase64(iv), version: 1 })
    };
  };

  const decryptText = async (payload: string, metadata: string): Promise<string | null> => {
    const key = await conversationKey();
    if (!key) {
      return null;
    }
    const meta = JSON.parse(metadata || '{}') as Record<string, unknown>;
    const plain = await crypto.subtle.decrypt(
      { name: 'AES-GCM', iv: base64ToBytes(String(meta.iv || '')) },
      key,
      base64ToBytes(payload)
    );

    return textDecoder.decode(plain);
  };

  const applySearch = (): void => {
    const term = normalize(searchInput?.value || '');
    messagesRoot.querySelectorAll<HTMLElement>('[data-message-id]').forEach((article) => {
      if (term === '') {
        article.classList.remove('private-discussion-search-hidden');
        return;
      }

      const haystack = normalize(article.dataset.searchText || article.textContent || '');
      article.classList.toggle('private-discussion-search-hidden', !haystack.includes(term));
    });
  };

  const decryptArticle = async (article: HTMLElement): Promise<void> => {
    if (article.dataset.encryptionMode !== 'client_aes_gcm_v1') {
      article.dataset.searchText = article.dataset.searchText || article.textContent || '';
      applySearch();
      return;
    }
    const target = article.querySelector<HTMLElement>('[data-encrypted-body]');
    if (!target) {
      return;
    }
    try {
      const text = await decryptText(article.dataset.encryptedPayload || '', article.dataset.encryptionMetadata || '');
      target.textContent = text || 'Message chiffre illisible sur cet appareil.';
      article.dataset.searchText = text || '';
    } catch (_error) {
      target.textContent = 'Message chiffre illisible sur cet appareil.';
      article.dataset.searchText = '';
    }
    applySearch();
  };

  const scrollToLastMessage = (): void => {
    if (lastMessageElement instanceof HTMLElement) {
      lastMessageElement.scrollIntoView({ block: 'center' });
    }
  };

  const removeEmptyState = (): void => {
    messagesRoot.querySelector<HTMLElement>('#discussion-empty')?.remove();
  };

  const createAttachmentList = (message: DiscussionMessage): HTMLUListElement | null => {
    const attachments = Array.isArray(message.attachments) ? message.attachments : [];
    if (attachments.length === 0) {
      return null;
    }

    const list = document.createElement('ul');
    list.className = 'private-discussion-attachments';
    attachments.forEach((attachment) => {
      if (!attachment || !attachment.attachmentId) {
        return;
      }
      const item = document.createElement('li');
      const href = `${filesUrl}/${encodeURIComponent(String(attachment.attachmentId))}`;
      if (String(attachment.mimeType || '').startsWith('image/')) {
        const imageLink = document.createElement('a');
        imageLink.href = href;
        const image = document.createElement('img');
        image.className = 'discussion-attachment-preview';
        image.src = `${href}/preview`;
        image.alt = String(attachment.originalFilename || 'piece-jointe');
        image.loading = 'lazy';
        imageLink.appendChild(image);
        item.appendChild(imageLink);
      }
      const link = document.createElement('a');
      link.href = href;
      link.textContent = String(attachment.originalFilename || 'piece-jointe');
      item.appendChild(link);
      list.appendChild(item);
    });

    return list;
  };

  const createMessageArticle = (message: DiscussionMessage): HTMLElement | null => {
    const id = Number(message.id || 0);
    if (id <= 0 || messagesRoot.querySelector(`[data-message-id="${id}"]`)) {
      return null;
    }

    const senderId = Number(message.senderPrivateUserId || 0);
    const article = document.createElement('article');
    article.className = `notice private-discussion-message${senderId === currentUserId ? ' private-discussion-message-own' : ''}`;
    article.dataset.messageId = String(id);
    article.dataset.senderId = String(senderId);
    article.dataset.createdAt = String(message.createdAt || '');
    article.dataset.encryptionMode = String(message.encryptionMode || 'none');
    article.dataset.encryptedPayload = String(message.encryptedPayload || '');
    article.dataset.encryptionMetadata = String(message.encryptionMetadata || '');
    article.dataset.searchText = String(message.body || '');

    const header = document.createElement('div');
    header.className = 'private-discussion-message-header';
    const meta = appendText(header, 'p', '', 'muted private-discussion-message-meta');
    const author = appendText(meta, 'strong', labelFor(senderId));
    author.insertAdjacentText('afterend', ' ');
    appendText(meta, 'span', String(message.createdAt || ''));
    article.appendChild(header);
    header.appendChild(meta);

    if (article.dataset.encryptionMode !== 'none') {
      const encrypted = appendText(article, 'p', 'Message chiffre sur cet appareil.');
      encrypted.setAttribute('data-encrypted-body', '1');
    } else if (message.body) {
      appendText(article, 'p', String(message.body));
    }

    const attachmentList = createAttachmentList(message);
    if (attachmentList) {
      article.appendChild(attachmentList);
    }

    return article;
  };

  const appendMessage = (message: DiscussionMessage): void => {
    const id = Number(message.id || 0);
    if (id <= lastId || id <= 0) {
      return;
    }

    const article = createMessageArticle(message);
    if (!article) {
      return;
    }

    removeEmptyState();
    if (lastMessageElement instanceof HTMLElement) {
      lastMessageElement.removeAttribute('id');
      lastMessageElement.removeAttribute('data-discussion-last-message');
    }
    article.id = 'discussion-message-last';
    article.dataset.discussionLastMessage = '1';
    messagesRoot.appendChild(article);
    lastMessageElement = article;
    lastId = id;
    void decryptArticle(article);
  };

  const prependMessages = (messages: DiscussionMessage[], hasMoreBefore: boolean): void => {
    const previousTop = messagesRoot.firstElementChild instanceof HTMLElement ? messagesRoot.firstElementChild : null;
    messages.forEach((message) => {
      const article = createMessageArticle(message);
      if (!article) {
        return;
      }
      messagesRoot.insertBefore(article, previousTop);
      const id = Number(message.id || 0);
      beforeId = beforeId > 0 ? Math.min(beforeId, id) : id;
      void decryptArticle(article);
    });
    if (loadBeforeButton) {
      loadBeforeButton.hidden = !hasMoreBefore;
    }
  };

  const poll = async (): Promise<void> => {
    try {
      const response = await fetch(`${apiUrl}?after_message_id=${lastId}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin'
      });
      if (!response.ok) {
        return;
      }
      const payload = await response.json() as Record<string, unknown>;
      (Array.isArray(payload.messages) ? payload.messages as DiscussionMessage[] : []).forEach(appendMessage);
      await fetch(readUrl, {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
        credentials: 'same-origin'
      });
    } catch (_error) {
      // Le polling suivant retentera.
    }
  };

  const loadPreviousMessages = async (): Promise<void> => {
    if (!loadBeforeButton || beforeId <= 0) {
      return;
    }
    loadBeforeButton.disabled = true;
    try {
      const response = await fetch(`${apiUrl}?before_message_id=${beforeId}&limit=50`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin'
      });
      if (!response.ok) {
        return;
      }
      const payload = await response.json() as Record<string, unknown>;
      prependMessages(
        Array.isArray(payload.messages) ? payload.messages as DiscussionMessage[] : [],
        Boolean(payload.hasMoreBefore)
      );
    } catch (_error) {
      // L'utilisateur peut relancer le chargement.
    } finally {
      loadBeforeButton.disabled = false;
    }
  };

  const deleteMessageFromDom = (messageId: number): void => {
    const article = messagesRoot.querySelector<HTMLElement>(`[data-message-id="${messageId}"]`);
    if (!article) {
      return;
    }
    article.classList.add('notice-error');
    article.querySelectorAll('p, ul, form').forEach((child) => {
      child.remove();
    });
    appendText(article, 'p', 'Message supprimé.');
    article.dataset.searchText = '';
  };

  const connectEvents = (): boolean => {
    if (!window.EventSource || !eventsUrl) {
      return false;
    }

    const source = new EventSource(`${eventsUrl}?after_event_id=${lastEventId}`);
    const refresh = (event: MessageEvent<string>): void => {
      lastEventId = Number(event.lastEventId || lastEventId || 0);
      let parsed: DiscussionEventPayload = {};
      try {
        parsed = JSON.parse(event.data || '{}') as DiscussionEventPayload;
      } catch (_error) {
        parsed = {};
      }
      const payload = parsed.payload || {};
      if (parsed.type === 'message.deleted' && Number(payload.messageId || 0) > 0) {
        deleteMessageFromDom(Number(payload.messageId || 0));
        return;
      }
      void poll();
    };
    source.addEventListener('message.created', refresh as EventListener);
    source.addEventListener('message.deleted', refresh as EventListener);
    source.addEventListener('attachment.deleted', refresh as EventListener);
    source.addEventListener('member.added', refresh as EventListener);
    source.addEventListener('member.left', refresh as EventListener);
    source.onerror = () => {
      source.close();
      window.setTimeout(connectEvents, 10000);
    };

    return true;
  };

  const clearPreviewUrls = (): void => {
    previewUrls.forEach((url) => window.URL.revokeObjectURL(url));
    previewUrls = [];
  };

  const syncFileInput = (): void => {
    if (!fileInput) {
      return;
    }
    try {
      const transfer = new DataTransfer();
      selectedFiles.forEach((file) => transfer.items.add(file));
      fileInput.files = transfer.files;
    } catch (_error) {
      // Certains navigateurs interdisent l'ecriture de FileList.
    }
  };

  const renderFilePreviews = (): void => {
    if (!previewList) {
      return;
    }
    clearPreviewUrls();
    previewList.replaceChildren();
    selectedFiles.forEach((file, index) => {
      const item = document.createElement('li');
      const details = document.createElement('div');
      appendText(details, 'strong', file.name || 'piece-jointe');
      appendText(details, 'div', fileSizeLabel(file.size), 'private-discussion-file-meta');
      if (file.type.startsWith('image/')) {
        const image = document.createElement('img');
        image.className = 'private-discussion-preview-image';
        image.alt = file.name || 'image';
        image.src = window.URL.createObjectURL(file);
        previewUrls.push(image.src);
        details.appendChild(image);
      }
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'button-small button-danger';
      remove.textContent = 'Retirer';
      remove.addEventListener('click', () => {
        selectedFiles = selectedFiles.filter((_file, fileIndex) => fileIndex !== index);
        syncFileInput();
        renderFilePreviews();
      });
      item.appendChild(details);
      item.appendChild(remove);
      previewList.appendChild(item);
    });
  };

  const addFiles = (files: FileList | File[]): void => {
    const incoming = Array.from(files);
    if (incoming.length === 0) {
      return;
    }
    selectedFiles = selectedFiles.concat(incoming).slice(0, 10);
    syncFileInput();
    renderFilePreviews();
  };

  if (fileInput) {
    fileInput.addEventListener('change', () => {
      selectedFiles = Array.from(fileInput.files || []);
      renderFilePreviews();
    });
  }

  if (dropzone) {
    ['dragenter', 'dragover'].forEach((eventName) => {
      dropzone.addEventListener(eventName, (event) => {
        event.preventDefault();
        dropzone.dataset.dragActive = '1';
      });
    });
    ['dragleave', 'drop'].forEach((eventName) => {
      dropzone.addEventListener(eventName, () => {
        dropzone.dataset.dragActive = '0';
      });
    });
    dropzone.addEventListener('drop', (event) => {
      event.preventDefault();
      addFiles(event.dataTransfer?.files || []);
    });
  }

  if (draftInput) {
    try {
      draftInput.value = window.sessionStorage.getItem(draftKey) || draftInput.value;
    } catch (_error) {
      // Les brouillons restent optionnels.
    }
    draftInput.addEventListener('input', () => {
      try {
        window.sessionStorage.setItem(draftKey, draftInput.value);
      } catch (_error) {
        // Les brouillons restent optionnels.
      }
    });
    draftInput.addEventListener('paste', (event) => {
      const files = event.clipboardData?.files;
      if (files && files.length > 0) {
        addFiles(files);
      }
    });
  }

  searchInput?.addEventListener('input', applySearch);
  loadBeforeButton?.addEventListener('click', () => {
    void loadPreviousMessages();
  });
  regenerateKeysButton?.addEventListener('click', () => {
    void regenerateConversationKey();
  });
  revokeDeviceButton?.addEventListener('click', () => {
    void revokeCurrentDevice();
  });

  if (lastMessageElement instanceof HTMLElement) {
    window.requestAnimationFrame(scrollToLastMessage);
    window.setTimeout(scrollToLastMessage, 250);
  }

  document.querySelectorAll<HTMLElement>('[data-private-toast]').forEach((toast) => {
    window.setTimeout(() => {
      toast.classList.add('private-toast-notice-hidden');
      window.setTimeout(() => {
        toast.hidden = true;
      }, 320);
    }, 3200);
  });

  if (form) {
    form.addEventListener('submit', async (event) => {
      const body = form.querySelector<HTMLTextAreaElement>('textarea[name="body"]');
      const clientMessage = form.querySelector<HTMLInputElement>('input[name="client_message_id"]');
      const mode = form.querySelector<HTMLInputElement>('input[name="encryption_mode"]');
      const payload = form.querySelector<HTMLInputElement>('input[name="encrypted_payload"]');
      const metadata = form.querySelector<HTMLInputElement>('input[name="encryption_metadata"]');
      const text = body ? String(body.value || '').trim() : '';
      if (clientMessage && !clientMessage.value) {
        clientMessage.value = secureRandomHex();
      }

      if (!text || !mode || !payload || !metadata) {
        setSubmitState('sending', 'Envoi en cours...');
        return;
      }

      event.preventDefault();
      if (!cryptoOk) {
        setStatus('Chiffrement local indisponible sur ce navigateur. Message texte non envoye.', true);
        setSubmitState('error', 'Envoi bloque: chiffrement local indisponible.');
        return;
      }

      setSubmitState('sending', 'Chiffrement et envoi en cours...');
      try {
        const encrypted = await encryptText(text);
        if (!encrypted) {
          setStatus('Chiffrement local indisponible. Message non envoye.', true);
          setSubmitState('error', 'Envoi bloque: cle locale indisponible.');
          return;
        }
        mode.value = 'client_aes_gcm_v1';
        payload.value = encrypted.payload;
        metadata.value = encrypted.metadata;
        if (body) {
          body.value = '';
        }
        try {
          window.sessionStorage.removeItem(draftKey);
        } catch (_error) {
          // Le brouillon sera remplace a la prochaine saisie.
        }
        HTMLFormElement.prototype.submit.call(form);
      } catch (_error) {
        setStatus('Chiffrement local impossible. Message non envoye.', true);
        setSubmitState('error', 'Envoi impossible.');
      }
    });
  }

  if (!cryptoOk) {
    setStatus('Chiffrement local indisponible sur ce navigateur. Les messages texte sont bloques.', true);
  } else {
    conversationKey()
      .then((key) => {
        setStatus(key ? 'Chiffrement local actif pour les messages texte.' : 'Chiffrement local non initialise.', !key);
        messagesRoot.querySelectorAll<HTMLElement>('[data-encryption-mode="client_aes_gcm_v1"]').forEach((article) => {
          void decryptArticle(article);
        });
      })
      .catch(() => setStatus('Chiffrement local non initialise sur cet appareil.', true));
  }

  messagesRoot.querySelectorAll<HTMLElement>('[data-message-id]').forEach((article) => {
    if (article.dataset.encryptionMode !== 'client_aes_gcm_v1') {
      article.dataset.searchText = article.dataset.searchText || article.textContent || '';
    }
  });
  applySearch();

  const eventStreamActive = connectEvents();
  window.setInterval(() => {
    void poll();
  }, eventStreamActive ? 15000 : 5000);
};

const init = (): void => {
  document.querySelectorAll<HTMLElement>('[data-private-discussion-app]').forEach(initPrivateDiscussion);
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
  init();
}
