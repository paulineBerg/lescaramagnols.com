// src/js/logger.js
/* eslint-disable no-console */
// Centralise console usage to keep production builds clean while preserving useful diagnostics in dev.

const isDev = (() => {
  if (typeof import.meta !== 'undefined' && import.meta.env) {
    return Boolean(import.meta.env.DEV);
  }

  if (typeof process !== 'undefined' && typeof process.env !== 'undefined') {
    return process.env.NODE_ENV !== 'production';
  }

  return true;
})();

export const logWarn = (...args) => {
  if (isDev) {
    console.warn(...args);
  }
};

export const logError = (...args) => {
  // Keep errors visible in all environments to ease debugging.
  console.error(...args);
};

export const logDebug = (...args) => {
  if (isDev) {
    console.debug(...args);
  }
};
