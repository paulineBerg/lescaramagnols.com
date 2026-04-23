import { describe, it, expect, beforeEach, vi } from 'vitest';
import { initSiteNavigation, toTop } from '../menus.ts';

// Import side effects (DOMContentLoaded handlers)
import '../menus.ts';

const createPointerLikeEvent = (type: string, pointerType: string) => {
  const event = new Event(type, { bubbles: true, cancelable: true }) as Event & { pointerType?: string };
  Object.defineProperty(event, 'pointerType', { value: pointerType });
  return event;
};

describe('menus', () => {
  const renderNavigation = () => {
    document.body.innerHTML = `
      <div class="site-header-nav-shell" data-nav-scope-root="desktop">
        <ul class="site-nav-list">
          <li class="site-nav-item site-nav-item-has-children" data-nav-item>
            <div class="site-nav-row">
              <a class="site-nav-link" href="/accueil">Accueil</a>
              <button
                type="button"
                data-nav-submenu-toggle
                data-nav-scope="desktop"
                aria-expanded="false"
                aria-controls="desktop-submenu"
              >
                Toggle
              </button>
            </div>
            <div id="desktop-submenu" data-nav-panel hidden>
              <ul><li><a href="/accueil/intro">Intro</a></li></ul>
            </div>
          </li>
          <li class="site-nav-item site-nav-item-has-children" data-nav-item>
            <div class="site-nav-row">
              <a class="site-nav-link" href="/bouger">Bouger</a>
              <button
                type="button"
                data-nav-submenu-toggle
                data-nav-scope="desktop"
                aria-expanded="false"
                aria-controls="desktop-submenu-2"
              >
                Toggle 2
              </button>
            </div>
            <div id="desktop-submenu-2" data-nav-panel hidden>
              <ul><li><a href="/bouger/plages">Plages</a></li></ul>
            </div>
          </li>
        </ul>
      </div>
      <div id="breadcrumb-mobile" data-nav-scope-root="mobile">
        <button id="hamburger-icon" data-mobile-nav-toggle aria-expanded="false" aria-controls="site-mobile-panel"></button>
        <div id="site-mobile-panel" data-mobile-nav-panel hidden>
          <ul class="site-mobile-nav-list">
            <li class="site-mobile-nav-item site-mobile-nav-item-has-children" data-nav-item>
              <div class="site-mobile-nav-row">
                <a class="site-mobile-nav-link" href="/auto-retro">Auto-Retro</a>
                <span class="site-mobile-nav-toggle" aria-hidden="true">▾</span>
              </div>
              <div id="mobile-submenu" data-nav-panel hidden>
                <ul><li><a href="/auto-retro/austin">Austin</a></li></ul>
              </div>
            </li>
          </ul>
        </div>
      </div>
      <div id="remonter" class="fleche"></div>
    `;

    initSiteNavigation();
  };

  beforeEach(() => {
    window.sessionStorage.clear();
    vi.restoreAllMocks();
    renderNavigation();
  });

  it('ouvre et ferme le menu mobile via hamburger + Escape', () => {
    const hamburger = document.getElementById('hamburger-icon');
    const panel = document.getElementById('site-mobile-panel');

    hamburger?.dispatchEvent(new Event('click', { bubbles: true }));

    expect(document.body.classList.contains('menu-open')).toBe(true);
    expect(hamburger?.getAttribute('aria-expanded')).toBe('true');
    expect(panel?.hidden).toBe(false);

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    expect(document.body.classList.contains('menu-open')).toBe(false);
    expect(panel?.hidden).toBe(true);
    expect(hamburger?.getAttribute('aria-expanded')).toBe('false');
  });

  it('ouvre et ferme un sous-menu mobile au clic sur toute la ligne', () => {
    const hamburger = document.getElementById('hamburger-icon');
    const row = document.querySelector<HTMLElement>('.site-mobile-nav-row');
    const panel = document.getElementById('mobile-submenu');

    hamburger?.dispatchEvent(new Event('click', { bubbles: true }));
    expect(panel?.hidden).toBe(true);

    row?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(panel?.hidden).toBe(false);

    row?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(panel?.hidden).toBe(true);
  });

  it('ouvre et ferme un sous-menu desktop au clic', () => {
    const toggle = document.querySelector<HTMLElement>('[data-nav-scope="desktop"]');
    const panel = document.getElementById('desktop-submenu');

    toggle?.dispatchEvent(new Event('click', { bubbles: true }));
    expect(toggle?.getAttribute('aria-expanded')).toBe('true');
    expect(panel?.hidden).toBe(false);

    toggle?.dispatchEvent(new Event('click', { bubbles: true }));
    expect(toggle?.getAttribute('aria-expanded')).toBe('false');
    expect(panel?.hidden).toBe(true);
  });

  it('ouvre le sous-menu desktop au clic sur le lien parent dès le premier clic', () => {
    const firstItemLink = document.querySelector<HTMLAnchorElement>('.site-nav-item .site-nav-link');
    const firstItemPanel = document.getElementById('desktop-submenu');

    expect(firstItemLink).not.toBeNull();

    const firstClick = new MouseEvent('click', { bubbles: true, cancelable: true });
    const firstDispatchResult = firstItemLink!.dispatchEvent(firstClick);

    expect(firstDispatchResult).toBe(false);
    expect(firstClick.defaultPrevented).toBe(true);
    expect(firstItemPanel?.hidden).toBe(false);
  });

  it('ouvre le sous-menu desktop au tap tactile sur le lien parent', () => {
    const firstItemLink = document.querySelector<HTMLAnchorElement>('.site-nav-item .site-nav-link');
    const firstItemPanel = document.getElementById('desktop-submenu');

    expect(firstItemLink).not.toBeNull();

    const touchTap = createPointerLikeEvent('pointerup', 'touch');
    const tapDispatchResult = firstItemLink!.dispatchEvent(touchTap);

    expect(tapDispatchResult).toBe(false);
    expect(touchTap.defaultPrevented).toBe(true);
    expect(firstItemPanel?.hidden).toBe(false);
  });

  it('ouvre le sous-menu desktop au survol et le referme après un court délai en quittant la zone', () => {
    vi.useFakeTimers();

    try {
      const item = document.querySelector<HTMLElement>('[data-nav-item]');
      const toggle = document.querySelector<HTMLElement>('[data-nav-scope="desktop"]');
      const panel = document.getElementById('desktop-submenu');

      item?.dispatchEvent(new MouseEvent('mouseenter'));
      expect(item?.classList.contains('is-open')).toBe(true);
      expect(toggle?.getAttribute('aria-expanded')).toBe('true');
      expect(panel?.hidden).toBe(false);

      item?.dispatchEvent(new MouseEvent('mouseleave'));
      expect(item?.classList.contains('is-open')).toBe(true);
      expect(panel?.hidden).toBe(false);

      vi.advanceTimersByTime(200);
      expect(item?.classList.contains('is-open')).toBe(false);
      expect(toggle?.getAttribute('aria-expanded')).toBe('false');
      expect(panel?.hidden).toBe(true);
    } finally {
      vi.useRealTimers();
    }
  });

  it('annule la fermeture du sous-menu desktop si la souris revient rapidement', () => {
    vi.useFakeTimers();

    try {
      const item = document.querySelector<HTMLElement>('[data-nav-item]');
      const panel = document.getElementById('desktop-submenu');

      item?.dispatchEvent(new MouseEvent('mouseenter'));
      expect(panel?.hidden).toBe(false);

      item?.dispatchEvent(new MouseEvent('mouseleave'));
      expect(panel?.hidden).toBe(false);

      item?.dispatchEvent(new MouseEvent('mouseenter'));
      vi.advanceTimersByTime(220);
      expect(panel?.hidden).toBe(false);
      expect(item?.classList.contains('is-open')).toBe(true);
    } finally {
      vi.useRealTimers();
    }
  });

  it('garde le sous-menu desktop ouvert pendant le passage du bouton vers le panneau', () => {
    vi.useFakeTimers();

    try {
      const item = document.querySelector<HTMLElement>('[data-nav-item]');
      const panel = document.getElementById('desktop-submenu');

      item?.dispatchEvent(new MouseEvent('mouseenter'));
      expect(panel?.hidden).toBe(false);

      item?.dispatchEvent(new MouseEvent('mouseleave'));
      panel?.dispatchEvent(new MouseEvent('mouseenter'));

      vi.advanceTimersByTime(220);
      expect(item?.classList.contains('is-open')).toBe(true);
      expect(panel?.hidden).toBe(false);

      panel?.dispatchEvent(new MouseEvent('mouseleave'));
      vi.advanceTimersByTime(220);
      expect(item?.classList.contains('is-open')).toBe(false);
      expect(panel?.hidden).toBe(true);
    } finally {
      vi.useRealTimers();
    }
  });

  it('ouvre le sous-menu desktop au survol meme si le navigateur detecte mal le hover', () => {
    const originalMatchMedia = window.matchMedia;
    const matchMediaMock = vi.fn((query: string): MediaQueryList => ({
      matches: false,
      media: query,
      onchange: null,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
      addListener: vi.fn(),
      removeListener: vi.fn()
    }));

    Object.defineProperty(window, 'matchMedia', {
      configurable: true,
      value: matchMediaMock
    });

    try {
      renderNavigation();

      const item = document.querySelector<HTMLElement>('[data-nav-item]');
      const panel = document.getElementById('desktop-submenu');

      item?.dispatchEvent(new MouseEvent('mouseenter'));
      expect(panel?.hidden).toBe(false);
    } finally {
      Object.defineProperty(window, 'matchMedia', {
        configurable: true,
        value: originalMatchMedia
      });
    }
  });

  it('ouvre le sous-menu desktop au focus et le referme quand le focus sort', () => {
    const item = document.querySelector<HTMLElement>('[data-nav-item]');
    const link = document.querySelector<HTMLElement>('.site-nav-link');
    const panel = document.getElementById('desktop-submenu');

    link?.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
    expect(item?.classList.contains('is-open')).toBe(true);
    expect(panel?.hidden).toBe(false);

    const outside = document.createElement('button');
    document.body.appendChild(outside);
    outside.focus();
    outside.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));

    expect(item?.classList.contains('is-open')).toBe(false);
    expect(panel?.hidden).toBe(true);
  });

  it('ferme le sous-menu desktop au clic extérieur et garde un seul item ouvert', () => {
    const toggles = document.querySelectorAll<HTMLElement>('[data-nav-scope="desktop"]');
    const firstPanel = document.getElementById('desktop-submenu');
    const secondPanel = document.getElementById('desktop-submenu-2');

    toggles[0]?.dispatchEvent(new Event('click', { bubbles: true }));
    expect(firstPanel?.hidden).toBe(false);

    toggles[1]?.dispatchEvent(new Event('click', { bubbles: true }));
    expect(firstPanel?.hidden).toBe(true);
    expect(secondPanel?.hidden).toBe(false);

    document.body.dispatchEvent(new Event('click', { bubbles: true }));
    expect(secondPanel?.hidden).toBe(true);
  });

  it('ferme le sous-menu desktop au clic sur un lien interne', () => {
    const toggle = document.querySelector<HTMLElement>('[data-nav-scope="desktop"]');
    const panel = document.getElementById('desktop-submenu');
    const link = document.querySelector<HTMLAnchorElement>('#desktop-submenu a');

    toggle?.dispatchEvent(new Event('click', { bubbles: true }));
    expect(panel?.hidden).toBe(false);

    link?.addEventListener('click', event => event.preventDefault(), { once: true });
    link?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

    expect(toggle?.getAttribute('aria-expanded')).toBe('false');
    expect(panel?.hidden).toBe(true);
    expect(window.sessionStorage.getItem('site-nav-desktop-hover-until')).toBeNull();
  });

  it('ouvre immédiatement au survol même après une navigation précédente', () => {
    window.sessionStorage.setItem('site-nav-desktop-hover-until', String(Date.now() + 10_000));

    renderNavigation();

    const item = document.querySelector<HTMLElement>('[data-nav-item]');
    const panel = document.getElementById('desktop-submenu');

    item?.dispatchEvent(new MouseEvent('mouseenter'));
    expect(panel?.hidden).toBe(false);
  });

  it('ferme le panneau mobile au resize desktop', () => {
    const hamburger = document.getElementById('hamburger-icon');
    const panel = document.getElementById('site-mobile-panel');

    hamburger?.dispatchEvent(new Event('click', { bubbles: true }));
    expect(panel?.hidden).toBe(false);

    Object.defineProperty(window, 'innerWidth', { value: 1200, configurable: true });
    window.dispatchEvent(new Event('resize'));

    expect(panel?.hidden).toBe(true);
    expect(document.body.classList.contains('menu-open')).toBe(false);
  });

  it('toTop déclenche un scroll doux', () => {
    const spy = vi.spyOn(window, 'scrollTo').mockImplementation(() => {});
    toTop();
    expect(spy).toHaveBeenCalledWith({ top: 0, behavior: 'smooth' });
    spy.mockRestore();
  });
});
