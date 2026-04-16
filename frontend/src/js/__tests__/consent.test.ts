import { beforeEach, describe, expect, it, vi } from 'vitest';
import { initCookieConsent } from '../consent.ts';

describe('cookie consent', () => {
  beforeEach(() => {
    document.documentElement.removeAttribute('data-tarteaucitron-ready');
    document.body.innerHTML = '';
    window.tarteaucitronForceLanguage = undefined;
    window.caramagnolsRuntime = undefined;
    window.tarteaucitron = {
      init: vi.fn(),
      job: []
    };
  });

  it('initialise tarteaucitron en bas a droite', () => {
    initCookieConsent('de');

    expect(window.tarteaucitronForceLanguage).toBe('de');
    expect(window.tarteaucitron?.init).toHaveBeenCalledWith(
      expect.objectContaining({
        privacyUrl: '/accueil/toutes-les-mentions-legales',
        showIcon: true,
        iconPosition: 'BottomRight',
        showAlertSmall: true,
        highPrivacy: true
      })
    );
  });

  it('respecte la configuration runtime injectee par le backend', () => {
    window.caramagnolsRuntime = {
      tarteaucitron: {
        privacy_url: '/mentions',
        orientation: 'top',
        icon_position: 'TopLeft',
        show_icon: false,
        show_alert_small: false,
        high_privacy: false,
        accept_all_cta: false,
        deny_all_cta: false,
        mandatory: false,
        google_consent_mode: false,
        bing_consent_mode: false,
        services: ['vimeo']
      }
    };

    initCookieConsent('fr');

    expect(window.tarteaucitron?.init).toHaveBeenCalledWith(
      expect.objectContaining({
        privacyUrl: '/mentions',
        orientation: 'top',
        iconPosition: 'TopLeft',
        showIcon: false,
        showAlertSmall: false,
        highPrivacy: false,
        AcceptAllCta: false,
        DenyAllCta: false,
        mandatory: false,
        googleConsentMode: false,
        bingConsentMode: false
      })
    );
    expect(window.tarteaucitron?.job).toContain('vimeo');
  });

  it('active le service recaptcha quand la protection discussion est active', () => {
    window.caramagnolsRuntime = {
      tarteaucitron: {
        services: ['youtube']
      },
      discussions: {
        recaptcha: {
          enabled: true,
          site_key: 'site-key-123'
        }
      }
    };

    initCookieConsent('en');

    expect(window.tarteaucitron?.job).toContain('recaptcha');
    expect((window.tarteaucitron as any)?.user?.recaptcha_hl).toBe('en');
  });

  it('n initialise pas tarteaucitron quand il est desactive en runtime', () => {
    window.caramagnolsRuntime = {
      tarteaucitron: {
        enabled: false
      }
    };

    initCookieConsent('fr');

    expect(window.tarteaucitron?.init).not.toHaveBeenCalled();
  });

  it('remplace les iframes YouTube par des placeholders soumis au consentement', () => {
    document.body.innerHTML = `
      <div class="video-container">
        <iframe
          src="https://www.youtube.com/embed/jHO4WgBiHGQ?list=PLEaZw9SP95T3YS2JYO0fqT032SHk7SFhd&rel=0"
          title="Video Simca"
          width="640"
          height="360"
          loading="lazy"
          referrerpolicy="strict-origin-when-cross-origin"
          allowfullscreen
        ></iframe>
      </div>
    `;

    initCookieConsent('fr');

    const placeholder = document.querySelector<HTMLDivElement>('.youtube_player');
    expect(placeholder).not.toBeNull();
    expect(placeholder?.getAttribute('videoID')).toBe('jHO4WgBiHGQ');
    expect(placeholder?.getAttribute('width')).toBe('100%');
    expect(placeholder?.getAttribute('height')).toBe('100%');
    expect(placeholder?.style.width).toBe('100%');
    expect(placeholder?.style.height).toBe('100%');
    expect(placeholder?.getAttribute('title')).toBe('Video Simca');
    expect(window.tarteaucitron?.job).toContain('youtube');
    expect(document.querySelector('iframe')).toBeNull();
  });

  it('conserve les dimensions explicites hors conteneur video responsive', () => {
    document.body.innerHTML = `
      <iframe
        src="https://www.youtube.com/embed/jHO4WgBiHGQ"
        title="Video Simca"
        width="640"
        height="360"
      ></iframe>
    `;

    initCookieConsent('fr');

    const placeholder = document.querySelector<HTMLDivElement>('.youtube_player');
    expect(placeholder).not.toBeNull();
    expect(placeholder?.getAttribute('width')).toBe('640');
    expect(placeholder?.getAttribute('height')).toBe('360');
  });

  it('utilise le fallback i18n backend pour le titre des placeholders YouTube', () => {
    window.caramagnolsRuntime = {
      i18n: {
        youtube_title_fallback: 'Video YouTube localisee'
      }
    };

    document.body.innerHTML = `
      <iframe
        src="https://www.youtube.com/embed/jHO4WgBiHGQ"
        width="640"
        height="360"
      ></iframe>
    `;

    initCookieConsent('fr');

    const placeholder = document.querySelector<HTMLDivElement>('.youtube_player');
    expect(placeholder?.getAttribute('title')).toBe('Video YouTube localisee');
  });

  it('ajoute les services configures sans doublons dans tarteaucitron.job', () => {
    window.caramagnolsRuntime = {
      tarteaucitron: {
        services: ['youtube', 'vimeo', 'YouTube', '', 'googlemaps']
      }
    };

    document.body.innerHTML = `
      <iframe
        src="https://www.youtube.com/embed/jHO4WgBiHGQ"
        title="Video Simca"
      ></iframe>
    `;

    initCookieConsent('fr');

    expect(window.tarteaucitron?.job).toEqual(['youtube', 'vimeo', 'googlemaps']);
  });

  it('injecte les variables services dans tarteaucitron.user (ex: GTM)', () => {
    window.caramagnolsRuntime = {
      tarteaucitron: {
        services: ['googletagmanager'],
        user_config_json: '{"googletagmanagerId":"GTM-MKG2FFBZ","googleadsId":"AW-123456789"}'
      }
    };

    initCookieConsent('fr');

    expect((window.tarteaucitron as any)?.user?.googletagmanagerId).toBe('GTM-MKG2FFBZ');
    expect((window.tarteaucitron as any)?.user?.googleadsId).toBe('AW-123456789');
    expect(window.tarteaucitron?.job).toContain('googletagmanager');
  });
});
