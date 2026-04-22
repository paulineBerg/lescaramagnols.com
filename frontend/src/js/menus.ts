const DESKTOP_HOVER_SUPPRESSION_KEY = 'site-nav-desktop-hover-until';
const DESKTOP_TOUCH_SETTLE_MS = 900;
const DESKTOP_HOVER_CLOSE_DELAY_MS = 160;
let lastPointerType = '';
let lastPointerAt = 0;
let pointerTrackerBound = false;
const desktopHoverCloseTimers = new WeakMap<HTMLElement, ReturnType<typeof window.setTimeout>>();

const pointerTypeFromEvent = (event: Event): string => {
  const maybePointerType = (event as Event & { pointerType?: unknown }).pointerType;
  return typeof maybePointerType === 'string' ? maybePointerType : '';
};

const markPointerInteraction = (event: Event) => {
  const pointerType = pointerTypeFromEvent(event);
  if (pointerType === '') {
    return;
  }

  lastPointerType = pointerType;
  lastPointerAt = Date.now();
};

const recentTouchInteraction = () => {
  if (lastPointerType !== 'touch' && lastPointerType !== 'pen') {
    return false;
  }

  return (Date.now() - lastPointerAt) <= DESKTOP_TOUCH_SETTLE_MS;
};

const directChildBySelector = (item: HTMLElement, selector: string): HTMLElement | null => {
  const directChild = Array.from(item.children).find(
    child => child instanceof HTMLElement && child.matches(selector)
  );

  return directChild instanceof HTMLElement ? directChild : null;
};

const resetMegaPanelAlignment = (panel: HTMLElement) => {
  panel.style.removeProperty('--site-nav-panel-anchor-offset');
  delete panel.dataset.navPanelAnchorSide;
};

const alignMegaPanelToParent = (item: HTMLElement) => {
  const panel = directChildBySelector(item, '[data-nav-panel][data-nav-panel-kind="mega"]');
  if (!panel) {
    return;
  }

  resetMegaPanelAlignment(panel);
};

const alignOpenMegaPanels = () => {
  document
    .querySelectorAll<HTMLElement>('[data-nav-scope-root="desktop"] .site-nav-item.is-open')
    .forEach(item => alignMegaPanelToParent(item));
};

const bindPointerInteractionTracker = () => {
  if (pointerTrackerBound) {
    return;
  }

  pointerTrackerBound = true;
  document.addEventListener('pointerdown', event => {
    markPointerInteraction(event);
  }, true);
};

const clearDesktopHoverCloseTimer = (item: HTMLElement) => {
  const closeTimer = desktopHoverCloseTimers.get(item);
  if (closeTimer === undefined) {
    return;
  }

  window.clearTimeout(closeTimer);
  desktopHoverCloseTimers.delete(item);
};

const openItem = (item: HTMLElement, shouldOpen: boolean) => {
  clearDesktopHoverCloseTimer(item);
  item.classList.toggle('is-open', shouldOpen);

  const toggle = item.querySelector<HTMLElement>('[data-nav-submenu-toggle]');
  if (toggle) {
    toggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
  }

  const panel = item.querySelector<HTMLElement>('[data-nav-panel]');
  if (panel) {
    panel.hidden = !shouldOpen;

    if (!shouldOpen && panel.dataset.navPanelKind === 'mega') {
      resetMegaPanelAlignment(panel);
    }
  }

  if (shouldOpen) {
    const schedule = typeof window.requestAnimationFrame === 'function'
      ? window.requestAnimationFrame.bind(window)
      : (callback: FrameRequestCallback) => window.setTimeout(callback, 0);

    schedule(() => {
      alignMegaPanelToParent(item);
    });
  }
};

const scheduleDesktopHoverClose = (item: HTMLElement) => {
  clearDesktopHoverCloseTimer(item);

  const closeTimer = window.setTimeout(() => {
    desktopHoverCloseTimers.delete(item);

    const toggle = item.querySelector<HTMLElement>('[data-nav-submenu-toggle][data-nav-scope="desktop"]');
    if (!toggle || item.contains(document.activeElement)) {
      return;
    }

    openItem(item, false);
  }, DESKTOP_HOVER_CLOSE_DELAY_MS);

  desktopHoverCloseTimers.set(item, closeTimer);
};

const closeScopeItems = (scopeRoot: ParentNode | null) => {
  if (!scopeRoot) {
    return;
  }

  scopeRoot.querySelectorAll<HTMLElement>('[data-nav-item].is-open').forEach(item => {
    openItem(item, false);
  });
};

const closeSiblings = (item: HTMLElement) => {
  const siblings = item.parentElement?.children;
  if (!siblings) {
    return;
  }

  Array.from(siblings).forEach(sibling => {
    if (sibling instanceof HTMLElement && sibling !== item && sibling.matches('[data-nav-item]')) {
      openItem(sibling, false);
    }
  });
};

const isModifiedClick = (event: MouseEvent) =>
  event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;

const desktopHoverEnabled = () => {
  if (typeof window.matchMedia !== 'function') {
    return true;
  }

  const primaryPointerSupportsHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  if (primaryPointerSupportsHover) {
    return true;
  }

  return window.matchMedia('(any-hover: hover) and (any-pointer: fine)').matches;
};

const restoreDesktopHoverSuppression = () => {
  try {
    // Hover desktop always active: clear legacy suppression marker if present.
    window.sessionStorage.removeItem(DESKTOP_HOVER_SUPPRESSION_KEY);
  } catch {
    // Ignore storage failures.
  }
};

const suppressDesktopHoverAfterNavigation = () => {
  try {
    window.sessionStorage.removeItem(DESKTOP_HOVER_SUPPRESSION_KEY);
  } catch {
    // Ignore storage failures.
  }
};

const desktopHoverSuppressed = () => false;

const closeDesktopPanel = () => {
  closeScopeItems(document.querySelector<HTMLElement>('[data-nav-scope-root="desktop"]'));
};

const closeMobilePanel = () => {
  const mobileToggle = document.querySelector<HTMLElement>('[data-mobile-nav-toggle]');
  const mobilePanel = document.querySelector<HTMLElement>('[data-mobile-nav-panel]');

  if (!mobileToggle || !mobilePanel) {
    return;
  }

  mobileToggle.setAttribute('aria-expanded', 'false');
  mobilePanel.hidden = true;
  document.body.classList.remove('menu-open');
  closeScopeItems(document.getElementById('breadcrumb-mobile'));
};

const bindNavigationLinkClosers = () => {
  document.querySelectorAll<HTMLAnchorElement>('[data-nav-panel] a[href]').forEach(link => {
    if (link.dataset.navCloseBound === 'true') {
      return;
    }

    link.dataset.navCloseBound = 'true';
    link.addEventListener('click', () => {
      const desktopRoot = document.querySelector<HTMLElement>('[data-nav-scope-root="desktop"]');
      if (desktopRoot?.contains(link)) {
        suppressDesktopHoverAfterNavigation();
        closeDesktopPanel();
      }

      const mobileRoot = document.getElementById('breadcrumb-mobile');
      if (mobileRoot?.contains(link)) {
        closeMobilePanel();
      }
    });
  });
};

const bindSubmenuToggles = () => {
  document.querySelectorAll<HTMLElement>('[data-nav-submenu-toggle]').forEach(toggle => {
    if (toggle.dataset.bound === 'true') {
      return;
    }

    toggle.dataset.bound = 'true';
    toggle.addEventListener('click', event => {
      event.preventDefault();
      event.stopPropagation();

      const item = toggle.closest<HTMLElement>('[data-nav-item]');
      if (!item) {
        return;
      }

      const shouldOpen = !item.classList.contains('is-open');
      closeSiblings(item);
      openItem(item, shouldOpen);
    });
  });

  document
    .querySelectorAll<HTMLAnchorElement>('.site-nav-item-has-children > .site-nav-row > .site-nav-link')
    .forEach(link => {
      if (link.dataset.desktopToggleBound === 'true') {
        return;
      }

      link.dataset.desktopToggleBound = 'true';
      link.addEventListener('click', event => {
        if (!(event instanceof MouseEvent) || isModifiedClick(event)) {
          return;
        }

        const item = link.closest<HTMLElement>('[data-nav-item]');
        if (!item) {
          return;
        }

        const toggle = item.querySelector<HTMLElement>('[data-nav-submenu-toggle][data-nav-scope="desktop"]');
        if (!toggle) {
          return;
        }

        if (!item.classList.contains('is-open')) {
          event.preventDefault();
          closeSiblings(item);
          openItem(item, true);
        }
      });
    });

  document.querySelectorAll<HTMLElement>('[data-nav-item]').forEach(item => {
    if (item.dataset.focusBound === 'true') {
      return;
    }

    item.dataset.focusBound = 'true';
    item.addEventListener('focusin', () => {
      const toggle = item.querySelector<HTMLElement>('[data-nav-submenu-toggle]');
      if (!toggle) {
        return;
      }

      const scope = toggle.dataset.navScope;
      if (scope !== 'desktop') {
        return;
      }

      closeSiblings(item);
      openItem(item, true);
    });

    if (item.dataset.hoverBound === 'true') {
      return;
    }

    item.dataset.hoverBound = 'true';
    item.addEventListener('mouseenter', () => {
      clearDesktopHoverCloseTimer(item);

      if (!desktopHoverEnabled()) {
        return;
      }

      if (recentTouchInteraction()) {
        return;
      }

      if (desktopHoverSuppressed()) {
        return;
      }

      const toggle = item.querySelector<HTMLElement>('[data-nav-submenu-toggle][data-nav-scope="desktop"]');
      if (!toggle) {
        return;
      }

      closeSiblings(item);
      openItem(item, true);
    });

    item.addEventListener('mouseleave', event => {
      if (!desktopHoverEnabled()) {
        return;
      }

      if (recentTouchInteraction()) {
        return;
      }

      const toggle = item.querySelector<HTMLElement>('[data-nav-submenu-toggle][data-nav-scope="desktop"]');
      if (!toggle || item.contains(document.activeElement)) {
        return;
      }

      const destination = event.relatedTarget;
      if (destination instanceof Node && item.contains(destination)) {
        return;
      }

      scheduleDesktopHoverClose(item);
    });
  });
};

const bindDesktopTouchRowToggles = () => {
  document
    .querySelectorAll<HTMLElement>('.site-nav-item-has-children > .site-nav-row')
    .forEach(row => {
      if (row.dataset.desktopTouchBound === 'true') {
        return;
      }

      row.dataset.desktopTouchBound = 'true';
      row.addEventListener('pointerup', event => {
        const pointerType = pointerTypeFromEvent(event);
        if (pointerType !== 'touch' && pointerType !== 'pen') {
          return;
        }

        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }

        if (target.closest('[data-nav-submenu-toggle][data-nav-scope="desktop"]')) {
          return;
        }

        const clickedLink = target.closest<HTMLAnchorElement>('a.site-nav-link[href]');
        if (!clickedLink) {
          return;
        }

        const item = row.closest<HTMLElement>('[data-nav-item]');
        if (!item || item.classList.contains('is-open')) {
          return;
        }

        event.preventDefault();
        closeSiblings(item);
        openItem(item, true);
      });
    });
};

const bindMobileRowToggles = () => {
  document
    .querySelectorAll<HTMLElement>('#breadcrumb-mobile .site-mobile-nav-item-has-children > .site-mobile-nav-row')
    .forEach(row => {
      if (row.dataset.mobileRowBound === 'true') {
        return;
      }

      row.dataset.mobileRowBound = 'true';
      row.addEventListener('click', event => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }

        const item = row.closest<HTMLElement>('[data-nav-item]');
        if (!item) {
          return;
        }

        const clickedLink = target.closest<HTMLAnchorElement>('a.site-mobile-nav-link[href]');
        const isOpen = item.classList.contains('is-open');

        if (clickedLink) {
          if (isOpen) {
            closeMobilePanel();
            return;
          }

          event.preventDefault();
        } else {
          event.preventDefault();
        }

        closeSiblings(item);
        openItem(item, !isOpen);
      });
    });
};

const bindMobileToggle = () => {
  const mobileToggle = document.querySelector<HTMLElement>('[data-mobile-nav-toggle]');
  const mobilePanel = document.querySelector<HTMLElement>('[data-mobile-nav-panel]');

  if (!mobileToggle || !mobilePanel || mobileToggle.dataset.bound === 'true') {
    return;
  }

  mobileToggle.dataset.bound = 'true';

  mobileToggle.addEventListener('click', event => {
    event.preventDefault();
    event.stopPropagation();

    const shouldOpen = mobilePanel.hidden;
    mobilePanel.hidden = !shouldOpen;
    mobileToggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    document.body.classList.toggle('menu-open', shouldOpen);

    if (!shouldOpen) {
      closeMobilePanel();
    }
  });

  document.addEventListener('click', event => {
    const mobileRoot = document.getElementById('breadcrumb-mobile');
    if (mobileRoot && !mobileRoot.contains(event.target as Node)) {
      closeMobilePanel();
    }

    const desktopRoot = document.querySelector<HTMLElement>('[data-nav-scope-root="desktop"]');
    if (desktopRoot && !desktopRoot.contains(event.target as Node)) {
      closeDesktopPanel();
    }
  });

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      closeMobilePanel();
      closeDesktopPanel();
    }
  });

  document.addEventListener('focusin', event => {
    const desktopRoot = document.querySelector<HTMLElement>('[data-nav-scope-root="desktop"]');
    if (desktopRoot && !desktopRoot.contains(event.target as Node)) {
      closeDesktopPanel();
    }
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth >= 960) {
      closeMobilePanel();
    }
  });
};

const syncFixedMenuOffset = () => {
  const desktopHeader = document.getElementById('entete');
  if (!desktopHeader) {
    return;
  }

  const desktopStyles = window.getComputedStyle(desktopHeader);
  if (desktopStyles.display === 'none') {
    document.documentElement.style.removeProperty('--site-fixed-menu-top');
    return;
  }

  const headerBounds = desktopHeader.getBoundingClientRect();
  const nextTop = Math.max(Math.round(headerBounds.bottom + 14), 120);
  document.documentElement.style.setProperty('--site-fixed-menu-top', `${nextTop}px`);
};

export const initSiteNavigation = () => {
  bindPointerInteractionTracker();
  restoreDesktopHoverSuppression();
  bindSubmenuToggles();
  bindDesktopTouchRowToggles();
  bindMobileRowToggles();
  bindNavigationLinkClosers();
  bindMobileToggle();
  syncFixedMenuOffset();
  window.requestAnimationFrame(() => {
    syncFixedMenuOffset();
    alignOpenMegaPanels();
  });
};

document.addEventListener('DOMContentLoaded', () => {
  initSiteNavigation();
});

window.addEventListener('load', syncFixedMenuOffset);
window.addEventListener('resize', syncFixedMenuOffset);
window.addEventListener('resize', alignOpenMegaPanels);

export function toTop() {
  window.scrollTo({
    top: 0,
    behavior: 'smooth'
  });
}

window.addEventListener('scroll', () => {
  const remonter = document.getElementById('remonter');
  if (window.scrollY > 300) {
    remonter?.classList.add('visible');
  } else {
    remonter?.classList.remove('visible');
  }
});
