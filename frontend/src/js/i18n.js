// LesCaramagnols -->
// src/js/i18n.js -->

const SUPPORTED_LANGS = ['fr', 'en', 'de'];
const DEFAULT_LANG = 'fr';
const STORAGE_KEY = 'lescaramagnols.lang';

const translationCache = new Map();

function normaliseLang(lang) {
  if (!lang) return DEFAULT_LANG;
  const clean = lang.toLowerCase().split('-')[0];
  return SUPPORTED_LANGS.includes(clean) ? clean : DEFAULT_LANG;
}

export function getPersistedLanguage() {
  try {
    return window.localStorage.getItem(STORAGE_KEY);
  } catch (error) {
    console.warn('LocalStorage indisponible pour la langue', error);
    return null;
  }
}

export function persistLanguage(lang) {
  try {
    window.localStorage.setItem(STORAGE_KEY, lang);
  } catch (error) {
    console.warn('Impossible de persister la langue', error);
  }
}

export function resolveLanguage({ forcedLang, fallbackLang = DEFAULT_LANG } = {}) {
  if (forcedLang) {
    if (SUPPORTED_LANGS.includes(forcedLang)) {
      return forcedLang;
    }
    console.warn(`Langue forcée "${forcedLang}" non supportée, fallback sur ${fallbackLang}`);
  }

  const stored = getPersistedLanguage();
  if (stored && SUPPORTED_LANGS.includes(stored)) {
    return stored;
  }

  const browserLang = navigator.language || navigator.userLanguage;
  return normaliseLang(browserLang) || fallbackLang;
}

export async function loadTranslations(lang = DEFAULT_LANG) {
  const finalLang = normaliseLang(lang);

  if (translationCache.has(finalLang)) {
    return translationCache.get(finalLang);
  }

  try {
    const response = await fetch(`core/api/lang.php?lang=${finalLang}`, {
      headers: {
        'Accept': 'application/json',
      },
      cache: 'no-store',
    });

    if (!response.ok) {
      throw new Error(`Échec du chargement (${response.status})`);
    }

    const data = await response.json();

    translationCache.set(finalLang, data);
    persistLanguage(finalLang);

    return data;
  } catch (error) {
    console.error('Erreur i18n :', error);

    if (finalLang !== DEFAULT_LANG) {
      console.warn(`Fallback sur la langue par défaut (${DEFAULT_LANG})`);
      return loadTranslations(DEFAULT_LANG);
    }

    return {};
  }
}

export function clearTranslationCache(lang) {
  if (!lang) {
    translationCache.clear();
    return;
  }

  translationCache.delete(normaliseLang(lang));
}

function applyText(el, value) {
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

export function applyTranslations(translations = {}) {
  document.querySelectorAll('[data-i18n]').forEach(el => {
    const key = el.getAttribute('data-i18n');
    const fallback = el.textContent?.trim();
    const value = translations[key] ?? fallback;

    if (typeof value === 'string') {
      applyText(el, value);
    } else {
      console.warn(`Traduction manquante pour la clé "${key}"`);
    }
  });
}

export async function changeLanguage(lang) {
  const finalLang = normaliseLang(lang);
  const translations = await loadTranslations(finalLang);
  applyTranslations(translations);
  document.documentElement.lang = finalLang;
  return finalLang;
}
