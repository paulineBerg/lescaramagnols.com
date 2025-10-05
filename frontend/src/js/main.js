// src/js/main.js

import './menus.js';
import '../scss/style.scss';
console.log('Menus JS importé via Vite');

import { loadTranslations, applyTranslations } from './i18n.js';

function detectBrowserLang() {
  const lang = navigator.language || navigator.userLanguage;
  return lang.startsWith('fr') ? 'fr' : 'en';
}

document.addEventListener('DOMContentLoaded', async () => {
  const urlParams = new URLSearchParams(window.location.search);
  const forcedLang = urlParams.get('lang');

  const lang = forcedLang || detectBrowserLang();

  const translations = await loadTranslations(lang);
  applyTranslations(translations);

  // Optionnel : appliquer <html lang="...">
  document.documentElement.lang = lang;
});

