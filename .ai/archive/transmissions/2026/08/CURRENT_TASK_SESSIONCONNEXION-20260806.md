# Tâche en cours — sessions persistantes sécurisées Private/Admin

## Identité

- ID : `SESSIONCONNEXION-2026-08-06`
- Demande originale : implémenter une reconnaissance persistante sécurisée, rotative, révocable et auditée pour BO Private et BO Admin.
- Projet et périmètre : `/home/surfacepro8/www/caramagnols`, backend PHP server-rendered, BO Admin, espace privé, SQL, logs, Cron Center, documentation et tests associés.
- Hors périmètre : production distante, déploiement, push Git, écriture SQL distante, migration destructive, changement de branche, réécriture d'historique, exposition ou manipulation de secrets réels.

## Sources de vérité et état initial

- `AGENTS.md` et guides chargés : `/home/surfacepro8/Workspace/AGENTS.md`, `AGENTS.md` projet, routeur central `.ai/prompts/ROUTER.md`, `guide-architecture/README.md`, profil `les-caramagnols.md`, socle `core/`, guides PHP/JavaScript/Node/MySQL/Linux, métiers association/gestion/contenu, checklists nouvelle fonctionnalité et migration, `.ai/README.md`, `.ai/TASK_TEMPLATE.md`.
- Documents projet lus avant modification applicative : `README.md`, `docs/README.md`, `docs/architecture.md`, `docs/admin/README.md`, `docs/security/README.md`, `docs/private/README.md`, `docs/private/backlog-pvt01.md`, `docs/backend/public-entrypoints.md`, `docs/backend/logging.md`, `docs/backend/bootstrap-i18n.md`, `docs/backend/installation-hors-webroot.md`, `docs/deployment/README.md`, `docs/deployment/runbook-v1-go-live.md`, `docs/roadmap/README.md`, `docs/roadmap/transition-v1-s1-s8.md`.
- Horodatage de début : 2026-08-06T09:34:09+02:00.
- Branche : `restore-prod-master-20260716`.
- Référence du commit initial : `12f7a027203ab689c601c20bd3bab4a16ceab8c7`.
- État Git initial (`git status --short`) : propre.
- Modifications préexistantes à préserver : aucune dans le worktree Git ; `.ai/CURRENT_TASK.md` existe déjà et porte une tâche active non clôturée sur les liens d'accès privé.
- Propriétaire du comportement : application Les Caramagnols, domaines `Admin`, `PrivatePortal`, `Security`, `Logging`, `Cron`, `Database`.

## Classifications

- Routage multi-IA : `C` — architecture préalable, implémentation non triviale, vérification renforcée.
- Niveau de risque : `R3` — profil projet déclaré/calculé `R3`, données personnelles, authentification, privilèges BO, migrations SQL et surface internet.
- Déclencheurs et justification : sécurité d'authentification, cookies persistants, droits Admin/Private séparés, données comptes, logs d'audit, Cron Center, compatibilité production et rollback.

## Responsabilités et autorisations

| Rôle | Identifiant local attribué | État | Périmètre ou contrainte |
|---|---|---|---|
| Routeur | Codex CLI session courante | actif | cadrage et transmission |
| Architecte | Codex CLI session courante | actif | analyse locale du dépôt |
| Auteur ou implémentateur | Codex CLI session courante | actif | modifications locales bornées au périmètre demandé |
| Vérificateur | Codex CLI session courante | actif | contrôles documentés réellement exécutés |
| Relecteur indépendant | non attribué | non demandé | facultatif, distinct si sollicité |
| Décideur humain | propriétaire humain | requis pour acceptation R3 et toute production | aucune approbation implicite |

- Mode de collaboration : `mono_agent`.
- Agent principal : Codex CLI session courante.
- Source de l'attribution : demande humaine courante.
- Prompts spécialisés invoqués : `ROUTER.md` explicitement demandé ; responsabilités architecte/implémentateur/vérificateur enchaînées selon workflow mono-agent.
- Périmètre d'écriture : fichiers source, tests, SQL versionné, documentation et configuration exemple dans `/home/surfacepro8/www/caramagnols`.
- Environnement et cible de déploiement autorisés : aucun.
- Actions destructives ou de production autorisées : aucune.

## Données, dépendances et rollback

- Données et classification : comptes Admin/Private, identifiants d'appareils, empreintes de jetons, IP/UA hachés ou minimisés, événements d'authentification ; données personnelles/confidentielles.
- Dépendances ou services externes : aucune nouvelle dépendance prévue sauf nécessité prouvée ; SMTP/logging/Cron Center existants seulement.
- Sauvegarde ou retour arrière : rollback local par Git pour le code ; feature flags désactivés par défaut ; migrations SQL additives ou idempotentes, sans rollback destructif.
- Critères d'arrêt : secret détecté, migration destructive nécessaire, production requise, contradiction de règles, impossibilité de séparer scopes Private/Admin, tests sécurité bloquants.

## Critères d'acceptation

1. Private et Admin restaurent une session courte depuis un appareil fiable via scopes et cookies séparés.
2. Les jetons persistants sont bruts uniquement côté client, hashés en base, rotatifs à chaque usage et révoqués par famille en cas de réutilisation.
3. La session Private ne peut jamais créer une session Admin ; Admin vérifie rôle, TOTP/allowlist selon l'existant et réauthentification sensible.
4. Les appareils sont listables, renommables et révocables côté Private et Admin selon permissions.
5. Les logs d'audit sont utiles et ne contiennent aucun token, mot de passe, cookie complet ou secret TOTP.
6. La fonctionnalité est désactivable par feature flags et le login classique reste fonctionnel.
7. Les migrations, services, intégrations, tests, Cron Center et documentation sont alignés.
8. Les validations documentées passent ou les blocages sont explicitement qualifiés.

## Cadrage et inventaire

- Analyse existante obligatoire en cours.
- Point de workflow : `.ai/CURRENT_TASK.md` préexistant reste non clôturé ; ce fichier suffixé porte la demande explicite `CURRENT_TASK_SESSIONCONNEXION.md` sans écraser la tâche active antérieure.

## Analyse utile

Synthèse de l'audit :

1. Session Admin : `backend/core/auth/admin.php` stocke le contexte sous `admin.session_key`, timeout inactivité 7200 s par défaut, activité rafraîchie par `admin_is_authenticated()`.
2. Cookies : session PHP globale `caramagnols_session` côté Admin/public, session Private séparée `PRIVATE_SESSION_NAME` par défaut `caramagnols_private`.
3. Timeouts : `ADMIN_INACTIVITY_TIMEOUT_SECONDS`, `ADMIN_REAUTH_TIMEOUT_SECONDS`, `PRIVATE_INACTIVITY_TIMEOUT_SECONDS`, `PRIVATE_REAUTH_TIMEOUT_SECONDS`.
4. Régénération session : `admin_login()`, `admin_logout()`, `PrivateAuth::establishSession()`, `PrivateAuth::logout()`.
5. TOTP Admin : `admin_totp_should_challenge()` + HMAC-SHA1 Base32, drift configurable ; Private : `PrivateMfaVerifier`.
6. Allowlist IP : `AdminController::guardAdminNetwork()` + `ip_matches_allowlist()`.
7. Réauth sensible : timeout et fonctions existent, mais `guardSensitiveAction()` était un stub ; implémenté serveur.
8. `session/ping` : POST CSRF, exige session Admin, prolonge l'activité via `admin_is_authenticated()`.
9. Private : guard, login et session fonctionnels avec `PrivateAuth`, `PrivateSession`, `PrivatePortalSecurityGuard`.
10. Private documenté mais partiel : table `private_sessions` existante mais pas utilisée runtime pour sessions actives.
11. Modèle utilisateur réel : Admin configuré par env/override, pas de table multi-admin ; Private via `car_private_users`.
12. Tables appareils/jetons : aucune table active existante avant chantier.
13. Migrations SQL : migrations versionnées `backend/sql/editorial/*.sql` via `EditorialSchemaManager`.
14. Logging : `AppEventLogger` vers `security.log`, `content.log`, `access.log` et SQL structuré.
15. Cron Center : `CronJobRepository`, `CronScriptPolicy`, `run_cron_center.php`.
16. Tests existants : AdminController/Auth/FrontController/PrivatePortal/Cron/Logging + modules PrivateApps.
17. Docs à jour : README, security, private, admin, architecture, public-entrypoints, logging, deployment, roadmap, `.env.example`.

## Plan d'implémentation validé

- Domaine `backend/src/Identity` avec scopes `identity/private/admin`.
- SQL additif `015_persistent_auth.sql` : `trusted_devices`, `persistent_session_tokens`.
- Cookies persistants séparés par scope, `selector.secret`, `HttpOnly`, `SameSite=Strict`, `Secure` en production.
- Rotation stricte ; réutilisation => révocation famille + appareil selon politique.
- Restauration session courte non fraîche (`last_reauth_at=0`) pour préserver la réauthentification sensible.
- UI appareils Private/Admin avec CSRF, révocation et renommage.
- Cron Center : purge idempotente `purge_persistent_auth.php`.

## Résultat de l'auteur ou implémentateur

- Implémentation réalisée, committée, poussée et déployée en production OVH.
- Feature flags ajoutés, désactivés par défaut dans le code, puis activés explicitement en production à la demande humaine.
- Login classique conservé si les flags sont à `false`.
- Migration SQL additive `015_persistent_auth.sql` synchronisée en production par `deploy-release.sh`.
- Hotfix post-activation appliqué pour préserver plusieurs en-têtes `Set-Cookie` et éviter la perte du cookie de session courte lors de la rotation persistante.

## Validations et preuves

| Porte | Contrôle ou commande | État | Preuve ou résultat synthétique |
|---|---|---|---|
| G0 | règles, profil, état Git, sources de vérité | en cours | gouvernance validée, risque `R3`, worktree initial propre |
| G1 | syntaxe, statique, diff, secrets | OK | `php -l` ciblé OK ; `composer phpstan --working-dir=backend` OK ; `composer phpcs --working-dir=backend` OK |
| G2 | tests fonctionnels et refus | OK | `./vendor/bin/phpunit` dans `backend` : 730 tests, 5966 assertions |
| G3 | sécurité, données, observabilité, accessibilité | OK partiel | `composer audit --working-dir=backend` OK ; `npm audit --audit-level=high` OK ; logs/alertes mis à jour |
| G4 | compatibilité, rollback, migrations, déploiement | OK prod | migration additive ; flags désactivables ; rollback documenté ; `purge_persistent_auth.php --dry-run --json` OK local et prod ; déploiement prod OK |
| G5 | diff relu, documentation, preuves, risques | OK | docs et `.env.example` mis à jour ; recherche secrets ciblée sans fuite de token brut ; hotfix cookies relu et validé |

## Clôture Git et production

- Commit principal : `ad5fc6f feat: add persistent trusted device sessions`.
- Commit hotfix : `a1a97c9 fix: preserve session cookies during persistent auth`.
- Branche poussée : `origin/restore-prod-master-20260716`.
- Déploiement : `DEPLOY_TARGET=prod`, `REMOTE_HOST=ovh-boutique`, `REMOTE_BACKEND=/home/lescaramgl-ssh/caramagnols/backend`.
- Résultat déploiement : `deploy-release completed`, `cache_cleared`, `autoload_ok`.
- Vérification prod : site public `HTTP/2 200`, schema sync `current=15 target=15 pending=0`, hash distant `src/Http/Response.php` identique au local.
- Activation prod demandée après déploiement : `PERSISTENT_AUTH_ENABLED=true`, `PRIVATE_PERSISTENT_AUTH_ENABLED=true`, `ADMIN_PERSISTENT_AUTH_ENABLED=true`.
- Sauvegarde `.env` prod avant activation : `.env.backup-persistent-auth-20260806-113304`.

## Décisions, dérogations et dette

- Décisions : feature flags requis ; aucune écriture production ; Admin sans table multi-admin => rattachement par identifiant Admin pseudonymisé.
- Dérogations : `composer test --working-dir=backend` dépasse le timeout Composer 300 s ; validation complète exécutée via `./vendor/bin/phpunit`.
- Dette ou risques résiduels : parcours navigateur SQL réel à refaire en environnement avec DB disponible ; fonctions globales super-admin non ajoutées faute de modèle multi-admin réel.

## Revue finale indépendante

- Identifiant local du relecteur : non attribué.
- Revue facultative demandée : non.
- Avis : non applicable.

## Archivage de clôture

- État : demandé le 2026-08-06.
- Destination : `.ai/archive/transmissions/2026/08/CURRENT_TASK_SESSIONCONNEXION-20260806.md`.

## État

Implémentation, validations, commit, push, déploiement, activation production et hotfix terminés.
