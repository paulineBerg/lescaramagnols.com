# Audit CMS – Les Caramagnols (1 mars 2026)

## Nouveautés mini‑CMS (étape C)
- Source de pages JSON : `backend/data/pages.json` (slug, status, blocs EditRegion*, translations/meta par langue).
- Route dynamique : `/site/{slug}` (compatible préfixe langue `/fr/...`). Priorité aux templates physiques, sinon rendu dynamique.
- Template unique : `backend/templates/pages/site/dynamic.php` réutilise `layout.php` et remplit les blocs; 404 si slug absent ou draft.
- Loader : `backend/core/content/pages_loader.php` (cache, fallback vide, logs JSON invalide), helper `get_page_by_slug()`.
- Doc rapide : `docs/pages-dynamiques.md` (schéma, URL, règles de langue, TODO admin CRUD).

## Vue d’ensemble (mise à jour)
Le module « CMS » n’est pas actif. L’objectif évolue vers l’édition des contenus et menus du site (template unique), pas un CMS classique.
État actuel :
- Auth admin OK (email + mot de passe, CSRF, session).
- Tableau de bord statique.
- Endpoint JSON `core/CMS/save_article.php` (validation seule, pas de persistance).
- Pages `backend/templates/pages/CMS/*` vides.

Conclusion : pas de stockage ni d’affichage dynamique.

## Construction actuelle d’une page (site)
- **Route** : `core/router.php` résout l’URL vers un fichier PHP sous `backend/templates/pages/site/...`.
- **Page** : chaque fichier initialise un tableau `$blocks` (ex : `EditRegion1..12`) avec HTML/texte traduit via `t()`.
- **Layout** : `templates/partials/layout.php` charge les menus (`menu_loader`), inclut header/footer, puis rend `templates/partials/contenu.php` qui affiche les blocs dans une grille fixe (haut, colonnes, bas, etc.).
- **Menus** : chargés depuis `data/menus.json` si présent, sinon `config/menu_data.php`; rendus par `menus_header.php` + menu mobile.
- **Blocs disponibles** (usage courant) :
  - EditRegion1 : image/titre haut
  - EditRegion2 : colonne 40% intro
  - EditRegion8 : colonne 25% (optionnelle)
  - EditRegion3 : corps principal
  - EditRegion4 : bloc bas centre
  - EditRegion5/6/7 : petits blocs bas (gauche/droite/centre)
  - EditRegion9/11/12 : zones réservées/pied
- **Scripts** : `scripts_head.php` charge Vite/manifest ; `scripts_body_bas.php` ajoute JS inline (avec nonce CSP).

Implication pour l’édition : pour chaque page, il suffit de renseigner les blocs dans une structure de données (JSON ou BDD) et d’injecter ces blocs dans le template unique (`layout.php` + `contenu.php`).

## Partials clés (audit rapide)
- `layout.php` : shell global (charge menus, header/footer, appelle `contenu.php`, flèche remonter, scripts bas).
- `contenu.php` : placement des blocs `EditRegion*` dans la grille (haut, colonnes, bas).
- `header.php` / `footer.php` : entête HTML et pied spécifique (meta, favicons supplémentaires si besoin).
- `menus_header.php` : rendu des menus principaux (desktop + mobile) à partir des données chargées.
- `menus_footer.php` : menu du pied de page (menu3).
- `menus_fixes.php` : menus latéraux gauche/droite fixes.
- `scripts_head.php` : injecte CSS/JS Vite (manifest) et meta; doit inclure le nonce CSP sur les scripts.
- `scripts_body.php` / `scripts_body_bas.php` : hooks JS inline (ex : ajout `?lang` sur liens, JSON-LD). À passer au nonce CSP.
- `sitemap.php` : génération du plan du site (statique/legacy).

## Architecture actuelle
- **Admin** : `backend/public/site/<ADMIN_LOGIN_PATH>/` (défaut `adminFtyhik5642sZ`, défini dans `.env`).
  - `index.php` (login), `dashboard.php` (infos statiques), `layout.php`, `logout.php`.
  - Auth helpers : `backend/core/auth/admin.php`.
- **API “CMS”** : `backend/core/CMS/save_article.php`
  - Méthode POST JSON uniquement.
  - Rate limiter session (10 requêtes / 120 s).
  - Validation : titre, slug, contenu (HTML limité), tags, traductions, commentaires.
  - Réponse : `{data: {title, slug, ...}}` ou erreurs 4xx/429.
  - **Pas de persistance** (TODO).
- **Pages publiques** : `backend/templates/pages/CMS/{index,article,proposer}.php` sont des stubs qui incluent `layout.php` sans contenu dynamique.
- **Routage** : mini-routeur fichier (`core/router.php`) sert les pages si elles existent, aucun mapping spécifique pour le CMS.

## Sécurité & conformité
- CSRF pour l’admin et pour l’endpoint via session.
- Rate limiting simple par session.
- Auth admin dépend de `.env` (hash bcrypt). Pas de rôles ni de permissions fines.
- Pas d’anti-spam pour commentaires (seulement validation de base).

## Données & persistance (manquantes)
- Pas de schéma BDD pour les articles/commentaires (le SQL d’exemple n’est pas relié).
- Pas de dépôt JSON fallback.
- Pas de migration, pas de seed.

## UX / Front
- Aucune liste d’articles ni vue article. Les pages CMS sont vides.
- Pas de pagination, pas de filtres, pas de recherche connectée au CMS (le moteur de recherche existant indexe seulement les templates statiques).

## Tests
- Tests présents sur auth/validation/lang, mais aucun test sur le CMS (API, routes, permissions).

## Risques / points faibles
- Données non stockées → perte de contenu.
- Pas de contrôle d’accès par ressource (un admin unique seulement).
- Pas de protection anti-spam sur commentaires.
- Pas d’aperçu (preview) ni d’états (brouillon/publié).

## Actions recommandées (MVP fonctionnel)
1) **Persistance** : créer un modèle Article + Comment (MySQL ou JSON) avec schéma minimal : id, slug unique, title, content, lang, tags, status, timestamps.
2) **CRUD admin** : formulaires create/update/delete, liste avec pagination, changement d’état (brouillon/publié).
3) **Routes publiques** : `/CMS` (liste), `/CMS/<slug>` (vue article), 404 si absent.
4) **Sécurité** : permissions simples (admin unique OK), CSRF sur formulaires, anti-spam basique (honeypot + rate limit IP) pour commentaires.
5) **Tests** : PHPUnit (API save_article avec persistance, auth/permissions) et Vitest/Playwright éventuel pour le flux publish.
6) **Recherche** : brancher le moteur de recherche sur les articles publiés (génération d’index).

## Plan de mise en œuvre suggéré (iteratif)
1) Schéma + persistance (DAO ou repository) + tests de contrat.
## Actions réorientées (édition de contenu + menus)
1) **Menus** : `data/menus.json` source editable (menu principal/hamburger), fallback `config/menu_data.php`.
2) **Pages** : DONE pour le runtime public (JSON + route `/site/{slug}` + template unique). Reste à brancher l’admin CRUD + preview draft.
3) **Admin UI** : écrans « Pages » et « Menus » (CRUD simple, CSRF) pour éditer les JSON avec backups.
4) **Template unique** : déjà en place pour le dynamique; prévoir SEO avancé (title/OG) et preview.
5) **Tests** : loaders menus/pages, route dynamique (200/404/preview), sauvegarde + backup.
6) **Migration progressive** : cohabitation avec les pages PHP existantes ; prioriser les pages critiques à migrer.
