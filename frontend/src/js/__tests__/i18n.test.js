import { describe, it, expect, vi, beforeEach } from 'vitest';
import {
  resolveLanguage,
  persistLanguage,
  getPersistedLanguage,
  clearTranslationCache,
  changeLanguage,
  loadTranslationsByKeys
} from '../i18n.ts';

global.fetch = vi.fn(async () => ({
  ok: true,
  json: async () => ({ title: 'Bonjour' })
}));

describe('i18n helpers', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    clearTranslationCache();
    localStorage.clear();
    window.caramagnolsRuntime = undefined;
    Object.defineProperty(window.navigator, 'language', {
      value: 'fr-FR',
      configurable: true
    });
  });

  it('persists and restores language via localStorage', () => {
    persistLanguage('en');
    expect(getPersistedLanguage()).toBe('en');
  });

  it('falls back to browser language when nothing is forced/persisted', () => {
    const lang = resolveLanguage();
    expect(lang).toBe('fr');
  });

  it('ignores unsupported forced language and falls back', () => {
    const spy = vi.spyOn(console, 'warn').mockImplementation(() => {});
    const lang = resolveLanguage({ forcedLang: 'es' });
    expect(lang).toBe('fr');
    expect(spy).toHaveBeenCalled();
  });

  it('loads translations via changeLanguage and updates html lang', async () => {
    document.documentElement.lang = 'fr';
    await changeLanguage('en');
    expect(document.documentElement.lang).toBe('en');
    expect(fetch).toHaveBeenCalledWith(
      expect.stringContaining('core/api/lang.php?lang=en'),
      expect.any(Object)
    );
  });

  it('caches translations and avoids duplicate fetch', async () => {
    fetch.mockClear();
    await changeLanguage('en');
    await changeLanguage('en');
    expect(fetch).toHaveBeenCalledTimes(1);
  });

  it('uses runtime lang api path when provided', async () => {
    window.caramagnolsRuntime = {
      api: {
        lang: '/catalogue/core/api/lang.php'
      }
    };

    await changeLanguage('de');

    expect(fetch).toHaveBeenCalledWith(
      expect.stringContaining('/catalogue/core/api/lang.php?lang=de'),
      expect.any(Object)
    );
  });

  it('loads only requested keys when key filter is provided', async () => {
    fetch.mockClear();

    await loadTranslationsByKeys('fr', {
      keys: ['TXT_SITE_BRAND', 'TXT_NAV_OPEN_MENU']
    });

    expect(fetch).toHaveBeenCalledWith(
      expect.stringContaining('keys=TXT_SITE_BRAND%2CTXT_NAV_OPEN_MENU'),
      expect.any(Object)
    );
  });

  it('reloads full catalog after a keys-only fetch', async () => {
    fetch.mockReset();
    fetch
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ TXT_SITE_BRAND: 'Les Caramagnols' })
      })
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ title: 'Bonjour', TXT_NAV_OPEN_MENU: 'Ouvrir le menu' })
      });

    await loadTranslationsByKeys('fr', { keys: ['TXT_SITE_BRAND'] });
    await changeLanguage('fr');

    expect(fetch).toHaveBeenCalledTimes(2);
    expect(fetch).toHaveBeenNthCalledWith(
      2,
      expect.stringContaining('core/api/lang.php?lang=fr'),
      expect.any(Object)
    );
  });
});
