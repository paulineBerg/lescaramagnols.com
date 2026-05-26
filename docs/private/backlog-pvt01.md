# Backlog Technique Portail Prive Famille - PVT-01

Date de mise a jour : 2026-05-26
Statut : actif

Ce document est le backlog **pret a execution** du lot `PVT-01 - Foundation` du portail prive famille.

References :

- `docs/private/README.md`
- `docs/roadmap/transition-v1-s1-s8.md`
- `docs/security/README.md`
- `docs/backend/public-entrypoints.md`

## Objectif PVT-01

Construire une fondation securisee et non bloquante pour le Front-Office public :

1. route groupe `/private` (ou equivalent configurable),
2. garde d'acces centralisee,
3. dashboard prive minimal,
4. audit de base.

## Contraintes de lot

1. Aucun impact regressif sur les routes publiques existantes.
2. Toute logique nouvelle dans `backend/src/*`.
3. Aucun fichier prive accessible directement dans `backend/public`.
4. Couverture tests minimale sur auth guard + routes privees.

## Definition of Done lot PVT-01

Le lot est termine si :

1. Les routes privees non authentifiees renvoient `302` vers login prive (ou `401` sur API privee).
2. Les routes privees authentifiees fonctionnent avec session valide.
3. Les tentatives refusees sont journalisees avec `request_id`.
4. Les quality gates restent vertes (`composer test/phpstan/phpcs`, `npm lint/test/build`).
5. La documentation de configuration/deploiement est mise a jour.

## Cadrage produit valide (atelier 2026-03-20)

Perimetre fonctionnel decide avec le super-admin :

1. Invitation et activation :
   - seul le super-admin peut inviter un membre prive ;
   - l'utilisateur finalise lui-meme son activation via lien email ;
   - validite du lien d'invitation : 7 jours ;
   - un email ne peut appartenir qu'a un seul compte prive.
2. Authentification et securite :
   - mode MVP retenu : authentification locale (`email` + mot de passe), compatible migration OIDC ulterieure ;
   - MFA non obligatoire globalement au demarrage, mais mecanisme requis : TOTP + codes de secours ;
   - politique mot de passe requise (longueur mini + complexite), valeurs exactes a figer ;
   - mot de passe oublie self-service requis ;
   - verrouillage compte apres 3 echecs de connexion, pendant 24h.
3. Autorisations et administration :
   - affectation des modules uniquement par le super-admin, utilisateur par utilisateur ;
   - l'utilisateur prive ne gere pas ses droits/modules ;
   - suspension compte sans invalidation immediate des sessions actives (choix produit actuel, risque securite assume temporairement).
4. Audit, conformite et alertes :
   - consultation des journaux reservee au super-admin ;
   - retention des logs d'audit : 1 an par defaut ;
   - notifications email sur evenements sensibles requises ;
   - exigences RGPD activees (export/suppression des donnees privees).
5. Experience utilisateur :
   - UI portail prive mobile-first ;
   - ajout d'un mecanisme d'installation Android type "appli" (PWA).

## Checklists detaillees tickets PVT-01 (reste a developper)

### Checklist PVT01-T01 - Registre de configuration portail prive

- [ ] Ajouter `PRIVATE_PORTAL_ENABLED` et `PRIVATE_PORTAL_BASE_PATH` dans `backend/config/config.php`.
- [ ] Documenter les variables dans `backend/.env.example` avec valeurs par defaut non bloquantes.
- [ ] Etendre `backend/core/tools/check_env.php` uniquement si de nouvelles contraintes deviennent critiques.
- [ ] Verifier demarrage application avec et sans variables privees explicites.
- [ ] Archiver la sortie `composer check-env` dans le dossier de preuve preprod courant.

### Checklist PVT01-T02 - Route resolver prive

- [ ] Ajouter la resolution des routes privees dans `FrontController` sans regression des routes publiques.
- [ ] Isoler le routage prive dans `backend/src/PrivatePortal/Http/*`.
- [ ] Ajouter des tests routes privees et non-regression des routes publiques.
- [ ] Valider `composer test -- --filter PrivatePortal` puis `--filter FrontController`.
- [ ] Documenter les nouvelles routes dans `docs/backend/public-entrypoints.md` si exposition HTTP modifiee.

### Checklist PVT01-T03 - Guard authentification privee

- [ ] Implementer un guard prive distinct du guard admin.
- [ ] Configurer un cookie/session prive dedie (`name`, timeout inactivite, regeneration session id).
- [ ] Implementer le verrouillage apres 3 echecs login pendant 24h.
- [ ] Integrer la MFA TOTP + codes de secours (non obligatoire globalement au demarrage).
- [ ] Ajouter la redirection vers login prive en non-authentifie.
- [ ] Verifier expiration de session privee sur inactivite.
- [ ] Ajouter les tests `PrivatePortalSecurity` et archiver la sortie.

### Checklist PVT01-T04 - CSRF prive et protections POST

- [ ] Appliquer CSRF sur tous les formulaires prives en POST/PUT/PATCH/DELETE.
- [ ] Verifier rejet des requetes sans token et acceptation avec token valide.
- [ ] Ajouter les tests automatises cibles (`PrivatePortalCsrf`).
- [ ] Verifier que la protection reste non bloquante pour le Front-Office public.
- [ ] Mettre a jour la documentation securite si de nouvelles regles sont ajoutees.

### Checklist PVT01-T05 - Dashboard prive minimal

- [ ] Creer `dashboard.php` et `layout.php` dedies dans `backend/templates/private/`.
- [ ] Introduire un registre de modules prive (`backend/src/PrivatePortal/ModuleRegistry/*`).
- [ ] Afficher uniquement les modules autorises par permissions.
- [ ] Garantir que les utilisateurs prives ne peuvent pas modifier leurs droits/modules depuis le dashboard.
- [ ] Ajouter des tests de rendu/autorisation `PrivatePortalDashboard`.
- [ ] Verifier absence de fuite de liens vers des modules non autorises.

### Checklist PVT01-T06 - Journalisation audit privee

- [ ] Logger les acces prives en succes et refus avec `request_id`.
- [ ] Inclure route, role/acteur masque, IP et statut de decision.
- [ ] Forcer une retention des logs prives a 1 an par defaut.
- [ ] Restreindre la consultation des logs au super-admin.
- [ ] Ajouter les notifications email sur evenements sensibles (invitation, activation, verrouillage, reset securite).
- [ ] Etendre `check_log_alerts` si un seuil prive devient necessaire.
- [ ] Verifier les tests `PrivatePortalAudit`.
- [ ] Archiver une preuve de logs exploitables sur un scenario de refus.

### Checklist PVT01-T07 - Headers anti-indexation portail prive

- [ ] Ajouter `X-Robots-Tag: noindex, nofollow, noarchive` sur routes privees.
- [ ] Ajouter `Disallow: /private/` dans `robots.txt` (ou route robots equivalente).
- [ ] Verifier la presence du header sur une route privee representative.
- [ ] Rejouer `check_security_headers.php` sur l'URL cible.
- [ ] Documenter la verification dans `docs/security/README.md` et ce backlog.

### Checklist PVT01-T08 - Squelette stockage prive hors webroot

- [ ] Creer l'arborescence `backend/private/*` (modules, storage, config) hors webroot.
- [ ] Poser des permissions dossier explicites et documentees.
- [ ] Verifier qu'aucune route publique n'expose ces chemins.
- [ ] Ajouter garde de configuration pour les chemins prives obligatoires.
- [ ] Mettre a jour `docs/backend/installation-hors-webroot.md` si la procedure evolue.

## Backlog MVP IAM ajoute (prealable aux modules metier)

### PVT01-IAM-01 - Workflow invitation email super-admin

Priorite : P0  
Effort estime : 1 jour

Scope :

1. Ecran admin pour inviter un utilisateur prive par email.
2. Generation token d'invitation usage unique, expiration 7 jours.
3. Finalisation d'activation par l'utilisateur (creation mot de passe + activation compte).

Critere d'acceptation :

1. Invitation envoyee uniquement par super-admin.
2. Lien expire au bout de 7 jours.
3. Compte actif uniquement apres finalisation utilisateur.

### PVT01-IAM-02 - Mot de passe oublie + reset securise

Priorite : P0  
Effort estime : 0.5 jour

Scope :

1. Flux "mot de passe oublie" self-service par email.
2. Token de reset usage unique et expiration courte.
3. Journalisation des demandes et resets effectifs.

Critere d'acceptation :

1. Aucun reset sans token valide.
2. Les tokens expires/invalides sont refuses.
3. Evenements de reset traces dans l'audit.

### PVT01-IAM-03 - MFA TOTP + codes de secours

Priorite : P0  
Effort estime : 1 jour

Scope :

1. Enrollment TOTP sur compte prive.
2. Generation de codes de secours (usage unique).
3. Verification TOTP ou code de secours au login quand MFA activee.

Critere d'acceptation :

1. TOTP valide -> login autorise.
2. Code de secours valide consomme puis invalide.
3. Echec MFA trace dans l'audit.

### PVT01-IAM-04 - Lockout 3 echecs / 24h

Priorite : P0  
Effort estime : 0.5 jour

Scope :

1. Compteur d'echecs par compte (et IP en defense additionnelle).
2. Blocage automatique apres 3 echecs.
3. Deblocage automatique apres 24h.

Critere d'acceptation :

1. 3 echecs consecutifs bloquent le compte.
2. Le message de refus est non verbeux (pas de fuite d'info).
3. Deblocage automatique effectif a 24h.

### PVT01-IAM-05 - Affectation modules au cas par cas

Priorite : P0  
Effort estime : 1 jour

Scope :

1. Ecran super-admin pour activer/desactiver des modules par utilisateur.
2. Verifications back-end de permissions module par module.
3. Dashboard prive personnalise selon les modules assignes.

Critere d'acceptation :

1. Seul le super-admin modifie les assignations.
2. Un utilisateur voit uniquement ses modules autorises.
3. Acces direct URL d'un module non assigne -> `403`.

### PVT01-IAM-06 - Audit, retention et RGPD

Priorite : P0  
Effort estime : 1 jour

Scope :

1. Retention par defaut des logs prive a 1 an.
2. Interface de consultation reservee super-admin.
3. Capacites RGPD minimales (export et suppression compte prive).

Critere d'acceptation :

1. Les logs de plus d'un an sont purges automatiquement.
2. Export/suppression sont tracables en audit.
3. Acces audit refuse a tout role non super-admin.

### PVT01-IAM-07 - Dashboard mobile-first + installation Android

Priorite : P1  
Effort estime : 1 jour

Scope :

1. Ecrans dashboard/login prives optimises mobile-first.
2. Manifest + service worker pour installation Android (PWA).
3. Message d'aide utilisateur pour "installer l'application".

Critere d'acceptation :

1. UX stable sur mobile (navigation/touch/formulaires).
2. Installation PWA possible sur Android.
3. Aucun impact regressif sur Front-Office public.

## Ticketisation PVT-01

## PVT01-T01 - Registre de configuration portail prive

Priorite : P0  
Effort estime : 0.5 jour

Scope :

1. Introduire les cles de config portail prive (`PRIVATE_PORTAL_ENABLED`, `PRIVATE_PORTAL_BASE_PATH`).
2. Ajouter valeurs par defaut saines (desactive par defaut si necessaire).

Fichiers cibles :

1. `backend/config/config.php`
2. `backend/.env.example`
3. `backend/core/tools/check_env.php` (si nouvelles variables critiques)

Critere d'acceptation :

1. L'application demarre avec/sans cles privees definies.
2. `check-env` ne casse pas l'existant.

Validation :

```bash
cd backend
composer check-env
composer check-env -- --env=production
```

## PVT01-T02 - Route resolver prive

Priorite : P0  
Effort estime : 0.5 jour

Scope :

1. Ajouter la resolution de routes privees dans le front controller.
2. Isoler ce routage dans un composant dedie (`src/PrivatePortal/Http/*`).

Fichiers cibles :

1. `backend/src/Http/FrontController.php`
2. `backend/src/PrivatePortal/Http/*` (nouveau)
3. Tests route HTTP dedies

Critere d'acceptation :

1. Route privee existe et repond.
2. Routes publiques inchangées.

Validation :

```bash
cd backend
composer test -- --filter PrivatePortal
composer test -- --filter FrontController
```

## PVT01-T03 - Guard authentification privee (phase foundation)

Priorite : P0  
Effort estime : 1 jour

Scope :

1. Ajouter un guard `private` separe de l'admin.
2. Session privee dediee (nom cookie distinct).
3. Timeout inactivite prive configurable.

Fichiers cibles :

1. `backend/src/PrivatePortal/Security/*` (nouveau)
2. `backend/core/auth/admin.php` uniquement pour reutilisation utilitaire sans couplage
3. templates login prive

Critere d'acceptation :

1. Non-auth -> redirect login prive.
2. Auth valide -> acces dashboard prive.
3. Expiration inactivite effective.

Validation :

```bash
cd backend
composer test -- --filter PrivatePortalSecurity
```

## PVT01-T04 - CSRF prive et protections POST

Priorite : P0  
Effort estime : 0.5 jour

Scope :

1. Reutiliser les helpers CSRF existants pour le contexte prive.
2. Verifier que tous formulaires prives POST sont proteges.

Fichiers cibles :

1. `backend/src/PrivatePortal/Http/*`
2. `backend/templates/private/*`

Critere d'acceptation :

1. POST sans token refuse.
2. POST avec token valide accepte.

Validation :

```bash
cd backend
composer test -- --filter PrivatePortalCsrf
```

## PVT01-T05 - Dashboard prive minimal

Priorite : P1  
Effort estime : 0.5 jour

Scope :

1. Creer une page d'accueil privee minimale.
2. Afficher modules disponibles selon permissions (stub pour foundation).

Fichiers cibles :

1. `backend/templates/private/dashboard.php` (nouveau)
2. `backend/templates/private/layout.php` (nouveau)
3. `backend/src/PrivatePortal/ModuleRegistry/*` (nouveau)

Critere d'acceptation :

1. UI privee distincte de l'admin.
2. Pas de fuite de liens modules non autorises.

Validation :

```bash
cd backend
composer test -- --filter PrivatePortalDashboard
```

## PVT01-T06 - Journalisation audit privee

Priorite : P0  
Effort estime : 0.5 jour

Scope :

1. Logger les acces prives (succes/refus).
2. Inclure `request_id`, route, acteur masque, ip.

Fichiers cibles :

1. `backend/src/Logging/*` (extension non regressif)
2. `backend/src/PrivatePortal/Audit/*`
3. `backend/core/tools/check_log_alerts.php` (si extension seuils prive)

Critere d'acceptation :

1. Les acces prives laissent une trace.
2. Les refus d'acces sont detectables.

Validation :

```bash
cd backend
composer test -- --filter PrivatePortalAudit
composer check-log-alerts -- --since-minutes=60
```

## PVT01-T07 - Headers anti-indexation portail prive

Priorite : P0  
Effort estime : 0.5 jour

Scope :

1. Ajouter `X-Robots-Tag: noindex, nofollow, noarchive` sur routes privees.
2. Ajouter/mettre a jour directive `robots.txt` pour `/private`.

Fichiers cibles :

1. `backend/core/security.php`
2. `backend/public/robots.txt` (ou equivalent route robots)

Critere d'acceptation :

1. Header present sur pages privees.
2. `robots.txt` contient `Disallow: /private/`.

Validation :

```bash
cd backend
php core/tools/check_security_headers.php --url=https://www.lescaramagnols.com
```

## PVT01-T08 - Squelette stockage prive hors webroot

Priorite : P1  
Effort estime : 0.5 jour

Scope :

1. Creer arborescence `backend/private/*` non exposee.
2. Ajouter garde de configuration/permissions.

Fichiers cibles :

1. `backend/private/.gitkeep` + sous-dossiers
2. documentation installation/deploiement

Critere d'acceptation :

1. Aucune route web directe vers `backend/private`.
2. Permissions dossier explicites documentees.

Validation :

```bash
cd backend
php -r "echo is_dir('private') ? 'private_dir_ok'.PHP_EOL : 'private_dir_missing'.PHP_EOL;"
```

## PVT01-T09 - Tests integration prives

Priorite : P0  
Effort estime : 0.5 jour

Scope :

1. Ajouter tests HTTP pour login prive, guard, dashboard.
2. Ajouter tests unitaires security service prive.

Fichiers cibles :

1. `backend/tests/*PrivatePortal*` (nouveaux)

Critere d'acceptation :

1. Cas happy path + cas refuses couverts.
2. Test de non regression FO present.

Validation :

```bash
cd backend
composer test
composer phpstan
composer phpcs
```

## PVT01-T10 - Documentation/operations/rollback

Priorite : P0  
Effort estime : 0.5 jour

Scope :

1. Documenter variables `.env` du portail prive.
2. Documenter deploiement, purge cache, rollback.
3. Documenter runbook incident de base.

Fichiers cibles :

1. `docs/private/README.md`
2. `README.md`
3. `docs/README.md`

Critere d'acceptation :

1. Documentation exploitable sans ambiguite.
2. Liens docs valides.

Validation :

```bash
cd frontend
npm run hygiene:docs
```

## Ordre d'execution recommande

1. `PVT01-T01`
2. `PVT01-T02`
3. `PVT01-T03`
4. `PVT01-T04`
5. `PVT01-T05`
6. `PVT01-T06`
7. `PVT01-T07`
8. `PVT01-T08`
9. `PVT01-IAM-01`
10. `PVT01-IAM-02`
11. `PVT01-IAM-03`
12. `PVT01-IAM-04`
13. `PVT01-IAM-05`
14. `PVT01-IAM-06`
15. `PVT01-IAM-07`
16. `PVT01-T09`
17. `PVT01-T10`

## Checklist lot PVT-01 (Go/No-Go)

- [ ] Guards prives fonctionnels, routes publiques intactes.
- [ ] Invitation super-admin + activation utilisateur par email operationnelles.
- [ ] Mot de passe oublie securise operationnel.
- [ ] MFA TOTP + codes de secours operationnelle.
- [ ] Lockout 3 echecs / 24h operationnel.
- [ ] Assignation modules par super-admin effective.
- [ ] CSRF prive valide sur tous POST.
- [ ] Logs audit prives visibles.
- [ ] Retention audit 1 an + acces super-admin uniquement.
- [ ] Anti-indexation privee active.
- [ ] Arborescence privee hors webroot en place.
- [ ] UX mobile-first + installation Android (PWA) validees.
- [ ] Tests quality gates verts.
- [ ] Runbook deploy/rollback documente.

## Commandes globales de cloture lot

```bash
cd backend
composer test && composer phpstan && composer phpcs
php -r "require 'core/bootstrap.php'; app_runtime_cache_clear(['pages','navigation','translations']); echo 'cache_cleared'.PHP_EOL;"
cd ../frontend
npm run lint && npm run test:run && npm run build
```
