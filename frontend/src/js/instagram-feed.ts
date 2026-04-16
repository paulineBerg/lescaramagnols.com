const DEFAULT_INTERVAL_MS = 5500;
const MIN_INTERVAL_MS = 2500;
const MAX_INTERVAL_MS = 30000;

const normalizeInterval = (value: string | undefined): number => {
  const parsed = Number.parseInt(value ?? '', 10);
  if (Number.isNaN(parsed)) {
    return DEFAULT_INTERVAL_MS;
  }

  return Math.max(MIN_INTERVAL_MS, Math.min(MAX_INTERVAL_MS, parsed));
};

const wrapIndex = (value: number, total: number): number => {
  if (total <= 0) {
    return 0;
  }

  return ((value % total) + total) % total;
};

export const initInstagramFeeds = (): void => {
  const roots = document.querySelectorAll<HTMLElement>('[data-instagram-feed]');

  roots.forEach(root => {
    const track = root.querySelector<HTMLElement>('[data-instagram-track]');
    if (!track) {
      return;
    }

    const items = Array.from(root.querySelectorAll<HTMLElement>('[data-instagram-item]'));
    if (items.length === 0) {
      return;
    }

    const dots = Array.from(root.querySelectorAll<HTMLButtonElement>('[data-instagram-dot]'));
    const prevButton = root.querySelector<HTMLButtonElement>('[data-instagram-prev]');
    const nextButton = root.querySelector<HTMLButtonElement>('[data-instagram-next]');
    const intervalMs = normalizeInterval(root.dataset.rotationMs);
    let currentIndex = 0;
    let timer: number | null = null;

    const render = () => {
      const nextOffset = currentIndex * -100;
      track.style.transform = `translate3d(${nextOffset}%, 0, 0)`;

      items.forEach((item, index) => {
        item.setAttribute('aria-hidden', index === currentIndex ? 'false' : 'true');
      });

      dots.forEach((dot, index) => {
        const isActive = index === currentIndex;
        dot.classList.toggle('is-active', isActive);
        dot.setAttribute('aria-current', isActive ? 'true' : 'false');
      });
    };

    const goTo = (nextIndex: number) => {
      currentIndex = wrapIndex(nextIndex, items.length);
      render();
    };

    const stopAuto = () => {
      if (timer === null) {
        return;
      }

      window.clearInterval(timer);
      timer = null;
    };

    const startAuto = () => {
      stopAuto();

      if (items.length <= 1) {
        return;
      }

      timer = window.setInterval(() => {
        goTo(currentIndex + 1);
      }, intervalMs);
    };

    prevButton?.addEventListener('click', () => goTo(currentIndex - 1));
    nextButton?.addEventListener('click', () => goTo(currentIndex + 1));

    dots.forEach(dot => {
      dot.addEventListener('click', () => {
        const index = Number.parseInt(dot.dataset.instagramDot ?? '', 10);
        if (Number.isNaN(index)) {
          return;
        }

        goTo(index);
      });
    });

    root.addEventListener('mouseenter', stopAuto);
    root.addEventListener('mouseleave', startAuto);
    root.addEventListener('focusin', stopAuto);
    root.addEventListener('focusout', event => {
      const nextTarget = event.relatedTarget;
      if (!(nextTarget instanceof Node) || !root.contains(nextTarget)) {
        startAuto();
      }
    });

    document.addEventListener('visibilitychange', () => {
      if (document.hidden) {
        stopAuto();
        return;
      }

      startAuto();
    });

    render();
    startAuto();
  });
};

