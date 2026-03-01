# README_CMS_PLANNING — Planning & priorités (mini CMS “CMS”)
*(Les Caramagnols — plan de construction + optimisations, version OVH/MySQL/phpMyAdmin, build Vite en local)*


> **But** : remplacer les pages/menus “en dur” par un mini CMS **mono-admin** permettant :
> - CRUD du **menu haut** (créer / modifier / supprimer / ordonner)
> - CRUD des **pages** (créer / modifier / supprimer / publier)
> - **Multilingue par page** (FR/EN/DE)
> - Chaque item de menu pointe vers une page
> - **Même structure/design** pour toutes les pages (layout unique + `EditRegion*`)
>
> **Contrainte déploiement** : OVH, base MySQL via **phpMyAdmin**, build Vite **en local**, site déposé dans `caramagnols/`.

---

## 0) Décisions figées (validées)
- **DB** : MySQL (OVH) via **PDO**
- **Gestion DB prod** : phpMyAdmin (imports SQL)
- **Build frontend** : local (on upload les assets buildés)
- **Admin** : mono-admin (un seul compte)
- **Langues** : `fr`, `en`, `de`
- **URLs canoniques** :
  - Accueil : `/{lang}/` → page `home` (ex: `/fr/`)
  - Page : `/{lang}/{slug}` (ex: `/fr/association`)
  - Optionnel (qualité/SEO) : redirections vers canonique depuis `/` et `/{slug}` si supporté
- **Design** : inchangé, rendu via ton layout existant et ses régions `EditRegion*` (définies dans `templates/partials/`)

---

## 1) Pourquoi on parle de “BLOG” (et pas CMS)
Même si tu appelles ça “BLOG”, fonctionnellement c’est un **CMS léger** :
- le contenu est structuré en pages
- un menu navigue vers ces pages
- le layout est unique
- l’admin modifie les contenus et la navigation

## PHASE 0 — Refactor sémantique : Blog → CMS

### 🎯 Objectif

Remplacer toute occurrence du terme **"Blog"** par **"CMS"** dans :

- Documentation
- Nommage des dossiers
- Nommage des fichiers
- Nommage des classes et fonctions
- Routes admin si concernées

But :  
Aligner le vocabulaire avec la vraie nature du système (mini CMS) et éviter toute confusion future.

---

## 📚 Documentation

- [ ] Renommer `README_BLOG.md` → `README_CMS.md`
- [ ] Renommer `README_BLOG_PLANNING.md` → `README_CMS_PLANNING.md`
- [ ] Remplacer toutes occurrences "blog" par "CMS" dans :
  - README principal
  - dossier `docs/`
  - commentaires du code

---

## 📂 Dossiers

Si existants :

- [ ] `backend/core/blog/` → `backend/core/cms/`
- [ ] `backend/templates/blog/` → `backend/templates/cms/`
- [ ] `backend/public/site/.../blog/` → `.../cms/`

⚠ Important :  
Mettre à jour tous les `require`, `include`, `use`, autoload, etc.

---

## 📄 Fichiers

- [ ] `blog_loader.php` → `cms_loader.php`
- [ ] `save_blog.php` → `save_page.php` ou `cms_save_page.php`
- [ ] `blog_list.php` → `cms_pages_list.php`
- [ ] `blog_edit.php` → `cms_pages_edit.php`

---

## 🧠 Classes / Fonctions

- [ ] `BlogRepository` → `CmsPageRepository`
- [ ] `BlogController` → `CmsController`
- [ ] Fonctions `saveBlog()` → `saveCmsPage()`

---

## 🌐 Routes admin

Si route `/admin/blog/...` :

- [ ] `/admin/blog` → `/admin/cms`
- [ ] Ajouter redirection temporaire 301 pour compatibilité

---

## 🛡 Vérification après refactor

- [ ] Tester login admin
- [ ] Tester sauvegarde page
- [ ] Tester gestion menu
- [ ] Vérifier aucune inclusion cassée
- [ ] Rechercher dans tout le projet : `grep -R "blog"`

---

## 📌 Ordre recommandé d’exécution

1. Renommer README
2. Renommer dossiers
3. Renommer fichiers
4. Mettre à jour imports / includes
5. Tester complètement
6. Commit séparé :

---

## 2) Architecture cible (simple, compatible avec l’existant)

### 2.1 Front (rendu)
- Le routeur détecte `lang` + `slug`
- Le backend charge la page en DB + traduction
- Il construit `$blocks` (ex : `EditRegion1`, `EditRegion2`, …)
- Il inclut **le même layout** que les pages actuelles

### 2.2 Admin (édition)
- L’admin propose :
  - Pages : liste + edit multi-lang + publish/draft
  - Menus : liste items + CRUD + ordre + cible (page/url)

### 2.3 Assets
- Vite build en local → upload dans `backend/public/assets` + `backend/public/.vite` (ou structure actuelle)

---

## 3) Schéma DB MySQL (MVP)

> Objectif : supporter pages + traductions + menus sans complexité inutile.

### 3.1 Tables
**pages**
- `id` INT PK AI
- `slug` VARCHAR(190) UNIQUE NOT NULL
- `status` ENUM('draft','published') NOT NULL DEFAULT 'draft'
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

**page_translations**
- `id` INT PK AI
- `page_id` INT NOT NULL (FK pages.id)
- `lang` CHAR(2) NOT NULL
- `title` VARCHAR(255) NOT NULL
- `meta_description` VARCHAR(255) NULL
- `blocks_json` LONGTEXT NOT NULL *(JSON contenant EditRegion*)*
- UNIQUE(`page_id`,`lang`)

**menus**
- `id` INT PK AI
- `code` VARCHAR(50) UNIQUE NOT NULL *(ex: header, footer)*
- `label` VARCHAR(255) NULL

**menu_items**
- `id` INT PK AI
- `menu_id` INT NOT NULL
- `parent_id` INT NULL *(sous-menus, optionnel MVP)*
- `position` INT NOT NULL DEFAULT 0
- `label_text` VARCHAR(255) NOT NULL
- `type` ENUM('page','url') NOT NULL
- `page_id` INT NULL
- `url` VARCHAR(2048) NULL
- `is_visible` TINYINT(1) NOT NULL DEFAULT 1

---

## 4) Priorités (P0/P1/P2) — ce qu’on fait en premier

### P0 — Indispensable (avant mise en prod)
1) DB installable via phpMyAdmin (fichiers SQL versionnés)
2) Moteur pages dynamiques depuis DB (home + slug + 404)
3) Admin Pages CRUD multi-lang + publish/draft
4) Admin Menu header CRUD + ordre + lien page
5) Front : le menu header lit la DB et pointe vers `/{lang}/{slug}`

### P1 — Confort (UX / productivité)
6) Éditeur riche + mode HTML
7) Prévisualisation d’un brouillon (preview)
8) Ordre menu plus ergonomique (drag & drop) + sous-menus

### P2 — Qualité / SEO / robustesse
9) Sitemap dynamique
10) Canonical + OG tags
11) Redirections 301 si slug change
12) Logs + tests + sauvegardes

---

## 5) Planning par étapes (phases) + checklists + critères “Done”

> Chaque phase est autonome : tu sais quand c’est “fini”.

---

### ✅ PHASE 0 — Installation DB (OVH/phpMyAdmin) + config PDO
**But** : pouvoir installer le CMS en prod sans SSH ni CLI.

**Livrables**
- `database/001_init.sql` (tables + index + FK si possible)
- `database/002_seed.sql` (home + header menu)
- `docs/INSTALL_OVH.md` (pas à pas import phpMyAdmin)
- `backend/core/db.php` + `backend/config/database.php` (PDO)

**Checklist**
- [ ] Écrire `001_init.sql` (tables `pages`, `page_translations`, `menus`, `menu_items`)
- [ ] Écrire `002_seed.sql` :
  - [ ] créer menu `header`
  - [ ] créer page `home` en `published`
  - [ ] créer traductions `fr/en/de`
  - [ ] `blocks_json` contient **toutes** les clés `EditRegion*` attendues (même vides)
- [ ] Ajouter config DB via variables d’environnement (ne rien hardcoder)
- [ ] Documenter l’import via phpMyAdmin + variables `.env`

**Done si**
- Import 001 puis 002 dans phpMyAdmin = OK
- Le site se connecte à MySQL et peut lire au moins la page `home`

---

### ✅ PHASE 1 — Moteur “pages dynamiques” (front)
**But** : rendre toutes les pages depuis MySQL avec le layout unique.

**Livrables**
- `PageRepository` (ou fonctions équivalentes)
- Routes `/{lang}/` et `/{lang}/{slug}`
- Template unique dynamique
- 404 propre

**Checklist**
- [ ] Implémenter `findPublishedBySlug(slug, lang)` :
  - [ ] status published requis
  - [ ] traduction lang demandée sinon fallback `fr`
  - [ ] `blocks_json` → `$blocks` (tableau)
- [ ] Implémenter `home` : slug `home`
- [ ] Router :
  - [ ] `/{lang}/` → `home`
  - [ ] `/{lang}/{slug}` → page
  - [ ] gérer langues invalides (404 ou redirect vers langue par défaut)
- [ ] Template :
  - [ ] `$blocks` passé au layout
  - [ ] `$title` et `$meta_description` gérés si le layout les utilise
- [ ] 404 :
  - [ ] slug inconnu → HTTP 404
  - [ ] draft → HTTP 404

**Done si**
- `/fr/` affiche `home` depuis DB
- `/fr/test` affiche une page si elle existe
- slug inconnu → 404
- design identique aux pages legacy

**Note dossier `caramagnols/`**
- [ ] Vérifier que les redirections et liens respectent un éventuel base path `/caramagnols`
  - si l’URL publique est `https://domaine.tld/caramagnols/fr/…`, il faut un “BASE_URL” ou détection automatique.

---

### ✅ PHASE 2 — Admin Pages CRUD (multi-lang + publish/draft)
**But** : éditer le contenu sans toucher au code.

**Livrables**
- Page admin “liste pages”
- Page admin “éditer page”
- Endpoints POST : save/delete/publish

**Checklist**
- [ ] Liste pages :
  - [ ] slug, status, updated_at
  - [ ] actions : éditer / supprimer
- [ ] Édition page :
  - [ ] slug (validation + unique)
  - [ ] status draft/published
  - [ ] onglets FR/EN/DE
  - [ ] title + meta_description
  - [ ] champs pour chaque `EditRegion*` (structure existante)
- [ ] Endpoints :
  - [ ] sauvegarde page + traductions (transaction)
  - [ ] suppression page + traductions (transaction)
- [ ] Sécurité :
  - [ ] admin session
  - [ ] CSRF
  - [ ] (option P0) rate-limit sur actions sensibles

**Done si**
- Tu crées une page “association” en admin
- Tu remplis FR/EN/DE
- Tu publies → visible sur `/fr/association`
- Draft → invisible (404)

---

### ✅ PHASE 3 — Admin Menus CRUD (header) + rendu front depuis DB
**But** : gérer le menu haut depuis admin + liens vers pages.

**Livrables**
- Page admin “menu header”
- Endpoints CRUD items
- Menu front alimenté DB

**Checklist**
- [ ] Admin menu header :
  - [ ] ajouter item type page (select page)
  - [ ] ajouter item type url
  - [ ] modifier label / cible / visible
  - [ ] supprimer item
  - [ ] ordre MVP : `position` + boutons ↑ ↓
  - [ ] (option P1) sous-menus via `parent_id`
- [ ] Front :
  - [ ] loader menu DB
  - [ ] URL type page → `/{lang}/{slug}`
  - [ ] URL type url → URL direct
  - [ ] conserver le HTML/CSS existant du header (menus)

**Done si**
- Tu modifies le menu dans l’admin
- Le front change immédiatement
- Les liens mènent aux pages dans la langue courante

---

### ✅ PHASE 4 — Éditeur riche + mode HTML (P1)
**But** : éditer vite et bien.

**Livrables**
- WYSIWYG dans l’admin (Vite)
- Toggle mode HTML

**Checklist**
- [ ] Intégrer un éditeur (TipTap/Quill/TinyMCE)
- [ ] Toggle “HTML” (textarea) pour chaque bloc ou global
- [ ] Sauver HTML dans `blocks_json`
- [ ] Sécurité minimale :
  - [ ] refuser `<script>` à la sauvegarde (MVP)
  - [ ] documenter la politique (contenu admin = de confiance mais on bloque scripts)

**Done si**
- WYSIWYG fonctionne
- Mode HTML fonctionne
- Pas de script stocké

---

### ✅ PHASE 5 — SEO & robustesse (P2)
**Checklist**
- [ ] sitemap.xml dynamique (pages published)
- [ ] canonical + OG tags
- [ ] redirections 301 si slug changé
- [ ] journaux (logs) + tests (au moins repository pages)

---

## 6) “Optimisations” (ce qu’il faut optimiser en priorité)
> Ici “optimisation” = réduire le risque + faciliter l’évolution + améliorer la perf/SEO sans tout réécrire.

### 6.1 Optimisations P0 (sécurité & fiabilité)
- [ ] **Transactions** SQL sur save/delete (pages + translations)
- [ ] **Validation slug** stricte + unique
- [ ] **CSRF** sur toutes les actions admin (déjà présent dans le projet : à réutiliser)
- [ ] **Gestion erreurs DB** propre (pas d’erreurs affichées en prod)
- [ ] **404** cohérente (draft invisible)
- [ ] **Base path `/caramagnols`** : centraliser un helper `base_url()` pour tous les liens/redirections

### 6.2 Optimisations P1 (performance simple)
- [ ] Cache menu header (mémoire requête ou cache très court)
- [ ] Réduire le nombre de requêtes :
  - page + traduction en 1 requête JOIN
  - menu + items en 1 requête
- [ ] Index DB :
  - `pages.slug` unique
  - `page_translations(page_id, lang)` unique/index
  - `menu_items(menu_id, parent_id, position)` index

### 6.3 Optimisations P2 (SEO)
- [ ] Canonical en `/{lang}/{slug}`
- [ ] Sitemap basé sur pages publiées
- [ ] Balises OG title/description
- [ ] Redirections 301 si slug change (table `redirects` optionnelle)

---

## 7) Backlog (tableau “à exécuter”)

### P0 — Sprint 1 (Fondations)
- [ ] `database/001_init.sql`
- [ ] `database/002_seed.sql`
- [ ] `docs/INSTALL_OVH.md`
- [ ] `core/db.php` + config env
- [ ] `PageRepository` + routes `/{lang}/` + `/{lang}/{slug}`
- [ ] template dynamique unique + 404
- [ ] base_url helper (pour `/caramagnols`)

### P0 — Sprint 2 (Admin Pages)
- [ ] admin pages list
- [ ] admin page edit multi-lang
- [ ] endpoints save/delete/publish
- [ ] validations + transactions + CSRF

### P0 — Sprint 3 (Menus)
- [ ] admin menu header CRUD
- [ ] ordre ↑↓ (position)
- [ ] front menu depuis DB

### P1 — Sprint 4 (Éditeur)
- [ ] WYSIWYG + toggle HTML
- [ ] blocage `<script>`

### P2 — Sprint 5 (SEO / Robustesse)
- [ ] sitemap + canonical + OG
- [ ] 301 slug change
- [ ] logs + tests

---

## 8) Definition of Done (DoD) — quand on dit “c’est fini”
Une fonctionnalité est “DONE” si :
- [ ] elle a une UI (si admin) + un endpoint sécurisé (CSRF + session)
- [ ] elle fonctionne en FR/EN/DE si concernée
- [ ] elle ne casse pas les pages legacy existantes
- [ ] elle gère les erreurs (DB down, slug inconnu, etc.)
- [ ] elle est documentée (au moins une section dans ce planning ou INSTALL_OVH)

---

## 9) Notes pratiques OVH / déploiement
- DB : import SQL via phpMyAdmin (001 puis 002)
- Build Vite : fait en local, on upload les fichiers buildés
- Dossier `caramagnols/` :
  - soit c’est la racine web (idéal)
  - soit c’est un sous-dossier accessible via `/caramagnols/…`
  - Dans tous les cas, utiliser un helper `base_url()` ou config `BASE_PATH=/caramagnols` pour liens/redirections.

---

## 10) Prochain “pas” recommandé
Si tu veux avancer sans te perdre, l’ordre le plus efficace est :
1) **PHASE 0** : écrire les SQL + doc OVH + PDO
2) **PHASE 1** : rendre `home` depuis DB en `/fr/`
3) **PHASE 2** : admin pages CRUD multi-lang
4) **PHASE 3** : admin menus CRUD + front DB
5) Ensuite : éditeur riche + SEO

---

## 11) TODO (plus tard)
- Upload d’images (media library)
- Historique versions (versioning)
- Prévisualisation brouillon par URL token
- Sous-menus avancés (drag & drop)
- Recherche full-text (si besoin)

---

*Fin du fichier — prêt à copier/coller en `README_CMS_PLANNING.md`.*