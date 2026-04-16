type TarteaucitronInitOptions = Record<string, boolean | number | string>;
type RuntimeTarteaucitronConfig = {
  enabled?: boolean;
  privacy_url?: string;
  orientation?: string;
  icon_position?: string;
  show_icon?: boolean;
  show_alert_small?: boolean;
  high_privacy?: boolean;
  accept_all_cta?: boolean;
  deny_all_cta?: boolean;
  mandatory?: boolean;
  google_consent_mode?: boolean;
  bing_consent_mode?: boolean;
  user_config_json?: unknown;
  services?: unknown[];
};

type RuntimeDiscussionsConfig = {
  recaptcha?: {
    enabled?: boolean;
    site_key?: string;
  };
};

type RuntimeI18nConfig = {
  youtube_title_fallback?: string;
};

type RuntimeApiConfig = {
  lang?: string;
};

type TarteaucitronApi = {
  init: (options: TarteaucitronInitOptions) => void;
  job?: string[];
  user?: Record<string, unknown>;
};

declare global {
  interface Window {
    tarteaucitron?: TarteaucitronApi;
    tarteaucitronForceLanguage?: string;
    caramagnolsRuntime?: {
      tarteaucitron?: RuntimeTarteaucitronConfig;
      discussions?: RuntimeDiscussionsConfig;
      i18n?: RuntimeI18nConfig;
      api?: RuntimeApiConfig;
    };
  }
}

const TARTEAUCITRON_READY_FLAG = 'tarteaucitronReady';
const PRIVACY_URL = '/accueil/toutes-les-mentions-legales';
const DEFAULT_YOUTUBE_WIDTH = '560';
const DEFAULT_YOUTUBE_HEIGHT = '315';
const ORIENTATIONS = ['top', 'bottom', 'middle'] as const;
const ICON_POSITIONS = ['BottomRight', 'BottomLeft', 'TopRight', 'TopLeft'] as const;

const normalizeLanguage = (language: string) => {
  const normalized = language.trim().toLowerCase();
  return ['fr', 'en', 'de'].includes(normalized) ? normalized : 'fr';
};

const normalizeDimension = (value: string | null, fallback: string) => {
  const normalized = value?.trim() ?? '';
  return normalized !== '' ? normalized : fallback;
};

const resolveBoolean = (value: unknown, fallback: boolean) => {
  return typeof value === 'boolean' ? value : fallback;
};

const resolveString = (value: unknown, fallback: string) => {
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : fallback;
};

const resolveChoice = <T extends readonly string[]>(value: unknown, allowed: T, fallback: T[number]) => {
  const normalized = resolveString(value, fallback);
  return allowed.includes(normalized as T[number]) ? (normalized as T[number]) : fallback;
};

const normalizeServiceKey = (value: unknown) => {
  if (typeof value !== 'string') {
    return null;
  }

  const normalized = value.trim().toLowerCase();
  return /^[a-z0-9][a-z0-9_-]*$/.test(normalized) ? normalized : null;
};

const resolveServices = (value: unknown) => {
  if (!Array.isArray(value)) {
    return [];
  }

  const services: string[] = [];
  const seen = new Set<string>();

  value.forEach((entry) => {
    const key = normalizeServiceKey(entry);
    if (!key || seen.has(key)) {
      return;
    }

    seen.add(key);
    services.push(key);
  });

  return services;
};

const normalizeUserVariableKey = (value: unknown) => {
  if (typeof value !== 'string') {
    return null;
  }

  const normalized = value.trim();
  return /^[A-Za-z][A-Za-z0-9_]{1,79}$/.test(normalized) ? normalized : null;
};

const normalizeUserVariableValue = (value: unknown) => {
  if (typeof value === 'boolean') {
    return value;
  }

  if (typeof value === 'number') {
    return Number.isFinite(value) ? value : null;
  }

  if (typeof value === 'string') {
    const normalized = value.trim();
    return normalized !== '' ? normalized : null;
  }

  return null;
};

const resolveUserConfig = (value: unknown) => {
  let source: unknown = value;
  if (typeof value === 'string') {
    const raw = value.trim();
    if (raw === '') {
      return {} as Record<string, string | number | boolean>;
    }

    try {
      source = JSON.parse(raw);
    } catch {
      return {} as Record<string, string | number | boolean>;
    }
  }

  if (!source || typeof source !== 'object' || Array.isArray(source)) {
    return {} as Record<string, string | number | boolean>;
  }

  const entries: Record<string, string | number | boolean> = {};
  Object.entries(source as Record<string, unknown>).forEach(([key, rawValue]) => {
    const normalizedKey = normalizeUserVariableKey(key);
    if (!normalizedKey) {
      return;
    }

    const normalizedValue = normalizeUserVariableValue(rawValue);
    if (normalizedValue === null) {
      return;
    }

    entries[normalizedKey] = normalizedValue;
  });

  return entries;
};

const resolveRuntimeConfig = () => {
  const runtime = window.caramagnolsRuntime?.tarteaucitron;
  const discussions = window.caramagnolsRuntime?.discussions;
  const i18n = window.caramagnolsRuntime?.i18n;
  const recaptcha = discussions?.recaptcha;
  const recaptchaSiteKey = resolveString(recaptcha?.site_key, '');
  const recaptchaEnabled = resolveBoolean(recaptcha?.enabled, false) && recaptchaSiteKey !== '';

  return {
    enabled: resolveBoolean(runtime?.enabled, true),
    privacyUrl: resolveString(runtime?.privacy_url, PRIVACY_URL),
    orientation: resolveChoice(runtime?.orientation, ORIENTATIONS, 'bottom'),
    iconPosition: resolveChoice(runtime?.icon_position, ICON_POSITIONS, 'BottomRight'),
    showIcon: resolveBoolean(runtime?.show_icon, true),
    showAlertSmall: resolveBoolean(runtime?.show_alert_small, true),
    highPrivacy: resolveBoolean(runtime?.high_privacy, true),
    acceptAllCta: resolveBoolean(runtime?.accept_all_cta, true),
    denyAllCta: resolveBoolean(runtime?.deny_all_cta, true),
    mandatory: resolveBoolean(runtime?.mandatory, true),
    googleConsentMode: resolveBoolean(runtime?.google_consent_mode, true),
    bingConsentMode: resolveBoolean(runtime?.bing_consent_mode, true),
    userConfig: resolveUserConfig(runtime?.user_config_json),
    services: resolveServices(runtime?.services),
    recaptchaEnabled,
    recaptchaSiteKey,
    youtubeTitleFallback: resolveString(i18n?.youtube_title_fallback, 'YouTube video')
  };
};

const registerServices = (tarteaucitron: TarteaucitronApi, services: string[]) => {
  if (services.length === 0) {
    return;
  }

  tarteaucitron.job = tarteaucitron.job || [];
  const existing = new Set(tarteaucitron.job);

  services.forEach((service) => {
    if (existing.has(service)) {
      return;
    }

    tarteaucitron.job?.push(service);
    existing.add(service);
  });
};

const extractYoutubeVideoId = (url: URL) => {
  const host = url.hostname.toLowerCase();
  const pathParts = url.pathname.split('/').filter(Boolean);

  if (host.includes('youtu.be')) {
    return pathParts[0] ?? null;
  }

  if (!host.includes('youtube.com') && !host.includes('youtube-nocookie.com')) {
    return null;
  }

  if (pathParts[0] === 'embed' && typeof pathParts[1] === 'string') {
    return pathParts[1];
  }

  if (pathParts[0] === 'shorts' && typeof pathParts[1] === 'string') {
    return pathParts[1];
  }

  return url.searchParams.get('v');
};

const copyYouTubeQueryParam = (placeholder: HTMLDivElement, source: URLSearchParams, key: string) => {
  const value = source.get(key);
  if (value !== null && value !== '') {
    placeholder.setAttribute(key, value);
  }
};

const replaceYoutubeIframe = (iframe: HTMLIFrameElement, fallbackTitle: string) => {
  const rawSrc = iframe.getAttribute('src');
  if (!rawSrc) {
    return false;
  }

  let sourceUrl: URL;
  try {
    sourceUrl = new URL(rawSrc, window.location.origin);
  } catch {
    return false;
  }

  const videoId = extractYoutubeVideoId(sourceUrl);
  if (!videoId) {
    return false;
  }

  const isResponsiveContainer = iframe.parentElement instanceof HTMLElement
    && iframe.parentElement.classList.contains('video-container');

  const width = isResponsiveContainer
    ? '100%'
    : normalizeDimension(iframe.getAttribute('width'), DEFAULT_YOUTUBE_WIDTH);
  const height = isResponsiveContainer
    ? '100%'
    : normalizeDimension(iframe.getAttribute('height'), DEFAULT_YOUTUBE_HEIGHT);

  const placeholder = document.createElement('div');
  placeholder.className = 'youtube_player';
  placeholder.setAttribute('videoID', videoId);
  placeholder.setAttribute('width', width);
  placeholder.setAttribute('height', height);
  placeholder.setAttribute('title', iframe.getAttribute('title') || fallbackTitle);

  if (isResponsiveContainer) {
    placeholder.style.position = 'absolute';
    placeholder.style.top = '0';
    placeholder.style.left = '0';
    placeholder.style.width = '100%';
    placeholder.style.height = '100%';
  }

  if (iframe.hasAttribute('allowfullscreen')) {
    placeholder.setAttribute('allowfullscreen', '1');
  }

  const loading = iframe.getAttribute('loading');
  if (loading) {
    placeholder.setAttribute('loading', loading);
  }

  const referrerPolicy = iframe.getAttribute('referrerpolicy');
  if (referrerPolicy) {
    placeholder.setAttribute('referrerpolicy', referrerPolicy);
  }

  const sourceDocument = iframe.getAttribute('srcdoc');
  if (sourceDocument) {
    placeholder.setAttribute('srcdoc', sourceDocument);
  }

  ['controls', 'rel', 'autoplay', 'mute', 'start', 'end', 'loop', 'enablejsapi'].forEach(key => {
    copyYouTubeQueryParam(placeholder, sourceUrl.searchParams, key);
  });

  if (!isResponsiveContainer && iframe.style.width !== '') {
    placeholder.style.width = iframe.style.width;
  }

  if (!isResponsiveContainer && iframe.style.height !== '') {
    placeholder.style.height = iframe.style.height;
  }

  iframe.replaceWith(placeholder);
  return true;
};

const convertYoutubeEmbeds = (fallbackTitle: string) => {
  const selectors = [
    'iframe[src*="youtube.com/"]',
    'iframe[src*="youtube-nocookie.com/"]',
    'iframe[src*="youtu.be/"]'
  ];

  let converted = 0;
  document.querySelectorAll<HTMLIFrameElement>(selectors.join(', ')).forEach(iframe => {
    if (replaceYoutubeIframe(iframe, fallbackTitle)) {
      converted += 1;
    }
  });

  return converted;
};

export const initCookieConsent = (language: string) => {
  if (document.documentElement.dataset[TARTEAUCITRON_READY_FLAG] === 'true') {
    return;
  }

  const runtimeConfig = resolveRuntimeConfig();
  if (!runtimeConfig.enabled) {
    return;
  }

  const tarteaucitron = window.tarteaucitron;
  if (!tarteaucitron || typeof tarteaucitron.init !== 'function') {
    return;
  }

  const normalizedLanguage = normalizeLanguage(language);
  window.tarteaucitronForceLanguage = normalizedLanguage;
  if (Object.keys(runtimeConfig.userConfig).length > 0) {
    tarteaucitron.user = tarteaucitron.user || {};
    Object.entries(runtimeConfig.userConfig).forEach(([key, value]) => {
      tarteaucitron.user![key] = value;
    });
  }
  if (runtimeConfig.recaptchaEnabled) {
    tarteaucitron.user = tarteaucitron.user || {};
    tarteaucitron.user.recaptcha_hl = normalizedLanguage;
  }

  const hasYoutubeEmbeds = convertYoutubeEmbeds(runtimeConfig.youtubeTitleFallback) > 0;
  document.documentElement.dataset[TARTEAUCITRON_READY_FLAG] = 'true';

  tarteaucitron.init({
    privacyUrl: runtimeConfig.privacyUrl,
    bodyPosition: 'top',
    hashtag: '#tarteaucitron',
    cookieName: 'tarteaucitron',
    orientation: runtimeConfig.orientation,
    groupServices: true,
    showDetailsOnClick: true,
    serviceDefaultState: 'wait',
    showAlertSmall: runtimeConfig.showAlertSmall,
    cookieslist: false,
    cookieslistEmbed: false,
    closePopup: true,
    showIcon: runtimeConfig.showIcon,
    iconPosition: runtimeConfig.iconPosition,
    adblocker: false,
    DenyAllCta: runtimeConfig.denyAllCta,
    AcceptAllCta: runtimeConfig.acceptAllCta,
    highPrivacy: runtimeConfig.highPrivacy,
    alwaysNeedConsent: false,
    handleBrowserDNTRequest: false,
    removeCredit: false,
    moreInfoLink: true,
    useExternalCss: false,
    useExternalJs: false,
    mandatory: runtimeConfig.mandatory,
    mandatoryCta: false,
    googleConsentMode: runtimeConfig.googleConsentMode,
    bingConsentMode: runtimeConfig.bingConsentMode,
    pianoConsentMode: true,
    pianoConsentModeEssential: false,
    softConsentMode: false,
    dataLayer: false,
    serverSide: false,
    partnersList: false
  });

  registerServices(
    tarteaucitron,
    [
      ...runtimeConfig.services,
      ...(runtimeConfig.recaptchaEnabled ? ['recaptcha'] : []),
      ...(hasYoutubeEmbeds ? ['youtube'] : [])
    ]
  );
};
