# Plan Transition V1 - S1 A S8

Date de mise a jour : 2026-03-21
Statut : actif

Ce document est le plan d'execution operationnel pour :

- stabiliser la V1 en production sans bloquer le Front-Office
- reduire la dette legacy de facon progressive
- preparer une migration cible vers un socle Symfony pour un futur module client (connexion/compte)

References :

- `README_V1_PREPARATION_DEPLOIEMENT.md`
- `README_MODERNISATION_V1.md`
- `README_SECURITE_ADMIN_V1.md`
- `README_PRIVATE_FAMILLE_V1.md`
- `README_PRIVATE_FAMILLE_BACKLOG_V1.md`
- `README_DOCUMENTATION_INDEX.md`

## Execution en cours

Date : 2026-03-21

- [x] W1-01 execute : quality gates locales lancees et preuves archivees dans `docs/private/recette-preprod-v1-2026-03-20/`.
- [x] W1-02 execute : scripts `backend/tools/deploy-fast.sh` et `backend/tools/deploy-release.sh` ajoutes + documentation usage/rollback dans `README.md`.
- [x] W1-03 execute : hardening HTTP/headers valide sur `https://www.lescaramagnols.com` + controle `--strict-prod-security` vert (preuves `33-check-security-headers-www.txt` et `34-check-env-production-strict.txt`).
- [x] W1-04 execute : stabilite admin navigation/footer validee (tests 45/45 verts + purge cache navigation, preuves `62-w1-04-admin-tests.txt` et `63-w1-04-cache-clear-navigation.txt`).
- [x] W1-05 execute : runbook backup/restore rejoue en preprod (`storage=json`) avec preuve de backup (`103-w1-05-backup.txt`) et de restauration (`104-w1-05-restore.txt`).
- [x] W1-06 execute : recette manuelle FO/Admin desktop/mobile archivee (`front/*`, `admin/*`) + non-regression automatisee FO/Admin (`87-w1-06-fo-admin-tests.txt`).
- [x] W1-07 execute : controles de cloture relances (autoload/controllers/HTTP/cache/tests/hygiene docs) + decision formelle archivee (etat final `GO`, preuves `105` a `113`).
- [x] Maintenance documentaire + securite session admin : warning de prolongation avant timeout (120s) + route keepalive `/<base_path>/<ADMIN_LOGIN_PATH>/session/ping` documentes et testes (non bloquant FO).
- [x] Correctif admin tarteaucitron : acceptation stable de l'objet JSON vide `{}` dans "Variables JS services" (blocage historique supprime, listes JSON explicites `[]` toujours refusees).
- [x] Correctif admin tarteaucitron (booleens legacy) : normalisation `false/off/0` pour eviter le recochage automatique des options apres sauvegarde.
- [x] Durcissement persistance tarteaucitron : suppression des cast bool ambigus restants dans `AdminSettingsService` (normalisation homogene runtime + save), pour stabiliser l'etat coche/decoche apres "Enregistrer".
- [x] Correctif navigation desktop FO : sous-menus imbriques re-positionnes sous le parent + ouverture tactile au tap/clic conservee en complement du survol souris.
- [x] Correctif admin pages (editeur HTML) : les `iframe` YouTube en region `rich_text` sont conserves apres sauvegarde/re-ouverture, avec normalisation automatique vers `youtube-nocookie.com` et rejet des sources non autorisees.
- [x] Evolution editoriale V1 : upload image admin pages/articles, rendu SEO image (`og:image`/`twitter:image`) et preservation `backend/public/uploads/editorial/**` en deploiement (non bloquant FO).
- [x] Correctif admin menus : confirmation explicite avant suppression d'un item (`Oui/Non`), annulation par defaut si absence de validation (non bloquant FO).
- [x] Addendum go-live 2026-03-21 : pre-requis prod leves (`check-env --strict-prod-security` OK, headers securite OK, recette admin authentifiee OK, Instagram desactive) -> decision deploiement immediat revue a `GO` (preuves `114` a `117`).
- [x] Observabilite ops : `check_log_alerts.php` branche sur canal webhook/email + pack `systemd timer` (`runner`, templates units, script d'installation) pour preprod/prod.
- [x] V2 observabilite admin : nouveau panneau `Parametres > Observabilite ops` pour piloter `notify_on` (`alerts|always`) sans exposer les secrets infra.
- [x] Runbook post go-live execute : cycle J+1 + J+7 archive (`122` a `128`) avec logs verts, benchmark routes, controle HTTP/HTTPS cible et synthese anomalies.

## Principes d execution

1. Aucune regression visible FO : toute evolution admin/back doit rester non bloquante pour le public.
2. Priorite aux changements incrementaux : pas de rewrite global.
3. Toute nouvelle logique metier va dans `backend/src/*` (pas dans `backend/core/*`).
4. Qualite outillee obligatoire avant livraison : tests, lint, static analysis, audits.
5. Chaque livraison se termine par une purge de cache runtime (`pages`, `navigation`, `translations`).

## Vision 8 semaines

## S1 - Stabilisation release + deploiement leger

- gates qualite vertes et scriptables localement
- deploiement rapide des petits correctifs (fichiers modifies uniquement)
- hardening minimal valide en cible
- runbook de backup/restore rejouable

## S2 - Dette technique prioritaire admin/navigation

- poursuite extraction `core/*` vers `src/*`
- baisse complexite `AdminController`, `AdminSettingsService`, `AdminNavigationService`
- renforcement tests de non regression sur menus/pages/discussions

## S3 - Contrats data editoriaux

- verification source de verite (`sql` / `dual-write`)
- suppression article => discussions sans orphelins
- import/export editorial reproductible en preprod

## S4 - Performance et cache en charge normale

- mesure routes critiques (`/`, pages article, `/blog`)
- invalidation de cache admin fiable
- budget assets stabilise (pas de derive JS/CSS/images)

## S5 - Observabilite et exploitation

- rotation/retention logs en prod
- alertes de base (403/429/login failed/rate limited)
- runbook incidents J+1/J+7

## S6 - Bootstrap cible Symfony (sans big bang)

- squelette technique du module futur (socle uniquement)
- integration auth standard prete (OIDC cible, sans impact FO actuel)
- delimitation claire entre legacy runtime et nouveau noyau

## S7 - Bascule progressive par perimetre

- routing cible active uniquement sur zone pilote
- feature flag + rollback simple
- recette fonctionnelle complete FO/admin

## S8 - Go live durci + cloture V1

- validation finale des gates
- securite prod verifiee
- documentation exploitation/deploiement complete

## Backlog Semaine 1 (tickets executables)

Capacite cible : 5 jours, 2 a 4 tickets/jour selon complexite.

### Ticket W1-01 - Baseline qualite locale

Priorite : P0  
Effort : 0.5 jour

Objectif :

- garantir un point de depart propre avant tout changement S1

Actions :

- executer toutes les commandes qualite en local
- archiver les sorties dans `docs/private/recette-preprod-v1-YYYY-MM-DD/`

Commandes de validation :

```bash
cd backend
composer test
composer phpstan
composer phpcs
composer audit
cd ../frontend
npm run lint
npm run test:run
npm run build
npm audit --json
```

Livrables :

- preuves de commandes (fichiers `.txt` ou captures)
- statut de decision explicite

### Ticket W1-02 - Deploiement leger standardise

Priorite : P0  
Effort : 0.5 jour

Objectif :

- eviter le deploiement complet pour chaque micro-correctif

Actions :

- ajouter `backend/tools/deploy-fast.sh` (sync fichiers modifies)
- ajouter `backend/tools/deploy-release.sh` (sync complet)
- documenter usage et rollback

Commandes de validation :

```bash
bash backend/tools/deploy-fast.sh --dry-run
bash backend/tools/deploy-release.sh --dry-run
```

Livrables :

- scripts versionnes
- section README usage court + exemples

### Ticket W1-03 - Hardening HTTP/headers en cible

Priorite : P0  
Effort : 0.5 jour

Objectif :

- valider le socle de securite HTTP sur domaine cible

Actions :

- verifier HTTPS force, HSTS, CSP, XFO, XCTO, Referrer-Policy, Permissions-Policy
- corriger config infra/app si un header manque

Commandes de validation :

```bash
cd backend
php core/tools/check_security_headers.php --url=https://www.lescaramagnols.com
php core/tools/check_env.php --env=production --strict-prod-security
```

Livrables :

- sortie de controle archivee
- liste des ecarts restants (si applicable)

Checklist detaillee W1-03 (execution du 2026-03-20) :

- [x] HTTPS force verifie sur domaine cible (`status final: 200` sur URL HTTPS).
- [x] Headers requis controles et presents (`CSP`, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `COOP`, `CORP`, `HSTS`).
- [x] Controle environnement production strict valide (`php core/tools/check_env.php --env=production --strict-prod-security`).
- [x] Preuve archivee : `docs/private/recette-preprod-v1-2026-03-20/33-check-security-headers-www.txt`.
- [x] Preuve archivee : `docs/private/recette-preprod-v1-2026-03-20/34-check-env-production-strict.txt`.
- [x] Ecart restant sur ce ticket : aucun.

### Ticket W1-04 - Stabilite admin navigation/footer

Priorite : P0  
Effort : 1 jour

Objectif :

- fiabiliser la sauvegarde des menus (y compris pied de page)

Actions :

- couvrir les cas modale/systemes (`footer_notice`, banniere, remonter)
- ajouter tests de non regression serializer/form payload
- verifier invalidation cache navigation apres save

Commandes de validation :

```bash
cd backend
./vendor/bin/phpunit tests/AdminSerializedFormNormalizerTest.php tests/AdminNavigationServiceTest.php tests/AdminControllerTest.php
php -r "require 'core/bootstrap.php'; app_runtime_cache_clear(['navigation']); echo 'cache_cleared'.PHP_EOL;"
```

Livrables :

- tests verts
- bugfix deploye et recette admin validee

Checklist detaillee W1-04 (execution du 2026-03-20) :

- [x] Cas modale/systemes verifies : `footer_notice`, `banner`, `remonter`.
- [x] Non-regression serializer/form payload etendue (`AdminSerializedFormNormalizerTest` couvre aussi les champs systeme manquants dans le JSON serialize).
- [x] Invalidation cache navigation verifiee apres save (`AdminNavigationServiceTest`).
- [x] Validation ticket : `./vendor/bin/phpunit tests/AdminSerializedFormNormalizerTest.php tests/AdminNavigationServiceTest.php tests/AdminControllerTest.php` -> `45` tests verts.
- [x] Purge cache navigation executee : `php -r "require 'core/bootstrap.php'; app_runtime_cache_clear(['navigation']); ..."` -> OK.
- [x] Preuves archivees : `docs/private/recette-preprod-v1-2026-03-20/62-w1-04-admin-tests.txt`, `63-w1-04-cache-clear-navigation.txt`.
- [x] Ecart restant sur ce ticket : aucun.

### Ticket W1-05 - Backup/restore avant operations destructives

Priorite : P0  
Effort : 0.5 jour

Objectif :

- rendre le retour arriere DB/JSON operationnel

Actions :

- rejouer backup + restore en environnement de preprod
- verifier coherence pages/navigation/blog/discussions apres restore

Commandes de validation :

```bash
cd backend
php core/tools/editorial_backup_restore.php backup --output=var/backups
php core/tools/editorial_backup_restore.php restore var/backups/<dernier_fichier>.json --force
```

Livrables :

- archive backup
- preuve de restauration valide

Checklist detaillee W1-05 (execution du 2026-03-21) :

- [x] Backup editorial complet genere : `php core/tools/editorial_backup_restore.php backup --storage=json --output=var/backups/w1-05-backup-2026-03-21.json`.
- [x] Restauration rejouee depuis le backup genere : `php core/tools/editorial_backup_restore.php restore var/backups/w1-05-backup-2026-03-21.json --force --storage=json`.
- [x] Coherence post-restore verifiee : `47` pages, `8` emplacements navigation, `0` article, `0` discussion (jeu editorial JSON courant).
- [x] Preuves archivees : `103-w1-05-backup.txt`, `104-w1-05-restore.txt`.
- [x] Ecart restant sur ce ticket : aucun pour la cloture S1.

### Ticket W1-06 - Recette manuelle ciblee FO/Admin

Priorite : P0  
Effort : 1 jour

Objectif :

- fermer les ecarts non detectes par tests automatiques

Actions :

- verifier parcours critiques desktop/mobile
- capturer preuves dans :
  - `front/desktop`
  - `front/mobile`
  - `admin/desktop`
  - `admin/mobile`

Commandes de validation :

```bash
cd docs/private/recette-preprod-v1-YYYY-MM-DD
test -d front/desktop -a -d front/mobile -a -d admin/desktop -a -d admin/mobile && echo OK
```

Livrables :

- dossier de preuve complet
- liste des anomalies avec severite

Checklist detaillee W1-06 (execution du 2026-03-20) :

- [x] Dossiers de preuve presents : `front/desktop`, `front/mobile`, `admin/desktop`, `admin/mobile`.
- [x] Verification automatisee des dossiers : `test -d ...` -> OK (`85-w1-06-directories-check.txt`).
- [x] Captures FO desktop/mobile archivees (routes critiques `/`, `/blog`, `/blog/proposer`, `/rss`) : detail dans `86-w1-06-proof-files.txt`.
- [x] Captures admin desktop/mobile archivees (page login + verifications protections `302` sur routes admin privees) : detail dans `86-w1-06-proof-files.txt`.
- [x] Non-regression FO/Admin rejouee : `./vendor/bin/phpunit tests/FrontControllerHttpTest.php tests/BlogRouteTest.php tests/DynamicRouteTest.php tests/AdminBlogServiceTest.php tests/BlogDiscussionApiControllerTest.php` -> OK (`53` tests, `306` assertions) preuve `87-w1-06-fo-admin-tests.txt`.
- [x] Liste d'anomalies et decision de traitement archivees : `88-w1-06-anomalies.md` (aucune anomalie bloquante relevee).
- [x] Verifications fin de mission executees : autoload/Controllers/HTTP/cache/hygiene docs (`89` a `93`).

### Ticket W1-07 - Cloture S1 et decision de passage

Priorite : P0  
Effort : 0.5 jour

Objectif :

- formaliser la decision de passage a S2

Actions :

- consolider resultats des tickets W1-01 a W1-06
- mettre a jour `README_V1_PREPARATION_DEPLOIEMENT.md` (statuts, ecarts, decision)

Commandes de validation :

```bash
cd backend
php -r "require 'vendor/autoload.php'; echo file_exists('vendor/autoload.php') ? 'autoload_ok'.PHP_EOL : 'autoload_missing'.PHP_EOL;"
php -r "require 'core/bootstrap.php'; echo class_exists('Caramagnols\\\\Admin\\\\AdminController') ? 'admin_controller_ok'.PHP_EOL : 'admin_controller_missing'.PHP_EOL;"
php -r "require 'core/bootstrap.php'; app_runtime_cache_clear(['pages','navigation','translations']); echo 'cache_cleared'.PHP_EOL;"
```

Livrables :

- decision de passage signee (`GO`)
- checklist S1 complete

## Checklists detaillees tickets S1

### W1-04 - Stabilite admin navigation/footer

- [x] Reproduire les cas `footer_notice`, banniere et remonter sur l'interface admin.
- [x] Corriger les ecarts de serialisation formulaire/payload sans regression FO.
- [x] Rejouer `AdminSerializedFormNormalizerTest`, `AdminNavigationServiceTest`, `AdminControllerTest`.
- [x] Purger le cache `navigation` et verifier le rendu menu/footer cote public.
- [x] Archiver preuves de commande + captures dans `docs/private/recette-preprod-v1-2026-03-20/`.

### W1-05 - Backup/restore avant operations destructives

- [x] Generer un backup editorial complet avant manipulation destructive.
- [x] Rejouer une restauration depuis le dernier backup disponible.
- [x] Comparer la coherence post-restore (`pages`, `navigation`, `blog`, `discussions`).
- [x] Archiver le fichier de backup et la sortie de restauration.
- [x] Documenter les ecarts ou anomalies de coherence (si presents).

### W1-06 - Recette manuelle ciblee FO/Admin

- [x] Capturer parcours critiques FO desktop (`/`, `/blog`, `/blog/proposer`, `/rss`) et couvrir la route article via tests de non-regression.
- [x] Capturer parcours critiques FO mobile (meme perimetre).
- [x] Capturer parcours admin desktop (login + protections `302`) et couvrir pages/menus/blog/discussions via tests.
- [x] Capturer parcours admin mobile (login + protections `302`).
- [x] Dresser la liste d'anomalies avec severite et decision de traitement.

### W1-07 - Cloture S1 et decision de passage

- [x] Consolider les preuves et statuts W1-01 a W1-06.
- [x] Rejouer les controles fin de tache : autoload, controllers, smoke HTTP.
- [x] Purger le cache runtime (`pages`, `navigation`, `translations`) en cloture.
- [x] Mettre a jour `README_V1_PREPARATION_DEPLOIEMENT.md` (decision de passage + restants).
- [x] Formaliser une decision explicite `GO` avec justification.

## Definition de done S1

Pour considerer S1 termine :

1. Tous les tickets W1-01 a W1-07 sont clos ou reportes avec justification.
2. Les commandes qualite et securite sont vertes.
3. Les preuves de recette sont archivees.
4. Le deploiement rapide est operationnel.
5. Les README canoniques sont alignes.

Statut cloture S1 (2026-03-21) :

- Decision passage S1 -> S2 : `GO`.
- Decision deploiement production immediat : `GO` (confirme apres addendum go-live du 2026-03-21, preuves `114` a `117`).
