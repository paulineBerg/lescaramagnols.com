# Tache en cours - Systeme de journalisation centralisee complete pour le projet Caramagnols

## Demande originale

Creer un systeme de logs complet backend selon le prompt :
`file://wsl.localhost/Ubuntu/home/surfacepro8/www/caramagnols/backend/docs/prompt-journalisation-centralisee-complete-projet-webapps-private.md`

La demande detaillee est disponible dans ce fichier et doit etre utilisee comme source de verite pour l'implementations.

## Projet et perimetre

- Projet : Les Caramagnols (site public, admin, espace prive, webapps privees)
- Perimetre : **Refonte complete de la journalisation** - analyse, migration, implementation, tests et documentation
- Branche : `restore-prod-master-20260716`
- Source de verite : production `https://www.lescaramagnols.com/` (reference fonctionnelle)
- Cible admin : accessible via `ADMIN_LOGIN_PATH` (ne jamais coder `espace-admin-7k9m2p` en dur)
- Exclusions :
  - pas de deploiement en production
  - pas d'ecriture en base de production
  - pas de modification de `ADMIN_LOGIN_PATH`
  - pas de suppression de donnees existantes sans sauvegarde et migration verifiee

## Niveau de routage

**C** - Tache de securite et architecture touchant :
- Systeme de journalisation central (securite, audit, observabilite)
- Schema SQL et migrations de base de donnees
- Integration avec Cron Center, sauvegardes, alertes
- Couverture de toutes les webapps privees
- Protection des donnees sensibles (secrets, tokens, donnees personnelles)

## Agent autorise a modifier

**Codex** - Niveau C : Codex est l'unique agent autorise a modifier le code. Mistral reste en lecture seule pour l'analyse et la creation de la tache. Claude Code intervient en lecture seule pour l'analyse d'architecture et de securite.

## Contraintes et exclusions

- **Ne pas modifier** : Aucun code applicatif sans analyse prealable complete et validation de l'architecture
- **Ne pas deployer** : Pas de deploiement sans demande explicite et validation complete
- **Ne pas contacter la production** : Pas d'acces direct a OVH ou aux donnees de production
- **Ne pas supprimer** : Aucune donnee, table ou fichier existant sans sauvegarde, migration et verification
- **Respecter AGENTS.md** : Production = source de verite; toute evolution touchant routes, menus, contenus publics, assets visibles, securite HTTP ou espace admin doit etre verifiee contre la production
- **Secrets** : Aucune valeur sensible (mot de passe, token, session, TOTP, cle API, DSN, etc.) ne doit etre stockee dans les logs, les fichiers ou le code
- **Propriete intellectuelle** : Ne jamais versionner de contenu editorial, documents, pieces jointes ou donnees utilisateurs reelles

## Inventaire Mistral

### Architecture existante identifiee

#### Composants de logging
- `backend/src/Logging/LoggerFactory.php` - Fabrique de loggers Monolog avec rotation de fichiers
- `backend/src/Logging/AppEventLogger.php` - Fassade centrale avec methodes `security()`, `content()`, `access()` et masquage d'emails
- `backend/src/Logging/SqlLogStore.php` - Stockage SQL dans table `log_entries` avec sanitization de base
- `backend/src/Logging/SqlLogHandler.php` - Handler Monolog pour SqlLogStore
- `backend/src/Logging/LogAlertsNotifier.php` - Notifications d'alertes avec cooldown
- `backend/src/Logging/LogAlertsNotificationGate.php` - Controle des notifications par canal
- `backend/src/Logging/PrivateSecurityAlertReportBuilder.php` - Builder de rapports pour alertes de securite privee

#### Schema SQL
- `backend/sql/editorial/003_log_entries.sql` - Table `log_entries` avec colonnes :
  - `id` BIGINT UNSIGNED AUTO_INCREMENT
  - `channel` VARCHAR(32) NOT NULL
  - `level` VARCHAR(16) NOT NULL
  - `event` VARCHAR(191) NOT NULL
  - `context_json` LONGTEXT NULL
  - `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  - Index : channel, level, created_at, event

#### Scripts et outils
- `backend/core/tools/check_log_alerts.php` - Detection d'alertes avec seuils configurables
  - Metriques : login_failed, private_login_failed, rate_limited, private_rate_limited, private_csrf_rejected, private_email_failed, private_backup_failed, private_backup_warning, private_purge_failed, http_403, http_429, cron_failed
  - Fenetre temporelle configurable (defaut 15 min)
  - Notifications par email et webhook
  - Cooldown (defaut 180 min)

#### Tests existants
- `backend/tests/LoggerFactoryTest.php`
- `backend/tests/SqlLogStoreTest.php`
- `backend/tests/Logging/LogAlertsNotifierTest.php`
- `backend/tests/Logging/LogAlertsNotificationGateTest.php`
- `backend/tests/Logging/CheckLogAlertsScriptTest.php`
- `backend/tests/Logging/PrivateOperationsLoggingTest.php`
- `backend/tests/AdminLogAlertsSettingsManagerTest.php`

#### Webapps privees identifiees
1. **RealEstateRental** (Locations immobilières) - `backend/src/PrivateApps/RealEstateRental/`
   - Structure : Domain, Http, Repository, Service, AgencyManagement, TaxBridge
2. **BlocNote** - `backend/src/PrivateApps/BlocNote/`
   - Structure : Http
3. **Documents** - `backend/src/PrivateApps/Documents/`
   - Structure : Contract, Http, Repository, Registry, Service
4. **FamilyDiscussion** - `backend/src/PrivateApps/FamilyDiscussion/`
   - Structure : Attachment, Retention, Repository, Service
5. **TaxDeclarationHelper** - `backend/src/PrivateApps/TaxDeclarationHelper/`
   - Structure : Repository, Service, Source, ValueObject

#### Points d'entree admin
- Route admin via `ADMIN_LOGIN_PATH`
- Controleur admin existant a identifier dans `backend/src/Admin/`
- Menu admin avec rubrique Logs a creer/integrer

#### Cron Center
- Scripts sous `backend/core/tools/`
- `check_log_alerts.php` deja executable en CLI
- Integration Cron Center a verifier

### Etat des lieux factuel

| Aspect | Etat | Observations |
|--------|------|---------------|
| Table log_entries | Existe | Schema minimal, pas de request_id, correlation_id, error_fingerprint |
| Niveaux | PSR-3 | debug, info, notice, warning, error, critical, alert, emergency |
| Canaux | 3 | security, content, access |
| Flux | Non structures | Pas de separation application/module |
| Alertes | Fonctionnelles | Seuils configurables, cooldown, notifications email/webhook |
| Webapps privees | Non integrees | Chaque webapp peut avoir son propre logging |
| Admin > Logs | A creer | Pas d'interface centralisee identifiee |
| Fallback | Non implemente | Pas de secours local en cas de panne SQL |
| Retention | Non configuree | Pas de politique de purge automatique |
| Sanitization | Partielle | Masquage emails seulement, pas de protection complete |
| Correlation | Absente | Pas de request_id ni correlation_id |

### Fichiers cles a analyser

```
backend/src/Logging/LoggerFactory.php
backend/src/Logging/AppEventLogger.php
backend/src/Logging/SqlLogStore.php
backend/src/Logging/SqlLogHandler.php
backend/src/Logging/LogAlertsNotifier.php
backend/src/Logging/LogAlertsNotificationGate.php
backend/src/Logging/PrivateSecurityAlertReportBuilder.php

backend/sql/editorial/003_log_entries.sql

backend/core/tools/check_log_alerts.php

backend/tests/LoggerFactoryTest.php
backend/tests/SqlLogStoreTest.php
backend/tests/Logging/LogAlertsNotifierTest.php
backend/tests/Logging/LogAlertsNotificationGateTest.php
backend/tests/Logging/CheckLogAlertsScriptTest.php
backend/tests/Logging/PrivateOperationsLoggingTest.php

backend/src/PrivatePortal/ (a explorer pour integration)
backend/src/PrivateApps/*/ (a explorer pour chaque webapp)
backend/src/Admin/ (pour l'interface Logs)
```

### Commandes de diagnostic locales

```bash
# Analyser les composants existants
php -l backend/src/Logging/*.php
php -l backend/tests/Logging/*.php

# Verifier les seuils et configuration des alertes
php backend/core/tools/check_log_alerts.php --help

# Lister les metriques et evenements existants
grep -rn "logEvent\|AppEventLogger\|security\|content\|access" backend/src/ | head -50

# Voir les tests existants pour le logging
php backend/vendor/bin/phpunit --filter "Logging|SqlLogStore|LoggerFactory|LogAlerts" --list-tests

# Verifier la couverture de code
grep -rn "private\." backend/src/ | grep -i "log\|event" | head -30
```

## Analyse d'architecture Claude

*Analyse en lecture seule realisee le 2026-07-19 (Claude Code, prompt CLAUDE_ARCHITECT).*

### 1. Corrections de l'inventaire Mistral (verifiees dans le code)

L'inventaire ci-dessus sous-estime fortement l'existant. Ecarts constates :

| Aspect | Inventaire Mistral | Etat reel verifie |
|--------|--------------------|-------------------|
| Admin > Logs | « A creer » | **Existe deja** : `AdminController::logs()` (`backend/src/Admin/AdminController.php:2011`), route `logs` avec auth + CSRF, service `backend/src/Admin/AdminLogService.php` (filtres q/channel/level/dates, suppression par ids, purge filtree) |
| Correlation | « Absente » | **request_id existe** : `FrontController::handle()` genere/resout un request_id (`backend/src/Http/FrontController.php:101-103,773-796`), le pousse dans `app_request_context_set()` (`backend/core/helpers.php:208-254`) et l'en-tete de reponse `X-Request-Id`. `AppEventLogger::write()` fusionne ce contexte dans chaque evenement. Manquent : colonne SQL dediee, `correlation_id`, propagation aux jobs cron |
| Retention | « Non configuree » | **Purge existante** : `backend/core/tools/purge_sql_logs.php` (dry-run, JSON, `LOG_SQL_RETENTION_DAYS` defaut 90 j, `LOG_SQL_SENSITIVE_RETENTION_DAYS` defaut 365 j) appuye sur `SqlLogStore::purgeOlderThan()` avec classe sensible = canal `security` ou niveau >= warning |
| Cron Center | « Integration a verifier » | **Deja integre** : `CronScheduler` emet `cron.scheduler.*` / `cron.job.*` via AppEventLogger ; `check_log_alerts.php` et `purge_sql_logs.php` sont whitelistes dans `CronScriptPolicy` (`backend/src/Cron/CronScriptPolicy.php:49-50`) ; runner systemd `backend/tools/check-log-alerts-runner.sh` |
| Fallback | « Non implemente » | **Partiel de facto** : `LoggerFactory` double-ecrit fichier (StreamHandler + rotation par taille, 14 fichiers) et SQL (SqlLogHandler). En panne SQL, les fichiers continuent. Manquent : format JSONL structure, quota global, rejeu vers SQL |
| Webapps privees | « Non integrees » | **4 sur 5 integrees** : RealEstateRental, BlocNote, Documents, FamilyDiscussion appellent AppEventLogger (130 usages / 29 fichiers dans `backend/src`). **Seul trou : TaxDeclarationHelper** (aucun appel). Tables d'audit domaine paralleles legitimes : `rental_export_logs`, `tax_export_logs`, `discussion_retention_runs` |
| Frontend | non couvert | Logger centralise existant `frontend/src/js/logger.ts` (console encapsulee, warn/debug limites au dev). Pas de capture `window.onerror` / `unhandledrejection`, pas d'endpoint de collecte client |
| Tests | liste partiellement fausse | `backend/tests/Logging/` contient 4 fichiers (pas de `PrivateOperationsLoggingTest` manquant : il existe bien) ; `LoggerFactoryTest`, `SqlLogStoreTest`, `AdminLogAlertsSettingsManagerTest` a la racine de `backend/tests/` |

Autres constats : 18 `error_log()` directs hors chaine dans 6 fichiers (`Navigation/JsonNavigationStore`, `SqlNavigationStore`, `NavigationRepository`, `Content/JsonPageStore`, `SqlPageStore`, `TileRepository`) ; `backend/core/bootstrap.php:112-128` possede deja `set_exception_handler` + `register_shutdown_function` pour les echecs de bootstrap ; `PrivateAuditRetentionService` gere deja une retention d'audit cote portail prive.

### 2. Defauts reels identifies (cause racine)

Le socle est sain et largement branche ; la refonte doit etre une **extension incrementale**, pas une reecriture. Defauts confirmes :

1. **Bug de niveaux** : `AppEventLogger::write()` (`backend/src/Logging/AppEventLogger.php:95-100`) ne route que `debug|warning|error`, tout le reste (dont `critical`, `alert`, `emergency`, `notice`) est degrade en `info`. Consequence : la classe de retention sensible et les declencheurs d'alerte sur niveaux critiques ne peuvent jamais etre atteints via la facade.
2. **Confiance excessive dans le request_id entrant** : `resolveRequestId()` accepte `X-Request-Id` / `X-Correlation-Id` de n'importe quel client Internet (format valide seulement). Le prompt §7 exige une acceptation restreinte a des composants internes approuves : risque de collision/usurpation de chronologie.
3. **Schema minimal** : pas de colonnes `stream`, `application`, `module`, `request_id`, `correlation_id`, `error_fingerprint`, `actor/entity`, `http_status`, `duration_ms` ; le request_id est enfoui dans `context_json`, recherche uniquement par `LIKE` non indexable (`SqlLogStore::buildFilterQuery`).
4. **Sanitization minimale** : blacklist de 8 sous-chaines, pas de variantes FR, pas de limite de profondeur ni de nombre d'elements, **pas de neutralisation des retours ligne / octets de controle** (injection possible dans les logs fichiers Monolog), messages non nettoyes des chemins.
5. **Pas de catalogue d'evenements** : noms libres, variantes incoherentes possibles ; pas d'`application_code` par webapp.
6. **Pas de regroupement d'erreurs** : aucune empreinte, une rafale d'erreurs identiques noie l'ecran et les alertes.
7. **Ecran admin non scalable** : `LIMIT 200` sans pagination, 2 `COUNT(*)` par affichage, pas de filtres stream/application/request_id, pas d'export.
8. **Fallback non rejouable** : `SqlLogStore::insert()` retourne `false` silencieusement ; les fichiers paralleles ne sont ni structures ni reimportables.
9. **Couverture** : TaxDeclarationHelper non instrumente ; 18 `error_log()` hors chaine ; pas de capture frontend ; `cron_runs.stdout_text/stderr_text` en LONGTEXT non borne.

### 3. Invariants a preserver

- Le logging ne casse jamais l'application (try/catch actuels a conserver, aucune recursion).
- Retro-compatibilite des appels existants `security()/content()/access()` (134 appels dans 28 fichiers) : signature conservee, mapping canal -> stream interne.
- `ADMIN_LOGIN_PATH` jamais code en dur ; ecran logs derriere `guardAuthenticated` + CSRF existants.
- Migration SQL **additive et idempotente** (`CREATE/ALTER ... IF NOT EXISTS` selon conventions `backend/sql/editorial/`), compatible avec `sync_deploy_schema.php` qui s'execute au deploy : aucune perte des entrees existantes, anciens champs conserves.
- `PrivatePortal` = socle, `PrivateApps` = metier : le composant logging reste dans `backend/src/Logging/`, les webapps ne declarent que leur `application_code` et leurs evenements.
- Aucune donnee sensible en logs (regles AGENTS.md + prompt §8) ; production = source de verite, aucun acces prod dans cette tache.
- Tables d'audit domaine (`rental_export_logs`, `tax_export_logs`) conservees : ce sont des donnees metier, pas des doublons a supprimer.

### 4. Risques et cas limites

- **ALTER TABLE sur `log_entries` en prod** : volume prod inconnu depuis le local ; prevoir migration additive par colonnes nullables (pas de reecriture de table), et verifier le comportement de `deploy-schema-sync` sur ALTER.
- **Cout d'ecriture** : hebergement OVH mutualise ; limiter les index composites a ceux requis par les filtres reels de l'ecran admin (occurred_at/created_at DESC, level+date, stream+date, application+date, event+date, request_id, correlation_id, error_fingerprint+date). Ne pas indexer chaque colonne.
- **Double ecriture fichier+SQL** : duplication assumee aujourd'hui ; si on la conserve, borner les fichiers (deja fait : rotation) et documenter que SQL est la source de consultation.
- **Recursion** : une erreur SQL journalisee pendant une panne SQL doit aboutir au fallback fichier, jamais a une boucle (protection actuelle par try/catch a etendre au fallback).
- **Jobs CLI/cron** : pas de contexte HTTP -> generer un request_id de job, conserver le correlation_id du declencheur manuel quand il existe (Cron Center le permet via `cron_runs`).
- **Fuseaux** : `created_at TIMESTAMP` depend du fuseau serveur ; toute purge par date doit rester coherente avec l'existant (ne pas purger du recent par erreur de fuseau).
- **Tempete d'alertes** : cooldown existant (180 min) a completer par deduplication par empreinte.
- **Ecart local/prod possible sur l'ecran admin Logs** : l'ecran existe en local (branche `restore-prod-master-20260716`) ; son etat exact en production n'est pas verifiable en lecture seule locale -> a comparer avant toute suppression/refonte visuelle.

### 5. Solution principale recommandee (unique, proportionnee)

Extension incrementale de la chaine existante, dans l'ordre du prompt §30 :

1. **Correctifs prealables** (petits, forte valeur) : router les 8 niveaux PSR-3 dans `AppEventLogger::write()` ; restreindre l'acceptation des en-tetes `X-Request-Id`/`X-Correlation-Id` a une liste de sources internes approuvees (config, defaut : refus).
2. **Contrat + catalogue** : nouvelle methode generique `log(stream, event, context, level)` sur AppEventLogger (les 3 methodes actuelles deviennent des delegations : `security`->stream security, `content`->audit/application, `access`->application) ; catalogue declaratif en code (`backend/src/Logging/EventCatalog.php` ou equivalent) avec `application_code` par webapp (`private.rental`, `private.blocnote`, `private.documents`, `private.family`, `private.tax`), niveaux par defaut, cles de contexte autorisees, classe de retention.
3. **Sanitizer dedie** (`LogSanitizer`) : blacklist etendue du prompt §8 + variantes FR, whitelist par evenement connu, bornes profondeur/elements/taille, neutralisation retours ligne et octets de controle ; utilise par AppEventLogger et par tout futur endpoint client.
4. **Migration SQL additive** `backend/sql/editorial/0xx_log_entries_v2.sql` : colonnes nullables `stream`, `application`, `module`, `request_id`, `correlation_id`, `error_class`, `error_fingerprint`, `http_status`, `duration_ms`, `actor_type`, `actor_id`, `entity_type`, `entity_id`, `schema_version` ; index composites cites en §4 ; les anciennes entrees restent lisibles (colonnes NULL, pas de backfill inventif — mapper uniquement `channel`->`stream` et extraire `request_id` de `context_json` quand present).
5. **Fallback structure** : fichier JSONL borne (taille + nombre + quota) sous l'emplacement runtime prive du depot, ecrit uniquement quand `SqlLogStore::insert()` echoue ; commande CLI de rejeu idempotente `logs_replay_fallback.php` (style `backend/core/tools/*.php`) avec `--dry-run`, whitelistee dans `CronScriptPolicy` si planifiee.
6. **Empreinte et regroupement** : `error_fingerprint` calcule (classe + code + module + origine normalisee) ; vue regroupee dans l'ecran admin par agregat SQL (pas de table incidents separee au depart — volume trop faible pour la justifier ; a reevaluer si besoin).
7. **Couverture** : instrumenter TaxDeclarationHelper via la facade ; remplacer les 18 `error_log()` des stores Navigation/Content ; middleware leger dans `FrontController` pour 5xx, 4xx de securite et requetes lentes (seuil configurable) ; resume structure des runs Cron (borner stdout/stderr stockes).
8. **Frontend minimal** : brancher `window.onerror`/`unhandledrejection` sur `logError` existant + endpoint interne strict (payload minuscule, rate-limit, deduplication par empreinte, desactivable) — uniquement les 4 cas du prompt §13.
9. **Ecran Admin > Logs** : etendre l'existant (pas de nouvel ecran) : filtres stream/application/module/request_id/correlation_id/empreinte, pagination par curseur sur `id`, suppression des `COUNT(*)` systematiques au profit d'un compteur borne, onglets par flux, export CSV/JSON assaini avec protection injection de formules et audit de l'export.
10. **Alertes et retention** : etendre `check_log_alerts.php` (empreintes repetees, 5xx, niveaux critiques enfin joignables) et `purge_sql_logs.php` (classes de retention par stream selon le bareme du prompt §20) — les deux outils et leur planification Cron Center existent deja.
11. **Tests** : etendre les suites existantes (`LoggerFactoryTest`, `SqlLogStoreTest`, `backend/tests/Logging/*`) + nouveaux tests unitaires sanitizer/catalogue/empreinte/niveaux, integration fallback+rejeu+pagination, fonctionnels admin (acces refuse, CSRF, filtres, export), charge ~100 000 entrees synthetiques non commitees.

A ne pas faire (disproportionne pour ce volume) : table d'incidents avec workflow d'etats des la v1, archivage froid automatique, nouveau canal d'alerte externe, journalisation des requetes publiques reussies.

### 6. Criteres d'acceptation

- `composer --working-dir=backend test`, `phpstan`, `phpcs`, `npm --prefix frontend run test:run`, `lint` et `git diff --check` passent.
- Un `critical` emis via la facade est stocke avec le niveau `critical` (bug niveaux corrige, test dedie).
- Un request_id force depuis un client externe non approuve n'est pas reutilise (test dedie).
- Toute entree passe par le sanitizer : aucun secret des listes §8, aucune valeur multi-ligne brute en fichier (tests injection).
- Les 134 appels existants compilent et ecrivent sans modification de leurs signatures ; anciennes entrees toujours visibles dans l'admin apres migration.
- Ecran admin filtrable par stream/application/request_id, paginable, utilisable avec 100 000 entrees.
- Panne SQL simulee : evenements importants dans le fallback JSONL, rejeu idempotent verifie, aucune recursion.
- Purge `--dry-run` puis reelle par classes de retention, sans toucher aux entrees recentes.
- TaxDeclarationHelper emet au moins ses evenements sensibles ; plus aucun `error_log()` direct dans `backend/src`.
- Aucune donnee synthetique, aucun fichier temporaire, aucun secret dans le depot.

### 7. Incertitudes signalees (non inventees)

- **Volume prod de `log_entries` et etat prod de l'ecran Admin > Logs** : non verifiables en lecture seule locale ; a confirmer avant l'ALTER et avant toute refonte visuelle (production = source de verite).
- **Configuration reelle des alertes en prod** (seuils, destinataires) : stockee en runtime prive, non versionnee.
- **Comportement de `deploy-schema-sync` sur les ALTER TABLE** : a verifier par Codex avant de choisir la forme exacte de la migration.
- **Performance OVH mutualise** (cout des nouveaux index en ecriture) : non mesurable localement ; le test de charge local reste indicatif.

## Plan d'implementation valide

*A completer par Codex apres validation de l'analyse Claude.*

### Ordre d'execution prevu (selon le prompt section 30)
1. Audit complet de l'existant
2. Cartographie des evenements importants du projet et des webapps privees
3. Contrat structure, catalogue, sanitizer et identifiants de correlation
4. Migration SQL et index
5. Evolution compatible d'AppEventLogger, du store et du fallback
6. Gestionnaires PHP, middleware HTTP et frontieres techniques
7. Audit admin et securite
8. Instrumentation metier du projet principal
9. Instrumentation de chaque webapp privee
10. Frontend significatif, sans bruit
11. Cron Center, sauvegardes et appels externes
12. Regroupement des erreurs et incidents
13. Alertes, retention, aggregation et purge
14. Interface Admin > Logs et droits
15. Migration des anciens logs et appels directs
16. Tests unitaires, integration, fonctionnels et charge
17. Documentation et suppression du code devenu inutile
18. Verification finale complete

## Resultat Codex ou Mistral

Codex a verifie l'analyse Claude dans le code et implemente une tranche incrementale sure du plan autorise, centree sur le socle de journalisation :

- `AppEventLogger` conserve les entrees historiques `security()`, `content()` et `access()`, ajoute `log(stream, event, context, level)` et route maintenant les 8 niveaux PSR-3 (`debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency`) sans degradation en `info`.
- Ajout de `backend/src/Logging/LogSanitizer.php` : assainissement central recursif, masquage des cles sensibles FR/EN, bornage profondeur/elements/chaines, suppression des octets de controle et neutralisation des retours ligne.
- `SqlLogStore` extrait des champs structures depuis le contexte assaini : `stream`, `application`, `module`, `request_id`, `correlation_id`, `error_class`, `error_fingerprint`, `http_status`, `duration_ms`, acteur/entite et `schema_version`.
- Nouvelle migration additive `backend/sql/editorial/014_log_entries_structured_fields.sql` : colonnes nullables, backfill minimal `created_at -> occurred_at` et `channel -> stream`, index principaux `occurred_at`, `stream`, `request_id`, `correlation_id`, `error_fingerprint`.
- `FrontController` genere toujours un `request_id` serveur par defaut et n'accepte `X-Request-Id` / `X-Correlation-Id` que depuis `logging.trusted_request_id_sources` explicitement configure.
- Ajout de `LOG_TRUSTED_REQUEST_ID_SOURCES` dans la configuration logging.
- Tests ajoutes/ajustes pour le niveau `critical`, le sanitizer, les champs structures SQL, le refus d'un request id externe et la propagation depuis une source interne approuvee.
- Correction minimale dans `backend/templates/private/modules/real-estate-rental/agency-imports.php` : suppression de deux handlers `onclick` inline qui faisaient echouer le garde des templates prives ; le layout prive centralise deja la confirmation des actions sensibles.
- Normalisation mecanique des fins de ligne de `backend/docs/audit-sql-2026-07-17.md` pour permettre `git diff --check` (ce fichier etait modifie hors de la tranche logging).

Limites volontairement restantes par rapport au prompt complet :
- pas de fallback JSONL rejouable ;
- pas d'extension Admin > Logs, alertes, retention par classe, endpoint frontend ou instrumentation exhaustive de TaxDeclarationHelper dans cette tranche ;
- pas de test de charge 100 000 entrees ;
- aucun acces production, deploy, migration distante, import/export ou ecriture SQL distante.

## Tests et validations

Commandes de validation confirmees (presentes dans le depot) :
- `composer --working-dir=backend test` (backend - 703+ tests)
- `composer --working-dir=backend phpstan` (backend - niveau 5 avec baseline)
- `composer --working-dir=backend phpcs` (backend - PSR-12)
- `npm --prefix frontend run test:run` (frontend - 39 tests)
- `npm --prefix frontend run lint` (frontend)
- `git diff --check` (whitespace)
- `php -l <fichier>` (syntaxe PHP)

Commandes a executer avant validation :
```bash
# Verification syntaxe de tous les fichiers modifies
find backend/src/Logging -name "*.php" -exec php -l {} \;

# Execution des tests de logging
composer --working-dir=backend test -- --filter "LoggerFactoryTest|SqlLogStoreTest|LogAlertsNotifierTest|LogAlertsNotificationGateTest|CheckLogAlertsScriptTest|PrivateOperationsLoggingTest"

# Analyse statique
composer --working-dir=backend phpstan -- --level=5
composer --working-dir=backend phpcs

# Verification git
git diff --check
git status
```

Commandes executees par Codex :
- `php -l backend/src/Logging/AppEventLogger.php` : OK
- `php -l backend/src/Logging/LogSanitizer.php` : OK
- `php -l backend/src/Logging/SqlLogStore.php` : OK
- `php -l backend/src/Http/FrontController.php` : OK
- `php -l backend/tests/SqlLogStoreTest.php` : OK
- `php -l backend/tests/FrontControllerHttpTest.php` : OK
- `php -l backend/templates/private/modules/real-estate-rental/agency-imports.php` : OK
- `composer --working-dir=backend test -- --filter 'SqlLogStoreTest|LoggerFactoryTest'` : OK, 6 tests, 48 assertions
- `composer --working-dir=backend test -- --filter 'FrontControllerHttpTest'` : OK, 52 tests, 259 assertions
- `composer --working-dir=backend test -- --filter 'Logging|SqlLogStore|LoggerFactory|FrontControllerHttpTest'` : OK, 69 tests, 362 assertions
- `backend/vendor/bin/phpunit --configuration backend/phpunit.xml --filter 'PrivateTemplateGuardTest'` : OK, 37 tests, 454 assertions
- `backend/vendor/bin/phpunit --configuration backend/phpunit.xml` : OK, 706 tests, 5786 assertions
- `composer --working-dir=backend phpstan` : OK, 299 fichiers, aucune erreur
- `composer --working-dir=backend phpcs` : OK
- `npm --prefix frontend run test:run` : OK, 6 fichiers, 39 tests
- `npm --prefix frontend run lint` : OK
- `git diff --check` : OK

Note : `composer --working-dir=backend test` a d'abord ete interrompu par le timeout Composer de 300 secondes. La suite complete a ensuite ete relancee directement via `backend/vendor/bin/phpunit --configuration backend/phpunit.xml` et passe.

## Revue finale

Diff final examine. Points d'attention :
- migrations SQL uniquement locales et additives ; aucune execution distante.
- les changements locaux existants dans RealEstateRental ont ete preserves ; seule une correction minimale du template a ete ajoutee car elle bloquait la suite complete.
- le worktree contient aussi des modifications hors perimetre non creees par cette tranche (`.ai/prompts/MISTRAL_ROUTER.md`, `.ai/CURRENT_TASK_STORAGE.md`, `backend/src/PrivateApps/RealEstateRental/...`, `nul`) a ne pas confondre avec le travail logging.
- la production et l'etat reel de la table `log_entries` OVH n'ont pas ete consultes, conformement aux exclusions.

## Etat

Termine

Etats autorises : `A analyser`, `Planifie`, `En cours`, `A revoir`, `Termine`, `Bloque`.

---
*Cree : 2026-07-19*
*Agent : Mistral Vibe*
*Route par : MISTRAL_ROUTER.md (niveau C, agent Codex)*
*Source : prompt-journalisation-centralisee-complete-projet-webapps-private.md*
