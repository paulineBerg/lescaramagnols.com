import { describe, it, expect, beforeEach, vi } from 'vitest';
import { initSiteShare } from '../share.ts';

const renderShareControl = () => {
  document.title = 'Page active';
  document.body.innerHTML = `
    <div data-site-share>
      <button
        type="button"
        data-site-share-button
        data-share-title="Partager"
        aria-expanded="false"
        aria-controls="share-menu"
      >
        Partager
      </button>
      <div id="share-menu" data-site-share-menu data-share-copied-label="Lien copie" data-share-copy-failed-label="Copie impossible" hidden>
        <a href="#" data-site-share-link data-share-template="https://www.facebook.com/sharer/sharer.php?u={url}">Facebook</a>
        <button type="button" data-site-share-copy>Copier le lien</button>
      </div>
    </div>
  `;

  initSiteShare();
};

describe('share', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    Object.defineProperty(navigator, 'share', {
      configurable: true,
      value: undefined
    });
    Object.defineProperty(navigator, 'canShare', {
      configurable: true,
      value: undefined
    });
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: undefined
    });
    renderShareControl();
  });

  it('utilise le partage natif quand il est disponible', async () => {
    const share = vi.fn().mockResolvedValue(undefined);
    Object.defineProperty(navigator, 'share', {
      configurable: true,
      value: share
    });
    Object.defineProperty(navigator, 'canShare', {
      configurable: true,
      value: vi.fn().mockReturnValue(true)
    });

    document.querySelector<HTMLButtonElement>('[data-site-share-button]')?.click();
    await Promise.resolve();

    expect(share).toHaveBeenCalledWith({
      title: 'Page active',
      url: window.location.href
    });
  });

  it('ouvre le menu de repli avec le lien actif encode', () => {
    const button = document.querySelector<HTMLButtonElement>('[data-site-share-button]');
    const menu = document.querySelector<HTMLElement>('[data-site-share-menu]');
    const link = document.querySelector<HTMLAnchorElement>('[data-site-share-link]');

    button?.click();

    expect(button?.getAttribute('aria-expanded')).toBe('true');
    expect(menu?.hidden).toBe(false);
    expect(link?.href).toContain(encodeURIComponent(window.location.href));
  });

  it('copie le lien actif depuis le menu de repli', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined);
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: { writeText }
    });

    document.querySelector<HTMLButtonElement>('[data-site-share-button]')?.click();
    document.querySelector<HTMLButtonElement>('[data-site-share-copy]')?.click();
    await Promise.resolve();

    expect(writeText).toHaveBeenCalledWith(window.location.href);
  });
});

