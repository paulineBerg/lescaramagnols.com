// src/js/logger.ts
/* eslint-disable no-console */
// Centralise console usage to keep production builds clean while préservant les diagnostics en dev.

const isDev: boolean = (() => {
  if (typeof import.meta !== 'undefined' && import.meta.env) {
    return Boolean(import.meta.env.DEV);
  }

  if (typeof process !== 'undefined' && typeof process.env !== 'undefined') {
    return process.env.NODE_ENV !== 'production';
  }

  return true;
})();

export const logWarn = (...args: unknown[]): void => {
  if (isDev) {
    console.warn(...args);
  }
};

export const logError = (...args: unknown[]): void => {
  // Keep errors visible in all environments to ease debugging.
  console.error(...args);
};

export const logDebug = (...args: unknown[]): void => {
  if (isDev) {
    console.debug(...args);
  }
};
