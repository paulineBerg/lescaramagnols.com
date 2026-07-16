type SharePayload = {
  title: string;
  url: string;
};

type ShareNavigator = Navigator & {
  share?: (data: SharePayload) => Promise<void>;
  canShare?: (data: SharePayload) => boolean;
};

let globalShareClosersBound = false;

const currentSharePayload = (fallbackTitle: string): SharePayload => ({
  title: document.title.trim() || fallbackTitle,
  url: window.location.href
});

const encodedShareUrl = (template: string, payload: SharePayload): string => (
  template
    .replaceAll('{url}', encodeURIComponent(payload.url))
    .replaceAll('{title}', encodeURIComponent(payload.title))
);

const closeShareMenu = (root: HTMLElement) => {
  const button = root.querySelector<HTMLButtonElement>('[data-site-share-button]');
  const menu = root.querySelector<HTMLElement>('[data-site-share-menu]');

  if (!button || !menu) {
    return;
  }

  button.setAttribute('aria-expanded', 'false');
  menu.hidden = true;
};

const openShareMenu = (root: HTMLElement, payload: SharePayload) => {
  const button = root.querySelector<HTMLButtonElement>('[data-site-share-button]');
  const menu = root.querySelector<HTMLElement>('[data-site-share-menu]');

  if (!button || !menu) {
    return;
  }

  root.querySelectorAll<HTMLAnchorElement>('[data-site-share-link]').forEach(link => {
    const template = link.dataset.shareTemplate || '';
    if (template !== '') {
      link.href = encodedShareUrl(template, payload);
    }
  });

  button.setAttribute('aria-expanded', 'true');
  menu.hidden = false;
};

const toggleShareMenu = (root: HTMLElement, payload: SharePayload) => {
  const menu = root.querySelector<HTMLElement>('[data-site-share-menu]');
  if (!menu) {
    return;
  }

  if (menu.hidden) {
    document.querySelectorAll<HTMLElement>('[data-site-share]').forEach(candidate => {
      if (candidate !== root) {
        closeShareMenu(candidate);
      }
    });
    openShareMenu(root, payload);
    return;
  }

  closeShareMenu(root);
};

const copyText = async (text: string): Promise<boolean> => {
  if (navigator.clipboard?.writeText) {
    await navigator.clipboard.writeText(text);
    return true;
  }

  const input = document.createElement('textarea');
  input.value = text;
  input.setAttribute('readonly', 'readonly');
  input.style.position = 'fixed';
  input.style.left = '-9999px';
  document.body.appendChild(input);
  input.select();

  try {
    return document.execCommand('copy');
  } finally {
    input.remove();
  }
};

const bindShareRoot = (root: HTMLElement) => {
  if (root.dataset.shareBound === 'true') {
    return;
  }

  const button = root.querySelector<HTMLButtonElement>('[data-site-share-button]');
  const copyButton = root.querySelector<HTMLButtonElement>('[data-site-share-copy]');
  const menu = root.querySelector<HTMLElement>('[data-site-share-menu]');

  if (!button || !menu) {
    return;
  }

  root.dataset.shareBound = 'true';
  const fallbackTitle = button.dataset.shareTitle || 'Partager';

  menu.addEventListener('click', event => {
    event.stopPropagation();
  });

  button.addEventListener('click', async event => {
    event.preventDefault();
    event.stopPropagation();

    const payload = currentSharePayload(fallbackTitle);
    const shareNavigator = navigator as ShareNavigator;

    if (typeof shareNavigator.share === 'function') {
      try {
        if (typeof shareNavigator.canShare !== 'function' || shareNavigator.canShare(payload)) {
          await shareNavigator.share(payload);
          closeShareMenu(root);
          return;
        }
      } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
          return;
        }
      }
    }

    toggleShareMenu(root, payload);
  });

  root.querySelectorAll<HTMLAnchorElement>('[data-site-share-link]').forEach(link => {
    link.addEventListener('click', () => {
      closeShareMenu(root);
    });
  });

  copyButton?.addEventListener('click', async event => {
    event.preventDefault();
    event.stopPropagation();
    const copiedLabel = menu.dataset.shareCopiedLabel || copyButton.textContent || '';
    const failedLabel = menu.dataset.shareCopyFailedLabel || copyButton.textContent || '';
    const originalLabel = copyButton.textContent || '';
    const payload = currentSharePayload(fallbackTitle);

    try {
      const copied = await copyText(payload.url);
      copyButton.textContent = copied ? copiedLabel : failedLabel;
    } catch {
      copyButton.textContent = failedLabel;
    }

    window.setTimeout(() => {
      copyButton.textContent = originalLabel;
      closeShareMenu(root);
    }, 1200);
  });
};

export const initSiteShare = () => {
  document.querySelectorAll<HTMLElement>('[data-site-share]').forEach(bindShareRoot);

  if (globalShareClosersBound) {
    return;
  }

  globalShareClosersBound = true;
  document.addEventListener('click', event => {
    document.querySelectorAll<HTMLElement>('[data-site-share]').forEach(root => {
      if (!root.contains(event.target as Node)) {
        closeShareMenu(root);
      }
    });
  });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') {
      return;
    }

    document.querySelectorAll<HTMLElement>('[data-site-share]').forEach(closeShareMenu);
  });
};

document.addEventListener('DOMContentLoaded', () => {
  initSiteShare();
});
