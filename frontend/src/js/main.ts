// src/js/main.js

import './menus.ts';
import '../scss/style.scss';

import { initCookieConsent } from './consent.ts';
import { resolveLanguage, loadTranslationsByKeys, applyTranslations, persistLanguage } from './i18n.ts';

type IdleCallbackWindow = Window & {
  requestIdleCallback?: (callback: IdleRequestCallback, options?: IdleRequestOptions) => number;
};

const hasClientI18nTargets = (): boolean => {
  return document.querySelector('[data-i18n], [data-i18n-attr]') !== null;
};

const collectI18nKeysFromDom = (): string[] => {
  const keys = new Set<string>();
  document.querySelectorAll('[data-i18n]').forEach((node) => {
    if (!(node instanceof Element)) {
      return;
    }

    const key = (node.getAttribute('data-i18n') ?? '').trim();
    if (key !== '') {
      keys.add(key);
    }
  });

  return Array.from(keys);
};

const scheduleNonCriticalTask = (callback: () => void): void => {
  const idleWindow = window as IdleCallbackWindow;
  if (typeof idleWindow.requestIdleCallback === 'function') {
    idleWindow.requestIdleCallback(() => callback(), { timeout: 2000 });
    return;
  }

  window.setTimeout(callback, 400);
};

document.addEventListener('DOMContentLoaded', async () => {
  const urlParams = new URLSearchParams(window.location.search);
  const forcedLang = urlParams.get('lang');

  const lang = resolveLanguage({ forcedLang });

  scheduleNonCriticalTask(() => {
    initCookieConsent(lang);
  });

  if (hasClientI18nTargets()) {
    const translations = await loadTranslationsByKeys(lang, { keys: collectI18nKeysFromDom() });
    applyTranslations(translations);
  }

  if (document.querySelector('[data-instagram-feed]')) {
    const { initInstagramFeeds } = await import('./instagram-feed.ts');
    initInstagramFeeds();
  }

  if (document.querySelector('[data-discussion-form]')) {
    const { initDiscussionForms } = await import('./discussion-form.ts');
    initDiscussionForms();
  }

  if (document.querySelector('[data-site-share]')) {
    const { initSiteShare } = await import('./share.ts');
    initSiteShare();
  }

  // Optionnel : appliquer <html lang="...">
  document.documentElement.lang = lang;
  persistLanguage(lang);
});
