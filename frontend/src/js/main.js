// src/js/main.js

import './menus.js';
import '../scss/style.scss';
console.log('Menus JS importé via Vite');

import { resolveLanguage, loadTranslations, applyTranslations, persistLanguage } from './i18n.js';

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
