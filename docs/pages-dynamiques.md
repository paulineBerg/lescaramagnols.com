# Pages dynamiques (étape C)

Objectif : rendre n'importe quelle page via une seule route/template, alimentée par `backend/data/pages.json`.

## Données : `backend/data/pages.json`
- Clé racine `pages` (tableau).
- Chaque entrée minimale :
  - `slug` (unique, utilisé dans l'URL)
  - `status` (`published` ou `draft`, défaut : `published`)
  - `title` (optionnel, fallback : slug)
  - `blocks` : dictionnaire `EditRegion1..12` par défaut (contenu commun à toutes les langues)
  - `translations` : dictionnaire par langue (`fr`, `en`, `de`…) contenant `title`, `blocks`, `meta`.
  - `meta` : libre, `description` est utilisée pour la balise `<meta name="description">`.

Extrait simplifié :
```json
{
  "pages": [
    {
      "slug": "association",
      "status": "published",
      "translations": {
        "fr": {
          "title": "L'association Les Caramagnols",
          "blocks": {
            "EditRegion1": "<h1>L'association</h1>",
            "EditRegion2": "<p>Intro</p>",
            "EditRegion3": "<p>Contenu</p>"
          },
          "meta": { "description": "Présentation de l'association" }
        }
      }
    }
  ]
}
```

## Loader : `backend/core/content/pages_loader.php`
- `load_pages($path = null)`: lit le JSON, retourne un tableau normalisé ; JSON invalide → tableau vide + log.
- `get_page_by_slug($slug, $lang, $fallbackLang = DEFAULT_LANG)`: retourne une page publiée fusionnant blocs par défaut → blocs communs → blocs de la traduction ; applique fallback de langue.
- `pages_cache_clear($path = null)`: purge le cache mémoire (utile en tests).

Hypothèse sécurité : contenu considéré « de confiance » (édité par l'admin). Si besoin d'échapper, le layout reste maître ; seule la meta description est échappée dans le template dynamique.

## Route dynamique
- URL choisie : `/{slug}` (compatible avec préfixe langue déjà géré : `/fr/{slug}`).
- Priorité : pages physiques existantes d'abord, puis dynamique. Les routes statiques continuent de fonctionner.
- Implémentation dans `backend/core/router.php` : pattern `/{slug}` → recherche dans `pages.json` → template `pages/dynamic.php` ; sinon 404.

## Template unique : `backend/templates/pages/dynamic.php`
- Utilise `$blocks` comme les autres pages, même layout (`partials/layout.php`).
- Prend la page préchargée par le routeur via `$GLOBALS['currentDynamicPage']` (pas de 2e I/O).
- Si meta.description définie → injectée dans `EditRegion10` sous forme de balise meta échappée.
- Si page absente/non publiée → 404 habituelle.

## Ajouter une page
1. Éditer `backend/data/pages.json` et ajouter une entrée dans `pages` avec un `slug` unique.
2. Ajouter les blocs nécessaires (`EditRegion1..12`).
3. Visiter `/{slug}` (ou `/fr/{slug}` selon la langue courante).

## Tests
- `backend/tests/Content/PagesLoaderTest.php`
- `backend/tests/DynamicRouteTest.php`

## TODO (étape suivante - admin CRUD)
- Interface d'édition JSON (CRUD) avec validation forte.
- Prévisualisation draft (`status=draft`), éventuellement via paramètre réservé ou auth admin.
- Mise en cache disque/OPcache + bust lors des sauvegardes.
- Ajout de champs SEO (title, og:*, canonical) dans le template.
