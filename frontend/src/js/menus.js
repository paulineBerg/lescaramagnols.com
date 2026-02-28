// /src/js/menus.js

document.addEventListener('DOMContentLoaded', () => {
  // === Menu principal (desktop) : ouverture au survol ===
  const allMenuItems = document.querySelectorAll('#menu2 li');
  const hamburger = document.getElementById('hamburger-icon');
  const breadcrumb = document.getElementById('breadcrumb-mobile');
  const mobileNav = breadcrumb ? breadcrumb.querySelector('.breadcrumb-menu') : null;

  const closeDesktopMenus = () => {
    allMenuItems.forEach(item => item.classList.remove('open'));
  };

  const closeMobileMenus = () => {
    if (!breadcrumb) {
      return;
    }

    breadcrumb.classList.remove('open');
    document.body.classList.remove('menu-open');
    if (hamburger) {
      hamburger.setAttribute('aria-expanded', 'false');
    }

    if (mobileNav) {
      mobileNav.classList.remove('open');
      mobileNav.querySelectorAll('li.open').forEach(el => el.classList.remove('open'));
    }
  };

  allMenuItems.forEach(item => {
    const submenu = item.querySelector(':scope > ul');

    if (!submenu) {
      return;
    }

    item.addEventListener('mouseenter', () => {
      const siblings = item.parentElement.querySelectorAll(':scope > li');
      siblings.forEach(sibling => {
        if (sibling !== item) {
          sibling.classList.remove('open');
        }
      });
      item.classList.add('open');
    });

    item.addEventListener('mouseleave', () => {
      item.classList.remove('open');
    });
  });

  // Ferme les menus desktop si clic en dehors
  document.addEventListener('click', event => {
    closeDesktopMenus();

    if (breadcrumb && !breadcrumb.contains(event.target)) {
      closeMobileMenus();
    }
  });

  if (hamburger && breadcrumb) {
    hamburger.addEventListener('click', event => {
      event.stopPropagation();

      const shouldOpen = !breadcrumb.classList.contains('open');
      breadcrumb.classList.toggle('open', shouldOpen);
      document.body.classList.toggle('menu-open', shouldOpen);
      hamburger.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');

      if (mobileNav) {
        mobileNav.classList.toggle('open', shouldOpen);
        mobileNav.querySelectorAll('li.open').forEach(el => el.classList.remove('open'));
      }
    });
  }

  if (mobileNav) {
    mobileNav.querySelectorAll('.has-submenu > a, .has-submenu > span').forEach(el => {
      el.addEventListener('click', event => {
        event.preventDefault();
        event.stopPropagation();

        const parent = el.closest('li');
        if (!parent) {
          return;
        }

        const siblings = parent.parentElement?.querySelectorAll(':scope > li') ?? [];
        siblings.forEach(sibling => {
          if (sibling !== parent) {
            sibling.classList.remove('open');
          }
        });

        parent.classList.toggle('open');
      });
    });
  }
});

// === Fleche "remonter" ===
export function toTop() {
  window.scrollTo({
    top: 0,
    behavior: 'smooth'
  });
}

// Affiche ou masque le bouton "remonter" au scroll
window.addEventListener('scroll', () => {
  const remonter = document.getElementById('remonter');
  if (window.scrollY > 300) {
    remonter?.classList.add('visible');
  } else {
    remonter?.classList.remove('visible');
  }
});
