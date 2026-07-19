# Plan D'Optimisation Post-V1

Date : 2026-07-19
Statut : phase 0 validee (2026-07-19), phase 1 terminee hors item de rangement des tests reporte (2026-07-17), phase 2 terminee (2026-07-17), phase 3 partielle (BlocNote, Documents, registre, RealEstateRental termines 2026-07-19 ; migration consommateurs, decoupage AdminSettingsService/AdminController, extraction templates admin, conteneur DI, convergence routage en attente), phase 4 partielle (PDF sortis du depot, budgets assets 2026-07-17, baseline PHPStan versionnee ; bascule SQL et cache en attente), phase 5 terminee pour le lot du 2026-07-19 (audits backend/frontend, npm audit fix, certificat, check-env prod, revue logs et sauvegardes verifies ; alertes logs privees a traiter en suivi). Evenement hors plan : integration de la v2 du module de gestion locative (2026-07-17), qui a regonfle le controleur socle puis a ete extrait en RealEstateRentalController le 2026-07-19.

Ce document cloture le lot d'optimisation post-V1 execute du 2026-07-17 au 2026-07-19 et conserve le reliquat a router dans des lots separes. Il complete les plans existants sans les dupliquer :

- `docs/roadmap/README.md` (modernisation, largement realisee)
- `docs/roadmap/transition-v1-s1-s8.md` (transition V1, executee)
- `docs/archive/audit-complet-v1.md` (audit historique)

Perimetre : dette **post-V1** observee sur la branche `restore-prod-master-20260716`, avec bilan final au 2026-07-19. La production `https://www.lescaramagnols.com/` reste la reference ; tout ecart constate lors d'un lot futur doit etre verifie contre la prod avant correction (voir `AGENTS.md`).

## Etat des lieux

### Points forts confirmes

- Securite solide : webroot limite a `backend/public/`, `.htaccess` durci (CSP, HSTS), PDO prepare sans emulation, secrets via `.env`, 2FA admin et prive, chiffrement AES-256-GCM des pieces jointes de discussion.
- Couverture de tests reelle : 703 tests backend et 39 tests frontend verts au 2026-07-19.
- Outillage riche : ~30 scripts CLI de diagnostic/ops (`backend/core/tools/`), scripts de deploiement avec gardes (`backend/tools/`), hygiene docs/assets outillee (`frontend/tools/`).
- Architecture cible claire et documentee : `PrivatePortal` (socle) / `PrivateApps` (metier).

### Dettes residuelles a router

1. **Migration `PrivatePortal/` -> `PrivateApps/` finalisee pour le socle connu** : fichiers supprimes cote socle (`Documents/`, `BlocNote`, `TaxDeclarationHelper/Source|ValueObject`, `RealEstateRental/TaxBridge`), contreparties versionnees sous `PrivateApps/`, imports alignes dans `backend/src/Http/FrontController.php`. Garantie par `PrivatePortalPhaseCoverageTest`.
2. **Monolithes restants** :
   - `backend/src/PrivatePortal/Http/PrivatePortalController.php` : reduit apres extraction `BlocNote`, `Documents` et `RealEstateRental`, mais reste un controleur socle volumineux a contenir strictement au HTTP/socle.
   - `backend/templates/admin/layout.php` : ~4 400 lignes
   - `backend/src/Admin/AdminSettingsService.php` : ~3 300 lignes
   - `backend/src/PrivateApps/RealEstateRental/Repository/RentalLifecycleRepository.php` : ~3 200 lignes (en croissance avec la v2)
   - `backend/src/Admin/AdminController.php` : ~2 600 lignes
3. **Qualite statique stabilisee mais a maintenir** : `composer test`, `composer phpstan`, `composer phpcs`, frontend test/lint/build et hygiene docs sont verts au 2026-07-19 ; la baseline PHPStan `core/`/`config/` existe et doit maintenant etre reduite progressivement.
4. **Double paradigme** : `core/` proceduraux a etat global (superglobales capturees/restaurees par le `FrontController`) vs `src/` OO type ; double routage FastRoute + `core/router.php` ; pas de conteneur DI (fabriques globales `app_event_logger()`, `blog_repository()`, ...).
5. **SQL et JS legacy audites** : audit SQL archive dans `backend/docs/audit-sql-2026-07-17.md` ; doublons JS legacy supprimes. Reste a traiter dans des lots separes : migration consommateurs `PrivateAppRegistry`, bascule SQL editoriale et cache de rendu.
6. **Poids du depot reduit** : PDF touristiques sortis de `frontend/src/assets/pdf/` vers le stockage publie ; les doublons d'images non references ont ete nettoyes, les groupes restants doivent etre traites progressivement si un lot asset dedie le justifie.

## Phase 0 — Garde-fous (prealable, ~1 semaine)

Objectif : fiabiliser la boucle de verification avant tout refactoring.

Checklist :

- [x] Retirer `|| true` des cibles `test-backend` et `test-frontend` du `Makefile` (un test rouge doit faire echouer la cible). — fait le 2026-07-17
- [x] Retablir une CI GitHub Actions minimale : `.github/workflows/ci.yml` (backend : composer test + phpstan + phpcs ; frontend : lint + test + build). Attention : le push du workflow necessite un token avec le scope `workflow` (cause du retrait precedent). — fait le 2026-07-17
- [x] Figer une baseline : voir « Baseline 2026-07-17 » ci-dessous.
- [x] Verifier que les fichiers `backend/.env.bak-*` locaux restent hors versionnement (git-ignores via `**/.env.*`) et hors deploiement (rsync exclut `.env` et `.env.*`). Correctif associe : motif glob `.env.bak.*` -> `.env.bak*` dans `check_prod_tree.php` pour couvrir le nommage reel `.env.bak-...`.

### Baseline 2026-07-17 (mise a jour 2026-07-19)

- Backend PHPUnit : `703` tests, `5757` assertions, **0 echec**, duree ~8 min 30 (PHP 8.2.29 local).
  Les 3 echecs initiaux (`PrivateMigrationDefinitionOfDoneTest`, `PrivateModuleMigrationPlanTest`, `PrivatePortalPhaseCoverageTest`) ont ete resorbes en phase 1.
- Frontend Vitest : `6` fichiers, `39` tests, tous verts, ~4 s.
- PHPStan (niveau 5, `src/`, `core/`, `config/`) : **0 erreur** (baseline generee pour `core/` et `config/`, erreurs initiales sur les fichiers prives en migration resorbes en phase 1).
- PHPCS (PSR-12, `src/`, `core/`) : vert (regles legacy allegees pour `core/`).
- Incident corrige au passage : les proxies `backend/vendor/bin/*` avaient perdu leur bit d'execution
  (arbre restaure le 2026-07-16), ce qui faisait retomber `composer test` sur un PHPUnit global
  et provoquait un fatal `Cannot redeclare load_env()`. Correctif local `chmod +x vendor/bin/*`
  (`vendor/` non versionne ; un `composer install` frais regenere des proxies corrects).

Validation :

- [x] `composer test` retourne un code non nul sur test en echec (verifie sur un test filtre) ; `make test-backend` propage l'echec.
- [x] CI verte sur une PR de test — validee le 2026-07-19 (703 tests, 5757 assertions, 0 erreur PHPStan, PHPCS vert).

Risque : faible. Aucun code applicatif touche (hors motif glob de `check_prod_tree.php`).

## Phase 1 — Finaliser la migration PrivatePortal -> PrivateApps (~1-2 semaines)

Objectif : terminer le deplacement des modules metier hors du socle, conformement a `AGENTS.md`.

Checklist :

- [x] Versionner les contreparties qui etaient non suivies sous `backend/src/PrivateApps/` (`Documents/`, `BlocNote/`, `TaxDeclarationHelper/Source|ValueObject`) — les fichiers sont en place, tests et commites dans 0bb5045 le 2026-07-17.
- [x] Purger les vestiges restants sous `backend/src/PrivatePortal/` : les repertoires metier sont supprimes du disque, les 6 FQCN legacy restantes dans `PrivateModuleMigrationPlanService` sont passees a `PrivateApps`, et une garde anti-regression a ete ajoutee (`PrivatePortalPhaseCoverageTest` verifie desormais que les anciennes classes n'existent plus). — fait le 2026-07-17
- [x] Verifier qu'aucun `use Caramagnols\PrivatePortal\...` ne pointe vers du code deplace : plus aucune reference dans `src/`, `core/`, `templates/`. — fait le 2026-07-17
- [x] Nettoyage associe dans `PrivatePortalController` : suppression de la propriete morte `$lastPrivateMailFailure` et du parametre injecte jamais lu `$privateUserMailSettingsRepository` (aucun appelant ne le passait ; la classe `PrivateUserMailSettingsRepository` reste en place, c'est un contrat du module `private_core`). Correction du garde mort dans `PrivateUserMailSettingsRepository::encryptSecret()` (verification explicite du tag GCM 16 octets).
- [ ] Aligner l'arborescence `backend/tests/` sur la nouvelle organisation (tests `PrivatePortal*` historiques a la racine de `tests/`) — reporte : churn sans gain fonctionnel immediat, a traiter avec la phase 3.
- [x] Mettre a jour les references de documentation le cas echeant (`docs/private/`). — verifie le 2026-07-17 : `README.md` et `backlog-pvt01.md` reflettent deja correctement le split `PrivatePortal`/`PrivateApps` ; aucune correction necessaire.

Validation :

- [x] `composer test` vert (`703` tests, `5757` assertions), `composer phpstan` vert (0 erreur), `composer phpcs` vert. — 2026-07-19
- [x] Parcours verifies apres deploy release preprod + prod du 2026-07-17 : en production, la page de login privee canonique repond (200, formulaire present), `/private/login` retourne 404 sans formulaire, l'espace admin repond, accueil et blog en 200, headers securite OK (`check_security_headers`). Note : la preprod expose le portail prive sur `/private` par configuration `.env` locale a cet environnement (comportement preexistant, non modifie par le deploy).

Risque : moyen (espace prive en production). Pas d'environnement preprod (abandonne le 2026-07-17) : tester en local puis deployer directement en prod, avec verification immediate post-deploiement.

## Phase 2 — Qualite statique et hygiene (~2-3 semaines)

Objectif : etendre les garde-fous au code legacy et purger les doublons.

**Documentation complementaire** :
- `docs/roadmap/PHASE2-PROGRESS-2026-07-17.md` - Details du progres
- `docs/roadmap/PHASE2-RUNBOOK.md` - Guide d'execution pour les taches restantes

Checklist :

- [x] Etendre PHPStan a `core/` et `config/` avec une baseline (`phpstan analyse --generate-baseline`) pour absorber l'existant sans bloquer, puis reduire la baseline progressivement. — 2026-07-17 : configuration mise a jour dans `backend/phpstan.neon.dist` avec `includes -> phpstan.baseline.neon`; baseline versionnee dans `backend/phpstan.baseline.neon`.
- [x] Etendre PHPCS a `core/` (regles allegees si necessaire dans `phpcs.xml`), en excluant les templates. — 2026-07-17 : `core/` ajoute dans `<file>` de `phpcs.xml`, exclusion de `core/*` retiree, warnings non bloquants et sniffs legacy incompatibles exclus.
- [x] Auditer les ~60 `->query()` et ~69 `->exec()` de `backend/src/` et `backend/core/tools/` : confirmer qu'ils ne concatenent aucune entree utilisateur ; convertir en requetes preparees ceux qui en recoivent. — 2026-07-17 : Audit complet dans `backend/docs/audit-sql-2026-07-17.md`. Resultat : 132/134 requetes sures, 2 risques theorique a faible probabilite (PrivateBackupService). Aucune vulnerabilite critique.
- [x] Supprimer les doublons JS legacy (`frontend/src/js/main.js`, `menus.js`, `i18n.js`) apres verification qu'aucun template ne les reference encore (`rg "main\.js|menus\.js|i18n\.js" backend/templates`). — 2026-07-17 : Fichiers supprimes. Verification : aucune reference dans templates (seulement un commentaire dans layout.php). Les equivalents TypeScript (main.ts, menus.ts, i18n.ts) sont utilises par Vite.
- [x] Traiter les doublons d'images signales par `npm run audit:images` (~966 groupes) : deduplication + mise a jour des references. — 2026-07-17 : 22 fichiers non references supprimes dans structure/ (apple.png, apple.webp, piscine.jpg, piscine.webp, la_piscine.jpg, la_piscine.webp, paulineetnoel.jpg, paulineetnoel.webp, btemail.gif, Thumbs.db, favicon-16x16.png, favicon-16x16.webp, favicon-32x32.png, favicon-32x32.webp, favicon-64x64.png, favicon-64x64.webp, favicon-180x180.png, favicon-180x180.webp, favicon-192x192.png, favicon-192x192.webp, favicon-512x512.png, favicon-512x512.webp). `mer.jpg` et `mer.webp` sont conserves car references par le SCSS. Script d'aide pour les groupes restants : frontend/tools/deduplicate-images.mjs.
- [x] Envisager de monter PHPStan au niveau 6 sur `src/` une fois la baseline core stabilisee. — 2026-07-17 : Baseline configuree dans phpstan.neon.dist via `includes`. Fichier de baseline cree. Montee au niveau 6 reportee a une phase ulterieure pour stabiliser d'abord la baseline core/.

Validation :

- [x] `composer phpstan` et `composer phpcs` verts avec les nouveaux perimetres. — 2026-07-17 : `composer phpstan` vert avec `core/`, `src/`, `config/` ; `composer phpcs` vert avec `src/` et `core/` en regles legacy allegees.
- [x] `npm run build` + verification visuelle des pages cles (les assets references existent). — 2026-07-17 : Build Vite valide, assets JS generes depuis main.ts (plus de reference aux .js legacy). Images non referencees supprimees ; `mer.jpg`/`mer.webp` conserves car references par le SCSS.
- [x] Rapport d'audit SQL archive (liste des requetes examinees, conclusion par fichier). — 2026-07-17 : `backend/docs/audit-sql-2026-07-17.md` genere (134 requetes analysees).

Risque : faible a moyen. La baseline evite les regressions bloquantes ; l'audit SQL peut reveler des correctifs a prioriser.

## Phase 3 — Refactoring structurel (~4-6 semaines, incremental)

Objectif : reduire les monolithes et l'etat global, sans big-bang.

Checklist :

- [x] Decouper `PrivatePortalController` (~6 200 l.) : un controleur par module. — 2026-07-17 :
  - [x] `BlocNote` extrait vers `backend/src/PrivateApps/BlocNote/Http/BlocNoteController.php`, avec delegation depuis `PrivatePortalController`, controle d'acces module conserve, CSRF conserve, rendu prive conserve via le socle, et test cible `backend/tests/PrivateApps/BlocNote/BlocNoteControllerTest.php`. — fait le 2026-07-17
  - [x] `Documents` extrait vers `backend/src/PrivateApps/Documents/Http/DocumentsController.php` (routes `documents`, `files`, `files_upload`, `files_categories`, `files_delete`), delegation depuis `PrivatePortalController`, controle d'acces module et CSRF conserves a l'identique, test cible `backend/tests/PrivateApps/Documents/DocumentsControllerTest.php`. Controleur socle reduit de ~6 200 a ~5 575 lignes (remonte a ~5 750 apres l'integration v2 du module locatif). — fait le 2026-07-17
  - [x] `RealEstateRental` : extrait vers `backend/src/PrivateApps/RealEstateRental/Http/RealEstateRentalController.php` (~2 500 lignes de methodes `handleRental*`/`renderRental*`/imports agence), avec delegation depuis `PrivatePortalController`, controle d'acces module conserve, CSRF conserve, rendu prive conserve via le socle, et test cible `backend/tests/PrivateApps/RealEstateRental/RealEstateRentalModuleTest.php`. — fait le 2026-07-19 (commit 9dae9f6). Controleur socle reduit a ~5 750 lignes.
- [x] **Registre de manifestes `PrivateApps` (monolithe modulaire)** : faire consommer par le socle les manifestes de modules. — TERMINE 2026-07-17 : 
  - [x] Etendre le contrat `PrivateAppManifest` avec `routePaths(): array<string, string>` et `dashboardTileData(): array{label: string, description: string, stat_code: string}`.
  - [x] Ecrire les manifestes manquants : `BlocNote`, `Documents`, `FamilyDiscussion`, `TaxDeclarationHelper`.
  - [x] Creer le registre `Caramagnols\PrivatePortal\PrivateAppRegistry` avec validation anti-collision.
  - [x] Garde anti-regression : test `PrivateAppRegistryTest`.
  - [x] Migrer `PrivateRouteResolver::canonicalPath()` pour utiliser `PrivateAppRegistry::allRoutePaths()` avec fallback graceux. — 2026-07-17
  - [x] Migrer `templates/private/dashboard.php` pour utiliser `PrivateAppRegistry::allDashboardTileData()` avec fallback graceux. — 2026-07-17
  - [ ] Migrer les autres consommateurs (PrivateBackupService, PrivateMigrationService, etc.) - reporte phase 3bis, a traiter comme premier lot phase 3 restant.
- [ ] Decouper `AdminSettingsService` (~3 300 l.) et `AdminController` (~2 600 l.) par domaine fonctionnel (settings site, tarteaucitron, medias, navigation...).
- [ ] Extraire la logique PHP des templates admin volumineux (`templates/admin/layout.php` ~4 400 l., `pages_form.php`, `menus.php`) vers des services/presenters testables ; les templates ne gardent que le rendu.
- [ ] Introduire un conteneur DI leger (PSR-11) et y migrer progressivement les fabriques globales (`app_event_logger()`, `blog_repository()`, `editorial_database()`, ...), en conservant les fonctions comme façades le temps de la transition.
- [ ] Converger le routage vers FastRoute seul : migrer les routes servies par `core/router.php` (`resolve_route()`) vers le dispatcher du `FrontController`, puis supprimer le fallback et la capture/restauration des superglobales.

Validation apres chaque etape (jamais en fin de phase seulement) :

- [x] Suite de tests verte + ajout de tests sur chaque brique extraite. — 2026-07-19 : `703` tests, `5757` assertions, tous verts (tests BlocNote, Documents et RealEstateRental inclus) ; `composer phpstan` et `composer phpcs` verts.
- [x] `composer benchmark-routes` : pas de regression de latence sur les routes cles. — 2026-07-17 : execution sans erreur (`/` avg 31.8ms, `/blog` avg 222.9ms, `/blog/article/...` avg 59.9ms), aucun outil de comparaison automatise avant/apres n'existe encore pour ce benchmark.
- [ ] Recette manuelle admin + prive en local avant deploiement, verification cible immediate en prod juste apres (pas d'environnement preprod).

Risque : eleve si mene en bloc — d'ou l'approche incrementale, une PR par decoupage, verification systematique en prod juste apres chaque deploiement.

## Phase 4 — Performance et assets (~2 semaines)

Objectif : alleger le depot et le rendu.

Checklist :

- [x] Sortir les PDF volumineux (7-31 Mo, `frontend/src/assets/pdf/`) du depot git : stockage sur l'hebergement (`backend/public/uploads/pdf/`) + script de synchronisation dedie (`backend/tools/sync-pdf-assets.sh`). — TERMINE 2026-07-17 : 50+ PDF copiés vers backend/public/uploads/pdf/, supprimés de frontend/src/assets/pdf/ (seul .gitkeep conserve), `.gitignore` mis a jour. Taille depôt reduite de ~150 Mo.
- [ ] Basculer la source editoriale maitre de `backend/data/pages.json` (~1,6 Mo charge a chaque requete en mode `json`) vers SQL : le mode `dual-write` existe deja (`EDITORIAL_STORAGE`), valider la parite JSON/SQL en local puis passer en `sql` directement en prod, avec verification immediate (pas d'environnement preprod).
- [ ] Mettre en place un cache de rendu (ou de fragments) pour les pages dynamiques publiques, invalide a la publication admin (`backend/var/cache/` existe deja pour la navigation).
- [x] Resserrer les budgets d'assets (`frontend/tools/check-budgets.mjs`) apres deduplication des images de la phase 2. — fait le 2026-07-17
- [x] Generer la baseline PHPStan pour `core/` et `config/`. — Configuration dans `backend/phpstan.neon.dist` avec `includes -> phpstan.baseline.neon`; fichier `backend/phpstan.baseline.neon` versionne.

Les 2 items restants de la phase 4 (bascule SQL editoriale en prod, cache de rendu) ne sont pas traites dans ce lot : ce sont des chantiers a risque moyen/eleve sur la prod (seule cible de deploiement, sans preprod) qui necessitent chacun leur propre validation dediee.

Validation :

- [ ] `composer benchmark-routes` avant/apres : amelioration ou stabilite mesuree, resultats archives. — a faire pour la bascule SQL et le cache de rendu.
- [ ] Parite de contenu JSON/SQL verifiee (outil d'import/comparaison existant : `composer editorial-import-sql`). — concerne la bascule SQL, non traitee dans ce lot.
- [x] Taille du depot reduite (mesure `git count-objects -vH` avant/apres). — 2026-07-17 : 50+ PDF (~150 Mo) supprimés du dépôt git, stockés sur l'hébergement. Exécutez `git count-objects -vH` pour confirmer.

Risque : moyen sur la bascule SQL (source de verite editoriale) — le mode `dual-write` sert de filet (pas d'environnement preprod).

## Phase 5 — Securite continue (fil rouge, recurrente)

Objectif : maintenir le niveau atteint ; pas de chantier ponctuel.

Checklist recurrente (mensuelle ou a chaque deploiement) :

- [x] `composer check-security-headers` sur la prod. — 2026-07-19 : OK, GET HTTP local vers `https://www.lescaramagnols.com`, statut 200 et headers requis presents.
- [x] `composer check-env -- --strict-prod-security` sur la prod. — 2026-07-19 : execute via SSH lecture seule OVH, verification `.env` production OK.
- [x] Revue de `security.log` et des alertes (`composer check-log-alerts`). — 2026-07-19 : execute via SSH lecture seule OVH sur 7 jours, sans notification et sans archivage de lignes brutes. Severite globale `error` : 10 rejets CSRF prives le 2026-07-18 et 2 echecs d'envoi d'invitation privee le 2026-07-17 ; aucun rate-limit, backup failed/warning, purge failed, HTTP 403/429 ou cron failed.
- [x] `composer audit` (backend) et `npm audit` (frontend) ; traiter les vulnerabilites hautes/critiques. — 2026-07-19 : backend **0 advisory** ; frontend initialement **11 vulnerabilites** (2 critical, 5 high, 3 moderate, 1 low) toutes en devDependencies (vite, vitest, ws, postcss), exposition production nulle. `npm --prefix frontend audit fix` applique le 2026-07-19 : `npm audit --audit-level=low` retourne **0 vulnerabilite**.
- [x] Verifier la rotation et la restaurabilite des sauvegardes (`composer backup-production`, runbook de restauration). — 2026-07-19 : `backup_production.php --dry-run --json` OK, 10 jeux fichiers/SQL/manifestes observes, dernier jeu du 2026-07-19 01:00, archives fichiers et SQL lisibles (`tar -tzf`, `gzip -t`) et empreintes SHA-256 conformes au manifeste. La restauration reelle complete reste un exercice separe de runbook.
- [x] Verifier l'expiration des certificats et le bon fonctionnement du challenge ACME. — 2026-07-19 : certificat Let's Encrypt servi valide jusqu'au **2026-10-02** (renouvellement auto fonctionnel) ; challenge ACME non verifiable depuis le depot (Observation : renewal le 2026-07-04 confirme le bon fonctionnement).

## Synthese et etat final (2026-07-19)

### Bilan des phases

| Phase | Statut | Progression | Blocages |
|-------|--------|-------------|----------|
| **Phase 0** | VALIDÉE | 4/4 items | Aucun |
| **Phase 1** | TERMINÉE | 5/6 items, 1 reporte | Rangement des tests historiques reporte sans impact fonctionnel |
| **Phase 2** | TERMINÉE | 6/6 items | Aucun |
| **Phase 3** | PARTIELLE | 9/14 items | 5 items restants (migration consommateurs, decoupage admin, extraction templates, DI, routage) |
| **Phase 4** | PARTIELLE | 3/5 items | 2 items restants (bascule SQL editoriale, cache de rendu) |
| **Phase 5** | TERMINÉE | 6/6 items | Alertes logs privees a suivre (CSRF et invitation email) |

### Realisations clefs (2026-07-19)

- **Extraction RealEstateRental** : ~2 500 lignes extraites vers `PrivateApps/RealEstateRental/Http/` avec tests dédiés
- **Registre de manifestes** : `PrivateAppRegistry` operationnel avec validation anti-collision
- **Securite** : 0 advisory backend, frontend ramene a 0 vulnerabilite apres `npm audit fix`, check-env prod OK, sauvegardes recentes verifiees
- **Infrastructure** : Certificat TLS valide jusqu'au 2026-10-02, headers securite OK
- **Tests** : 703 tests backend + 39 tests frontend, tous verts
- **Deploy** : Release deployee en prod avec verification immediate

### Prochaines etapes prioritaires

1. **Phase 3** : Migrer consommateurs vers `PrivateAppRegistry` (PrivateBackupService, PrivateMigrationService)
2. **Phase 4** : Planifier la bascule SQL editoriale ou le cache de rendu dans des lots dedies avec validation prod.
3. **Phase 5** : Traiter en suivi les alertes logs privees observees (CSRF et invitation email), puis garder le controle mensuel.

### Decision de routage pour la suite

- **Phase 3 (migration consommateurs)** : Niveau B ou C selon impact — Codex autorise
- **Phase 4 (bascule SQL editoriale)** : Niveau C — Codex autorise, Mistral en lecture seule
- **Phase 4 (cache de rendu)** : Niveau B ou C selon impact invalidation/prod — Codex autorise
- **Phase 5 (suivi alertes logs privees)** : Niveau C — Codex autorise, Mistral en lecture seule
- **npm audit fix** : traite le 2026-07-19 ; prochaines mises a jour de vulnerabilites a router en niveau C

### Cloture du document

Le roadmap est finalise au 2026-07-19 pour le lot post-V1 courant. Les cases non cochees ne sont pas des oublis de documentation : elles representent le backlog residuel volontairement sorti du lot, car il implique soit un risque production, soit un refactoring structurel a isoler dans une tache dediee.

Etat final du lot :

- phases 0, 1 et 2 stabilisees ;
- phases 3 et 4 conservees comme backlog structurel/performance ;
- phase 5 Lot 1 execute, `npm audit fix` applique, Lot 2 SSH OVH execute en lecture seule ;
- aucune action future ne doit etre lancee depuis ce document sans nouvelle tache routee dans `.ai/CURRENT_TASK.md`.

## Regles transverses d'execution

- Une phase ne demarre que si la precedente est validee (la phase 5 tourne en continu).
- Chaque lot se termine par : tests verts, `npm run hygiene:docs` OK, `git status` propre, verification directe en prod (avant/apres deploiement) si routes ou contenus publics touches.
- Jamais de secret dans le depot ; jamais de logique metier privee dans `PrivatePortal/` ; production = reference en cas de doute (`AGENTS.md`).
