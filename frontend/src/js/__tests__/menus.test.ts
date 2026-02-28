import { describe, it, expect, beforeEach, vi } from 'vitest';
import { toTop } from '../menus.ts';

// Import side effects (DOMContentLoaded handlers)
import '../menus.ts';

describe('menus', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <div id="breadcrumb-mobile">
        <div class="breadcrumb-menu"></div>
      </div>
      <div id="hamburger-icon" aria-expanded="false"></div>
      <ul id="menu2"><li></li></ul>
      <div id="remonter" class="fleche"></div>
    `;
  });

  it('ouvre et ferme le menu mobile via hamburger + Escape', () => {
    document.dispatchEvent(new Event('DOMContentLoaded'));
    const hamburger = document.getElementById('hamburger-icon');
    const breadcrumb = document.getElementById('breadcrumb-mobile');

    hamburger?.dispatchEvent(new Event('click', { bubbles: true }));

    expect(document.body.classList.contains('menu-open')).toBe(true);
    expect(hamburger?.getAttribute('aria-expanded')).toBe('true');
    expect(breadcrumb?.classList.contains('open')).toBe(true);

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    expect(document.body.classList.contains('menu-open')).toBe(false);
    expect(breadcrumb?.classList.contains('open')).toBe(false);
    expect(hamburger?.getAttribute('aria-expanded')).toBe('false');
  });

  it('toTop déclenche un scroll doux', () => {
    const spy = vi.spyOn(window, 'scrollTo').mockImplementation(() => {});
    toTop();
    expect(spy).toHaveBeenCalledWith({ top: 0, behavior: 'smooth' });
    spy.mockRestore();
  });
});
