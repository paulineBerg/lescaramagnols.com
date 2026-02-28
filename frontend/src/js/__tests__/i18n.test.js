import { describe, it, expect, vi, beforeEach } from 'vitest';
import {
  resolveLanguage,
  persistLanguage,
  getPersistedLanguage,
  clearTranslationCache,
  changeLanguage,
} from '../i18n.js';

global.fetch = vi.fn(async () => ({
  ok: true,
  json: async () => ({ title: 'Bonjour' }),
}));

describe('i18n helpers', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    clearTranslationCache();
    localStorage.clear();
    Object.defineProperty(window.navigator, 'language', {
      value: 'fr-FR',
      configurable: true,
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
      expect.any(Object),
    );
  });
});
