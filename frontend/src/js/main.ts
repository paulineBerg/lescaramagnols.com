// src/js/main.js

import './menus.ts';
import '../scss/style.scss';

import { initCookieConsent } from './consent.ts';
import { resolveLanguage, loadTranslations, applyTranslations, persistLanguage } from './i18n.ts';
import { initInstagramFeeds } from './instagram-feed.ts';

document.addEventListener('DOMContentLoaded', async () => {
  const urlParams = new URLSearchParams(window.location.search);
  const forcedLang = urlParams.get('lang');

  const lang = resolveLanguage({ forcedLang });
  initCookieConsent(lang);

  const translations = await loadTranslations(lang);
  applyTranslations(translations);
  initInstagramFeeds();

  // Optionnel : appliquer <html lang="...">
  document.documentElement.lang = lang;
  persistLanguage(lang);
});
