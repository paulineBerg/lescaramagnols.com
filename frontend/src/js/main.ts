// src/js/main.js

import './menus.ts';
import '../scss/style.scss';

import { resolveLanguage, loadTranslations, applyTranslations, persistLanguage } from './i18n.ts';

document.addEventListener('DOMContentLoaded', async () => {
  const urlParams = new URLSearchParams(window.location.search);
  const forcedLang = urlParams.get('lang');

  const lang = resolveLanguage({ forcedLang });

  const translations = await loadTranslations(lang);
  applyTranslations(translations);

  // Optionnel : appliquer <html lang="...">
  document.documentElement.lang = lang;
  persistLanguage(lang);
});
