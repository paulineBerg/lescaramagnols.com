// LesCaramagnols -->
// src/js/i18n.js -->

export async function loadTranslations(lang = 'fr') {
  try {
    const response = await fetch(`core/api/lang.php?lang=${lang}`);
    if (!response.ok) throw new Error('Erreur de chargement des traductions');

    const data = await response.json();
    return data;
  } catch (error) {
    console.error('Erreur i18n :', error);
    return {};
  }
}

// Appliquer les traductions à la page
export function applyTranslations(translations) {
  document.querySelectorAll('[data-i18n]').forEach(el => {
    const key = el.getAttribute('data-i18n');
    if (translations[key]) {
      el.innerHTML = translations[key];
    }
  });
}
