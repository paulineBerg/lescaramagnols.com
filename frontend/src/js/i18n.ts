import { logWarn, logError } from './logger.ts';

const SUPPORTED_LANGS = ['fr', 'en', 'de'] as const;
export type SupportedLang = (typeof SUPPORTED_LANGS)[number];
const DEFAULT_LANG: SupportedLang = 'fr';
const STORAGE_KEY = 'lescaramagnols.lang';
const MAX_TRANSLATION_KEYS_PER_REQUEST = 250;

const translationCache = new Map<SupportedLang, Record<string, string>>();
const translationCoverage = new Map<SupportedLang, Set<string> | null>();

type LoadTranslationsOptions = {
  keys?: string[];
};

function resolveLangApiEndpoint(): string {
  const candidate = window.caramagnolsRuntime?.api?.lang;
  if (typeof candidate === 'string' && candidate.trim() !== '') {
    return candidate.trim();
  }

  return '/core/api/lang.php';
}

function normaliseLang(lang?: string | null): SupportedLang {
  if (!lang) return DEFAULT_LANG;
  const clean = lang.toLowerCase().split('-')[0];
  return SUPPORTED_LANGS.includes(clean as SupportedLang) ? (clean as SupportedLang) : DEFAULT_LANG;
}

export function getPersistedLanguage(): SupportedLang | null {
  try {
    const value = window.localStorage.getItem(STORAGE_KEY);
    return value ? normaliseLang(value) : null;
  } catch (error) {
    logWarn('LocalStorage indisponible pour la langue', error);
    return null;
  }
}

export function persistLanguage(lang: SupportedLang): void {
  try {
    window.localStorage.setItem(STORAGE_KEY, lang);
  } catch (error) {
    logWarn('Impossible de persister la langue', error);
  }
}

export function resolveLanguage({
  forcedLang,
  fallbackLang = DEFAULT_LANG
}: { forcedLang?: string | null; fallbackLang?: SupportedLang } = {}): SupportedLang {
  if (forcedLang) {
    if (SUPPORTED_LANGS.includes(forcedLang as SupportedLang)) {
      return forcedLang as SupportedLang;
    }
    logWarn(`Langue forcée "${forcedLang}" non supportée, fallback sur ${fallbackLang}`);
  }

  const stored = getPersistedLanguage();
  if (stored && SUPPORTED_LANGS.includes(stored)) {
    return stored;
  }

  const browserLang = (navigator as Navigator & { userLanguage?: string }).language || navigator.userLanguage;
  return normaliseLang(browserLang) || fallbackLang;
}

export async function loadTranslations(lang: SupportedLang = DEFAULT_LANG): Promise<Record<string, string>> {
  const finalLang = normaliseLang(lang);
  const coverage = translationCoverage.get(finalLang);
  if (translationCache.has(finalLang) && coverage === null) {
    return translationCache.get(finalLang) ?? {};
  }

  try {
    const endpoint = resolveLangApiEndpoint();
    const separator = endpoint.includes('?') ? '&' : '?';
    const response = await fetch(`${endpoint}${separator}lang=${encodeURIComponent(finalLang)}`, {
      headers: {
        Accept: 'application/json'
      },
      cache: 'no-store'
    });

    if (!response.ok) {
      throw new Error(`Échec du chargement (${response.status})`);
    }

    const data = await response.json();

    translationCache.set(finalLang, data);
    translationCoverage.set(finalLang, null);
    persistLanguage(finalLang);

    return data;
  } catch (error) {
    logError('Erreur i18n :', error);

    if (finalLang !== DEFAULT_LANG) {
      logWarn(`Fallback sur la langue par défaut (${DEFAULT_LANG})`);
      return loadTranslations(DEFAULT_LANG);
    }

    return {};
  }
}

export async function loadTranslationsByKeys(
  lang: SupportedLang = DEFAULT_LANG,
  options: LoadTranslationsOptions = {}
): Promise<Record<string, string>> {
  const finalLang = normaliseLang(lang);
  const requestedKeys = normaliseTranslationKeys(options.keys ?? []);
  if (requestedKeys.length === 0) {
    return loadTranslations(finalLang);
  }

  const cachedTranslations = translationCache.get(finalLang) ?? {};
  const currentCoverage = translationCoverage.get(finalLang);

  if (currentCoverage === null && translationCache.has(finalLang)) {
    return pickTranslations(cachedTranslations, requestedKeys);
  }

  const knownKeys = currentCoverage ?? new Set<string>();
  const missingKeys = requestedKeys.filter((key) => !knownKeys.has(key));

  if (missingKeys.length === 0) {
    return pickTranslations(cachedTranslations, requestedKeys);
  }

  try {
    const endpoint = resolveLangApiEndpoint();
    const separator = endpoint.includes('?') ? '&' : '?';
    const keysQuery = encodeURIComponent(missingKeys.join(','));
    const response = await fetch(`${endpoint}${separator}lang=${encodeURIComponent(finalLang)}&keys=${keysQuery}`, {
      headers: {
        Accept: 'application/json'
      },
      cache: 'no-store'
    });

    if (!response.ok) {
      throw new Error(`Échec du chargement (${response.status})`);
    }

    const data = await response.json();
    const mergedTranslations = {
      ...cachedTranslations,
      ...data
    };

    translationCache.set(finalLang, mergedTranslations);
    translationCoverage.set(finalLang, new Set<string>([...knownKeys, ...missingKeys]));
    persistLanguage(finalLang);

    return pickTranslations(mergedTranslations, requestedKeys);
  } catch (error) {
    logError('Erreur i18n :', error);

    if (finalLang !== DEFAULT_LANG) {
      logWarn(`Fallback sur la langue par défaut (${DEFAULT_LANG})`);
      return loadTranslationsByKeys(DEFAULT_LANG, { keys: requestedKeys });
    }

    return {};
  }
}

export function clearTranslationCache(lang?: SupportedLang | null): void {
  if (!lang) {
    translationCache.clear();
    translationCoverage.clear();
    return;
  }

  const normalizedLang = normaliseLang(lang);
  translationCache.delete(normalizedLang);
  translationCoverage.delete(normalizedLang);
}

function applyText(el: Element, value: string): void {
  const targetAttr = el.getAttribute('data-i18n-attr');
  if (targetAttr) {
    el.setAttribute(targetAttr, value);
    return;
  }

  const allowHtml = el.hasAttribute('data-i18n-html');
  if (allowHtml) {
    el.innerHTML = value;
    return;
  }

  el.textContent = value;
}

export function applyTranslations(translations: Record<string, string> = {}): void {
  document.querySelectorAll('[data-i18n]').forEach(el => {
    const key = el.getAttribute('data-i18n') ?? '';
    const fallback = el.textContent?.trim() ?? '';
    const value = translations[key] ?? fallback;

    if (typeof value === 'string') {
      applyText(el, value);
    } else {
      logWarn(`Traduction manquante pour la clé "${key}"`);
    }
  });
}

export async function changeLanguage(lang: string): Promise<SupportedLang> {
  const finalLang = normaliseLang(lang);
  const translations = await loadTranslations(finalLang);
  applyTranslations(translations);
  document.documentElement.lang = finalLang;
  return finalLang;
}

function normaliseTranslationKeys(keys: string[]): string[] {
  const normalized = Array.from(new Set(
    keys
      .map((key) => key.trim())
      .filter((key) => key !== '')
  ));

  return normalized.slice(0, MAX_TRANSLATION_KEYS_PER_REQUEST);
}

function pickTranslations(source: Record<string, string>, keys: string[]): Record<string, string> {
  const selected: Record<string, string> = {};
  for (const key of keys) {
    if (Object.prototype.hasOwnProperty.call(source, key) && typeof source[key] === 'string') {
      selected[key] = source[key];
    }
  }

  return selected;
}
