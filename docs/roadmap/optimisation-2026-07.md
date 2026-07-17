# Plan D'Optimisation Post-V1

Date : 2026-07-17
Statut : phase 0 en cours d'execution (2026-07-17), phases 1+ proposees

Ce document analyse la dette technique actuelle et propose un plan d'optimisation en phases, avec checklist d'implementation. Il complete les plans existants sans les dupliquer :

- `docs/roadmap/README.md` (modernisation, largement realisee)
- `docs/roadmap/transition-v1-s1-s8.md` (transition V1, executee)
- `docs/archive/audit-complet-v1.md` (audit historique)

Perimetre : dette **post-V1** observee sur la branche `restore-prod-master-20260716` au 2026-07-17. La production `https://www.lescaramagnols.com/` reste la reference ; tout ecart constate lors de l'execution doit etre verifie contre la prod avant correction (voir `AGENTS.md`).

## Etat des lieux

### Points forts (a preserver)

- Securite solide : webroot limite a `backend/public/`, `.htaccess` durci (CSP, HSTS), PDO prepare sans emulation, secrets via `.env`, 2FA admin et prive, chiffrement AES-256-GCM des pieces jointes de discussion.
- Couverture de tests reelle : ~126 fichiers PHPUnit (~593 methodes) + Vitest cote frontend.
- Outillage riche : ~30 scripts CLI de diagnostic/ops (`backend/core/tools/`), scripts de deploiement avec gardes (`backend/tools/`), hygiene docs/assets outillee (`frontend/tools/`).
- Architecture cible claire et documentee : `PrivatePortal` (socle) / `PrivateApps` (metier).

### Dettes identifiees

1. **Migration `PrivatePortal/` -> `PrivateApps/` non finalisee** : fichiers supprimes cote socle (`Documents/`, `BlocNote/`, `TaxDeclarationHelper/Source|ValueObject`, `RealEstateRental/TaxBridge`), contreparties non versionnees sous `PrivateApps/`, imports mixtes dans `backend/src/Http/FrontController.php`.
2. **Monolithes** :
   - `backend/src/PrivatePortal/Http/PrivatePortalController.php` : ~6 200 lignes
   - `backend/templates/admin/layout.php` : ~4 400 lignes
   - `backend/src/Admin/AdminSettingsService.php` : ~3 300 lignes
   - `backend/src/PrivateApps/RealEstateRental/Repository/RentalLifecycleRepository.php` : ~3 000 lignes
   - `backend/src/Admin/AdminController.php` : ~2 600 lignes
3. **Qualite statique inegale** : PHPStan (niveau 5) et PHPCS n'analysent que `src/` — `core/`, `config/`, `public/` sont exclus ; `make test-backend` masque les echecs avec `|| true` ; pas de workflow CI actif dans `.github/`.
4. **Double paradigme** : `core/` proceduraux a etat global (superglobales capturees/restaurees par le `FrontController`) vs `src/` OO type ; double routage FastRoute + `core/router.php` ; pas de conteneur DI (fabriques globales `app_event_logger()`, `blog_repository()`, ...).
5. **A auditer** : ~60 `->query()` et ~69 `->exec()` (a priori DDL/migrations, verifier l'absence d'interpolation d'entrees utilisateur) ; doublons JS legacy (`frontend/src/js/main.js`, `menus.js`, `i18n.js` vs equivalents `.ts`).
6. **Poids du depot** : PDF touristiques 7 a 31 Mo dans `frontend/src/assets/pdf/`, `backend/data/pages.json` ~1,6 Mo, ~966 groupes d'images en doublon signales par `npm run audit:images`.

## Phase 0 — Garde-fous (prealable, ~1 semaine)

Objectif : fiabiliser la boucle de verification avant tout refactoring.

Checklist :

- [x] Retirer `|| true` des cibles `test-backend` et `test-frontend` du `Makefile` (un test rouge doit faire echouer la cible). — fait le 2026-07-17
- [x] Retablir une CI GitHub Actions minimale : `.github/workflows/ci.yml` (backend : composer test + phpstan + phpcs ; frontend : lint + test + build). Attention : le push du workflow necessite un token avec le scope `workflow` (cause du retrait precedent). — fait le 2026-07-17
- [x] Figer une baseline : voir « Baseline 2026-07-17 » ci-dessous.
- [x] Verifier que les fichiers `backend/.env.bak-*` locaux restent hors versionnement (git-ignores via `**/.env.*`) et hors deploiement (rsync exclut `.env` et `.env.*`). Correctif associe : motif glob `.env.bak.*` -> `.env.bak*` dans `check_prod_tree.php` pour couvrir le nommage reel `.env.bak-...`.

### Baseline 2026-07-17

- Backend PHPUnit : `624` tests, `4920` assertions, **3 echecs**, duree ~8 min 30 (PHP 8.2.29 local).
  Les 3 echecs sont des tests de suivi de la migration `PrivatePortal -> PrivateApps` en cours
  (`PrivateMigrationDefinitionOfDoneTest`, `PrivateModuleMigrationPlanTest`, `PrivatePortalPhaseCoverageTest`)
  — a resorber en phase 1, pas en phase 0.
- Frontend Vitest : `6` fichiers, `39` tests, tous verts, ~4 s.
- PHPStan (niveau 5, `src/`) : **3 erreurs**, toutes dans les fichiers prives en cours de migration — a resorber en phase 1.
- PHPCS (PSR-12, `src/`) : vert.
- Incident corrige au passage : les proxies `backend/vendor/bin/*` avaient perdu leur bit d'execution
  (arbre restaure le 2026-07-16), ce qui faisait retomber `composer test` sur un PHPUnit global
  et provoquait un fatal `Cannot redeclare load_env()`. Correctif local `chmod +x vendor/bin/*`
  (`vendor/` non versionne ; un `composer install` frais regenere des proxies corrects).

Validation :

- [x] `composer test` retourne un code non nul sur test en echec (verifie sur un test filtre) ; `make test-backend` propage l'echec.
- [ ] CI verte sur une PR de test — sera rouge tant que les 3 echecs et 3 erreurs PHPStan de la migration (phase 1) ne sont pas resorbes ; a valider apres la phase 1 ou sur une branche saine.

Risque : faible. Aucun code applicatif touche (hors motif glob de `check_prod_tree.php`).

## Phase 1 — Finaliser la migration PrivatePortal -> PrivateApps (~1-2 semaines)

Objectif : terminer le deplacement des modules metier hors du socle, conformement a `AGENTS.md`.

Checklist :

- [ ] Versionner les contreparties actuellement non suivies sous `backend/src/PrivateApps/` (`Documents/`, `BlocNote/`, `TaxDeclarationHelper/Source|ValueObject`) — les fichiers sont en place et testes, reste le commit (a la demande).
- [x] Purger les vestiges restants sous `backend/src/PrivatePortal/` : les repertoires metier sont supprimes du disque, les 6 FQCN legacy restantes dans `PrivateModuleMigrationPlanService` sont passees a `PrivateApps`, et une garde anti-regression a ete ajoutee (`PrivatePortalPhaseCoverageTest` verifie desormais que les anciennes classes n'existent plus). — fait le 2026-07-17
- [x] Verifier qu'aucun `use Caramagnols\PrivatePortal\...` ne pointe vers du code deplace : plus aucune reference dans `src/`, `core/`, `templates/`. — fait le 2026-07-17
- [x] Nettoyage associe dans `PrivatePortalController` : suppression de la propriete morte `$lastPrivateMailFailure` et du parametre injecte jamais lu `$privateUserMailSettingsRepository` (aucun appelant ne le passait ; la classe `PrivateUserMailSettingsRepository` reste en place, c'est un contrat du module `private_core`). Correction du garde mort dans `PrivateUserMailSettingsRepository::encryptSecret()` (verification explicite du tag GCM 16 octets).
- [ ] Aligner l'arborescence `backend/tests/` sur la nouvelle organisation (tests `PrivatePortal*` historiques a la racine de `tests/`) — reporte : churn sans gain fonctionnel immediat, a traiter avec la phase 3.
- [ ] Mettre a jour les references de documentation le cas echeant (`docs/private/`).

Validation :

- [x] `composer test` vert (`624` tests, `5056` assertions), `composer phpstan` vert (0 erreur), `composer phpcs` vert. — 2026-07-17
- [x] Parcours verifies apres deploy release preprod + prod du 2026-07-17 : en production, la page de login privee canonique repond (200, formulaire present), `/private/login` retourne 404 sans formulaire, l'espace admin repond, accueil et blog en 200, headers securite OK (`check_security_headers`). Note : la preprod expose le portail prive sur `/private` par configuration `.env` locale a cet environnement (comportement preexistant, non modifie par le deploy).

Risque : moyen (espace prive en production). Deployer d'abord en preprod, comparer avec la prod avant tout deploiement.

## Phase 2 — Qualite statique et hygiene (~2-3 semaines)

Objectif : etendre les garde-fous au code legacy et purger les doublons.

Checklist :

- [ ] Etendre PHPStan a `core/` et `config/` avec une baseline (`phpstan analyse --generate-baseline`) pour absorber l'existant sans bloquer, puis reduire la baseline progressivement.
- [ ] Etendre PHPCS a `core/` (regles allegees si necessaire dans `phpcs.xml`), en excluant les templates.
- [ ] Auditer les ~60 `->query()` et ~69 `->exec()` de `backend/src/` et `backend/core/tools/` : confirmer qu'ils ne concatenent aucune entree utilisateur ; convertir en requetes preparees ceux qui en recoivent.
- [ ] Supprimer les doublons JS legacy (`frontend/src/js/main.js`, `menus.js`, `i18n.js`) apres verification qu'aucun template ne les reference encore (`rg "main\.js|menus\.js|i18n\.js" backend/templates`).
- [ ] Traiter les doublons d'images signales par `npm run audit:images` (~966 groupes) : deduplication + mise a jour des references.
- [ ] Envisager de monter PHPStan au niveau 6 sur `src/` une fois la baseline core stabilisee.

Validation :

- [ ] `composer phpstan` et `composer phpcs` verts avec les nouveaux perimetres.
- [ ] `npm run build` + verification visuelle des pages cles (les assets references existent).
- [ ] Rapport d'audit SQL archive (liste des requetes examinees, conclusion par fichier).

Risque : faible a moyen. La baseline evite les regressions bloquantes ; l'audit SQL peut reveler des correctifs a prioriser.

## Phase 3 — Refactoring structurel (~4-6 semaines, incremental)

Objectif : reduire les monolithes et l'etat global, sans big-bang.

Checklist :

- [ ] Decouper `PrivatePortalController` (~6 200 l.) : un controleur par module (`PrivateApps/<Module>/Http/<Module>Controller.php`), le socle `PrivatePortal/Http/` ne gardant que routage, auth et layout. Proceder module par module (commencer par le plus petit, ex. `BlocNote`).
- [ ] Decouper `AdminSettingsService` (~3 300 l.) et `AdminController` (~2 600 l.) par domaine fonctionnel (settings site, tarteaucitron, medias, navigation...).
- [ ] Extraire la logique PHP des templates admin volumineux (`templates/admin/layout.php` ~4 400 l., `pages_form.php`, `menus.php`) vers des services/presenters testables ; les templates ne gardent que le rendu.
- [ ] Introduire un conteneur DI leger (PSR-11) et y migrer progressivement les fabriques globales (`app_event_logger()`, `blog_repository()`, `editorial_database()`, ...), en conservant les fonctions comme façades le temps de la transition.
- [ ] Converger le routage vers FastRoute seul : migrer les routes servies par `core/router.php` (`resolve_route()`) vers le dispatcher du `FrontController`, puis supprimer le fallback et la capture/restauration des superglobales.

Validation apres chaque etape (jamais en fin de phase seulement) :

- [ ] Suite de tests verte + ajout de tests sur chaque brique extraite.
- [ ] `composer benchmark-routes` : pas de regression de latence sur les routes cles.
- [ ] Recette manuelle admin + prive en preprod avant deploiement.

Risque : eleve si mene en bloc — d'ou l'approche incrementale, une PR par decoupage, comparaison preprod/prod systematique.

## Phase 4 — Performance et assets (~2 semaines)

Objectif : alleger le depot et le rendu.

Checklist :

- [ ] Sortir les PDF volumineux (7-31 Mo, `frontend/src/assets/pdf/`) du depot git : stockage sur l'hebergement (type `backend/public/uploads/`) + script de synchronisation dedie (modele : `backend/tools/sync-editorial-uploads.sh`) ; conserver uniquement les references.
- [ ] Basculer la source editoriale maitre de `backend/data/pages.json` (~1,6 Mo charge a chaque requete en mode `json`) vers SQL : le mode `dual-write` existe deja (`EDITORIAL_STORAGE`), valider la parite JSON/SQL puis passer en `sql` en preprod, enfin en prod.
- [ ] Mettre en place un cache de rendu (ou de fragments) pour les pages dynamiques publiques, invalide a la publication admin (`backend/var/cache/` existe deja pour la navigation).
- [ ] Resserrer les budgets d'assets (`frontend/tools/check-budgets.mjs`) apres deduplication des images de la phase 2.

Validation :

- [ ] `composer benchmark-routes` avant/apres : amelioration ou stabilite mesuree, resultats archives.
- [ ] Parite de contenu JSON/SQL verifiee (outil d'import/comparaison existant : `composer editorial-import-sql`).
- [ ] Taille du depot reduite (mesure `git count-objects -vH` avant/apres).

Risque : moyen sur la bascule SQL (source de verite editoriale) — le mode `dual-write` et la preprod servent de filet.

## Phase 5 — Securite continue (fil rouge, recurrente)

Objectif : maintenir le niveau atteint ; pas de chantier ponctuel.

Checklist recurrente (mensuelle ou a chaque deploiement) :

- [ ] `composer check-security-headers` sur la prod.
- [ ] `composer check-env -- --strict-prod-security` sur la prod.
- [ ] Revue de `security.log` et des alertes (`composer check-log-alerts`).
- [ ] `composer audit` (backend) et `npm audit` (frontend) ; traiter les vulnerabilites hautes/critiques.
- [ ] Verifier la rotation et la restaurabilite des sauvegardes (`composer backup-production`, runbook de restauration).
- [ ] Verifier l'expiration des certificats et le bon fonctionnement du challenge ACME.

## Regles transverses d'execution

- Une phase ne demarre que si la precedente est validee (la phase 5 tourne en continu).
- Chaque lot se termine par : tests verts, `npm run hygiene:docs` OK, `git status` propre, comparaison preprod/prod si routes ou contenus publics touches.
- Jamais de secret dans le depot ; jamais de logique metier privee dans `PrivatePortal/` ; production = reference en cas de doute (`AGENTS.md`).
