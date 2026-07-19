# Prompt — Journalisation centralisée, complète et exploitable de tout le projet et des webapps privées

Tu es le responsable technique senior de cette intervention. Travaille directement dans le dépôt actuellement ouvert et réalise une refonte complète, cohérente et prête pour la production de la journalisation, de l’audit, de l’observabilité et des alertes de l’ensemble du projet **Les Caramagnols**, y compris toutes les webapps situées dans le périmètre `private`.

Le résultat doit être consultable depuis la rubrique **Logs** de l’administration accessible par la route admin configurée. En production, cette administration correspond actuellement à :

```text
https://www.lescaramagnols.com/espace-admin-7k9m2p
```

Cette URL sert uniquement de cible fonctionnelle de vérification. **Ne code jamais `espace-admin-7k9m2p` en dur.** Réutilise impérativement `ADMIN_LOGIN_PATH`, le routeur, le contrôleur admin, les autorisations, la session, la réauthentification et les protections CSRF existantes.

L’objectif n’est pas d’ajouter quelques `error_log()` ni de créer un second outil de logs. Il faut analyser, consolider et compléter la couche déjà prévue dans le projet autour de :

```text
backend/src/Logging/LoggerFactory.php
backend/src/Logging/AppEventLogger.php
backend/core/tools/check_log_alerts.php
backend/sql/editorial/003_log_entries.sql
backend/tests/LoggerFactoryTest.php
backend/tests/SqlLogStoreTest.php
backend/tests/Logging/LogAlertsNotifierTest.php
```

Adapte cette liste aux fichiers réellement présents. Conserve `AppEventLogger` comme point d’entrée métier central ou fais-le évoluer proprement. Ne laisse pas deux systèmes concurrents, deux tables faisant le même travail ou des webapps privées avec leur propre format incompatible.

Tu dois faire les meilleurs choix directement après analyse de l’existant. N’attends pas que je choisisse entre plusieurs architectures lorsque le dépôt permet de trancher. Ne t’arrête pas à un audit ou à des recommandations : implémente, migre, teste, documente et vérifie la solution complète dans le périmètre autorisé.

## 1. Analyse préalable obligatoire

Avant toute modification :

1. Lis intégralement le `AGENTS.md` racine, puis chaque `AGENTS.md` plus proche du code concerné.
2. Lis les README et documents actifs portant sur l’architecture, le backend, l’administration, la sécurité, les logs, les alertes, Cron Center, les sauvegardes, le déploiement et les webapps privées.
3. Inspecte le schéma et les migrations de `log_entries`, ainsi que tous les stores, interfaces, factories, notifiers, settings managers, contrôleurs, templates, scripts CLI et tests associés.
4. Localise la route et l’écran Admin > Logs existants, même s’ils sont incomplets.
5. Localise toutes les webapps situées dans `private`, leurs points d’entrée, leurs routes, services, tâches planifiées, appels externes, imports/exports et erreurs actuelles.
6. Cartographie tous les mécanismes de journalisation existants dans le dépôt :
   - `AppEventLogger` ;
   - `LoggerFactory` ;
   - store SQL ;
   - fichiers de logs ;
   - `error_log`, `trigger_error`, exceptions capturées ;
   - logs de sécurité et authentification ;
   - sorties des tâches Cron Center ;
   - alertes ;
   - journaux de sauvegarde et de déploiement ;
   - `console.log`, `console.error` et gestion des erreurs frontend ;
   - logs propres aux webapps privées ;
   - erreurs silencieusement absorbées ou affichées directement à l’utilisateur.
7. Identifie la source de vérité réelle, les conventions du dépôt et les modifications locales déjà présentes. Préserve tout changement utilisateur sans rapport avec la mission.
8. Mesure l’état actuel : nombre de tables, volume approximatif, index, niveaux, événements, rétention, filtres, performance de la page admin et couverture fonctionnelle.
9. Produis un bref état des lieux factuel, puis commence immédiatement l’implémentation.

Interdictions :

- ne déploie pas en production ;
- n’écris pas dans la base de production ;
- ne modifie pas la valeur de `ADMIN_LOGIN_PATH` ;
- ne supprime aucun journal existant avant sauvegarde et migration vérifiée ;
- ne stocke jamais de mot de passe, token, cookie, session, code TOTP, clé API, en-tête `Authorization`, secret SQL ou contenu intégral de formulaire ;
- ne stocke jamais le contenu d’une pièce d’identité, d’un bail, d’une facture, d’un message privé ou d’un fichier importé ;
- ne stocke pas de stack trace complète ni de payload complet en production ;
- ne journalise pas chaque événement de rendu ou chaque boucle interne ;
- ne laisse aucun `var_dump`, `dump`, `die`, `print_r` ou `console.log` de débogage dans le code final ;
- ne crée pas une dépendance obligatoire envers un service SaaS externe ;
- ne transforme pas l’application en plateforme de monitoring disproportionnée pour son volume réel.

## 2. Résultat architectural attendu

Tout le projet doit utiliser une seule chaîne de journalisation structurée :

```text
Émetteur
  -> AppEventLogger / façade centrale
  -> normalisation et assainissement
  -> enrichissement du contexte
  -> stockage SQL principal
  -> secours local borné en cas de panne SQL
  -> agrégation, alertes et purge planifiée
  -> consultation dans Admin > Logs
```

Elle couvre :

- site public ;
- administration ;
- contenu éditorial et blog ;
- authentification, sessions et sécurité ;
- navigation et recherche ;
- tâches Cron Center ;
- sauvegardes et restaurations ;
- envoi d’e-mails et notifications ;
- API et services externes ;
- imports, exports, uploads et conversions ;
- base SQL et repositories ;
- frontend lorsque l’erreur est significative ;
- toutes les webapps privées ;
- webapp Locations immobilières ;
- futures webapps ajoutées dans `private`.

## 3. Principes de journalisation

Respecte la règle du projet : **journaliser peu mais utile**. Une journalisation complète signifie que chaque action ou échec important est traçable, pas que chaque ligne de code produit une entrée.

### À journaliser systématiquement

- erreurs et exceptions non prévues ;
- erreurs métier qui empêchent une opération ;
- échecs d’accès à la base ou au stockage ;
- échecs d’appel à un service externe ;
- opérations anormalement lentes ;
- échecs de tâches planifiées ;
- échecs de sauvegarde, restauration, import et export ;
- événements de sécurité ;
- changements administratifs sensibles ;
- création, modification, archivage, suppression et restauration d’une donnée importante ;
- changement de rôle, droit, configuration ou secret, sans enregistrer la valeur secrète ;
- opérations sensibles des webapps privées ;
- actions nécessitant une piste d’audit.

### À ne pas journaliser individuellement

- chaque page publique servie avec succès, sauf mode temporaire de diagnostic ;
- chaque lecture SQL normale ;
- chaque clic ou mouvement frontend ;
- chaque élément d’une boucle ;
- les valeurs complètes des formulaires ;
- les réponses complètes des API ;
- les fichiers et leur contenu ;
- les événements récurrents sans valeur opérationnelle.

Pour les requêtes HTTP :

- journaliser les erreurs `4xx` significatives et les `5xx` ;
- journaliser les requêtes lentes selon un seuil configurable ;
- journaliser les opérations admin et privées qui modifient l’état ;
- agréger les requêtes publiques normales sous forme de métriques plutôt que de créer une ligne SQL par visite ;
- ne pas journaliser les assets statiques réussis.

## 4. Contrat d’événement structuré unique

Définis et documente un contrat versionné. Adapte les noms exacts au code existant, mais chaque événement doit pouvoir porter :

```text
id
occurred_at UTC avec précision suffisante
level
stream
channel
event_name
schema_version
message courte et sûre
environment
application
module
release ou build_id
request_id
correlation_id
parent_event_id nullable
actor_type nullable
actor_id nullable
entity_type nullable
entity_id nullable
route_name nullable
http_method nullable
http_status nullable
duration_ms nullable
error_class nullable
error_code nullable
error_fingerprint nullable
context_json assaini et borné
created_at
```

Les champs facultatifs restent `NULL` lorsqu’ils n’ont pas de sens. Ne remplis pas le contexte avec des valeurs artificielles.

### Niveaux

Normalise les niveaux selon la sémantique PSR-3 si elle est compatible avec l’existant :

```text
debug
info
notice
warning
error
critical
alert
emergency
```

En production, `debug` est désactivé par défaut. Un niveau ne doit pas être choisi pour sa couleur mais selon l’impact réel.

### Flux

Sépare logiquement, sans multiplier inutilement les tables :

```text
application
error
security
audit
cron
external
client
```

Si le schéma existant peut porter ces flux dans `log_entries`, étends-le. Ne crée une table séparée que si l’intégrité transactionnelle ou la rétention l’exige réellement.

### Nommage des événements

Utilise des noms stables, en minuscules, hiérarchiques et orientés résultat :

```text
http.request.failed
http.request.slow
security.auth.login_succeeded
security.auth.login_failed
security.csrf.rejected
security.rate_limit.exceeded
admin.settings.updated
admin.user.permissions_updated
editorial.page.saved
editorial.article.published
cron.job.started
cron.job.completed
cron.job.failed
backup.production.completed
backup.production.failed
external.email.sent
external.email.failed
private.locations.document.imported
private.locations.document.import_failed
private.locations.export.completed
private.locations.export.failed
```

Ne concatène pas d’identifiants ou de données variables dans `event_name`. Place-les dans les champs structurés.

## 5. Catalogue central des événements

Créer un catalogue central en code ou configuration qui définit pour chaque événement connu :

- nom ;
- niveau par défaut ;
- flux ;
- application/module ;
- clés de contexte autorisées ;
- données obligatoirement masquées ;
- classe de rétention ;
- possibilité d’alerte ;
- caractère auditable ou purement opérationnel.

Le catalogue doit empêcher les variantes incohérentes comme :

```text
login_failed
login.fail
failed_login
admin_login_error
```

Prévois une méthode sûre permettant à une future webapp privée de déclarer son `application_code`, son module et ses événements sans recréer une pile de logs.

## 6. AppEventLogger comme façade centrale

Fais évoluer `AppEventLogger` en conservant autant que possible sa compatibilité avec les appels existants.

Le point d’entrée central doit :

1. valider ou normaliser le niveau et le nom de l’événement ;
2. enrichir avec environnement, application, module, release et identifiants de corrélation ;
3. appliquer l’assainissement central ;
4. borner taille, profondeur JSON et longueur des chaînes ;
5. calculer l’empreinte d’erreur lorsque pertinent ;
6. écrire dans le store SQL existant ;
7. utiliser un secours local borné si le SQL est indisponible ;
8. éviter toute récursion si la journalisation échoue ;
9. ne jamais interrompre un parcours utilisateur pour un log opérationnel non critique ;
10. permettre qu’un audit sensible soit écrit dans la même transaction métier lorsque cela est nécessaire.

Pour les actions de sécurité, de droits et de configuration sensibles, privilégie une écriture d’audit transactionnelle : si la modification ne peut pas être auditée, elle ne doit pas être confirmée comme réussie.

Pour les logs opérationnels ordinaires, adopte un comportement `best effort` avec secours local. Une panne du logger ne doit pas provoquer une boucle d’erreurs ni masquer l’erreur métier d’origine.

## 7. Identifiants de requête et corrélation

Ajoute un identifiant `request_id` à chaque requête applicative dynamique et un `correlation_id` pour relier plusieurs opérations.

Règles :

- générer côté serveur un identifiant imprévisible et valide ;
- ne pas faire confiance aveuglément à un identifiant fourni par Internet ;
- accepter un identifiant entrant uniquement depuis un composant interne explicitement approuvé et après validation stricte ;
- exposer éventuellement `X-Request-ID` dans la réponse si cela respecte les conventions HTTP existantes ;
- propager le `correlation_id` aux services, repositories, appels internes et jobs dérivés ;
- transmettre le contexte aux webapps privées par la façade commune, pas via une variable globale incontrôlée ;
- permettre de retrouver dans l’admin toute la chronologie d’une opération à partir d’un identifiant ;
- générer un nouveau `request_id` pour un job Cron tout en conservant le `correlation_id` de son déclencheur manuel lorsqu’il existe.

Exemple : import d’un document dans Locations immobilières :

```text
requête admin
-> validation du fichier
-> stockage
-> écriture SQL
-> génération d’aperçu
-> tâche différée
```

Toutes les étapes doivent être consultables dans une même chronologie sans enregistrer le contenu du document.

## 8. Assainissement et protection des données

Créer un composant central de redaction/sanitization utilisé avant toute écriture, y compris depuis les webapps privées.

Masquer ou supprimer récursivement au minimum les clés et variantes relatives à :

```text
password
passwd
secret
token
access_token
refresh_token
authorization
cookie
session
csrf
totp
api_key
private_key
database_url
dsn
iban
card_number
```

Ajouter les variantes françaises et les clés propres au dépôt découvertes lors de l’audit.

Règles :

- liste blanche de clés de contexte privilégiée pour les événements connus ;
- liste noire récursive de sécurité comme seconde barrière ;
- limite de profondeur JSON ;
- limite de nombre d’éléments ;
- limite de taille par valeur et par événement ;
- suppression des octets de contrôle et neutralisation des retours à la ligne pour empêcher l’injection de logs ;
- messages d’erreur nettoyés des chemins, chaînes de connexion, requêtes complètes et données personnelles ;
- adresses e-mail masquées ou pseudonymisées lorsque l’identité complète n’est pas indispensable ;
- adresse IP traitée selon le besoin de sécurité réel, avec accès restreint et rétention courte si elle est conservée ;
- user-agent réduit à une information utile, sans stockage illimité de chaînes arbitraires ;
- aucun corps HTTP complet ;
- aucun document, pièce jointe ou contenu éditorial complet ;
- aucun SQL avec valeurs injectées ; utiliser un nom d’opération ou de repository ;
- aucun avant/après complet : calculer un diff de champs autorisés.

La page Admin > Logs ne doit jamais contourner ce nettoyage, même pour un super-administrateur.

## 9. Gestion des erreurs PHP

Analyse et consolide les gestionnaires existants afin de couvrir :

- exceptions non interceptées ;
- erreurs PHP transformables ;
- erreurs fatales détectées à l’arrêt ;
- erreurs de routage et contrôleur ;
- erreurs de template ;
- erreurs repository/SQL ;
- erreurs de fichiers et stockage ;
- erreurs de conversion ou traitement asynchrone.

Pour chaque erreur significative, enregistrer de façon sûre :

```text
classe d’exception
code applicatif stable
message assaini
module
opération
route
request_id
correlation_id
origine normalisée si autorisée
empreinte de regroupement
durée si connue
statut HTTP final
```

Ne stocke pas la stack trace complète. Une empreinte doit regrouper les erreurs identiques à partir de valeurs stables telles que classe, code, module et origine normalisée, sans inclure de données personnelles variables.

Assure-toi que le gestionnaire d’erreur fonctionne même si la base des logs est indisponible et qu’il n’entre jamais en récursion.

## 10. Couverture HTTP

Créer ou compléter un middleware/point transversal compatible avec le front-controller existant.

Il doit mesurer :

- route ;
- méthode ;
- statut ;
- durée ;
- module ;
- environnement ;
- request/correlation ID.

Politique :

- mutations admin et privées : événement d’audit ou métier adapté, pas seulement une ligne HTTP générique ;
- `5xx` : événement `error` ;
- `4xx` de sécurité : flux `security` ;
- `404` public : agréger et limiter pour éviter le bruit des robots ;
- requête lente : `warning` selon seuil distinct public/admin/private ;
- requête publique réussie : métrique agrégée, pas une ligne par requête ;
- assets statiques : ne pas journaliser les succès ;
- endpoints de santé : ne pas polluer les logs.

Le seuil de lenteur doit être centralisé et configurable avec des valeurs par défaut raisonnables déduites de l’application réelle.

## 11. Audit des actions administratives

Créer un flux d’audit append-only au niveau applicatif pour les actions sensibles :

- connexion, déconnexion, échec de connexion et réauthentification ;
- activation/désactivation de 2FA ;
- modification d’un utilisateur, rôle ou droit ;
- modification des paramètres ;
- modification de la configuration de sauvegarde ;
- modification de Cron Center ;
- test manuel d’un job ;
- création, publication, dépublication, archivage et suppression d’un contenu ;
- restauration de sauvegarde ;
- export de données ;
- téléchargement de données sensibles ;
- purge de logs ;
- modification d’une règle d’alerte ;
- action sensible dans une webapp privée.

Chaque audit contient :

```text
qui
quand
action
résultat
entité et identifiant
champs autorisés modifiés
request_id
correlation_id
motif si l’interface le demande
```

Ne stocke jamais la valeur d’un secret avant ou après. Pour un mot de passe ou une clé, journalise uniquement que la valeur a été remplacée.

Interdire la modification manuelle d’une entrée d’audit. La purge passe uniquement par une tâche contrôlée, la politique de rétention et une autorisation dédiée.

## 12. Couverture des webapps privées

Inspecte chaque webapp du périmètre `private` et ajoute un `application_code` stable, par exemple :

```text
private.locations
private.family
private.<nom-reel>
```

Ne suppose pas leurs noms : utilise ceux découverts dans le dépôt.

Pour chaque webapp, instrumente au minimum :

- accès autorisé et accès refusé importants ;
- création, modification, archivage, restauration et suppression d’entités ;
- import, export et téléchargement ;
- tâches automatiques ;
- e-mails ou notifications ;
- erreurs métier ;
- appels externes ;
- erreurs frontend significatives ;
- actions sensibles propres au domaine.

Pour Locations immobilières, couvrir selon l’existant :

- biens, logements, propriétaires, locataires, garants et baux ;
- loyers, paiements, impayés et dépôts de garantie ;
- charges, régularisations, taxes, assurances et copropriété ;
- documents, imports, classification, archivage, exports et sauvegardes ;
- aide à la déclaration fiscale ;
- changements de statut importants ;
- génération et envoi de quittances ou demandes de paiement.

Ne journalise pas le contenu d’un bail, les coordonnées complètes, les revenus, les montants détaillés inutiles ou les pièces importées. Utilise les identifiants métier et un contexte minimal.

Toutes les webapps doivent appeler la façade centrale. Supprime ou adapte leurs loggers isolés après migration vérifiée.

## 13. Frontend et erreurs navigateur

Supprime les traces de débogage laissées dans le frontend final. Si le dépôt ne possède pas encore de capture des erreurs client, ajoute un mécanisme minimal et sécurisé uniquement pour :

- erreur JavaScript non gérée ;
- rejet de Promise non géré ;
- échec important d’une action métier ;
- erreur de chargement d’un module applicatif nécessaire.

Créer un endpoint interne dédié, protégé et limité :

- taille de payload très faible ;
- schéma strict ;
- rate limiting ;
- origine et CSRF/auth selon le contexte ;
- déduplication par empreinte ;
- aucun DOM complet ;
- aucune valeur de formulaire ;
- aucune URL contenant des paramètres sensibles ;
- message et stack client fortement normalisés et bornés ;
- release/build et route logique ;
- possibilité de désactiver la collecte publique si elle génère trop de bruit.

Ne transforme pas cet endpoint en collecteur public générique et ne fais pas confiance au niveau ou au nom d’événement fourni par le navigateur.

## 14. Repositories, SQL et stockage

Instrumente les erreurs aux frontières pertinentes, sans doubler la même erreur à chaque couche.

Règles :

- une erreur doit être journalisée une seule fois au niveau qui possède le contexte nécessaire ;
- les couches inférieures peuvent enrichir ou remonter une exception typée ;
- ne jamais journaliser la requête SQL complète avec ses valeurs ;
- utiliser un identifiant d’opération stable comme `page.save`, `rent.payment.create` ou `document.store` ;
- journaliser durée, résultat, type d’erreur et repository ;
- ajouter une détection des opérations SQL lentes uniquement si elle peut être réalisée proprement et sans bruit ;
- ne pas journaliser toutes les requêtes réussies ;
- ne pas masquer une exception après l’avoir journalisée ;
- préserver les transactions existantes.

## 15. Appels externes, e-mails et notifications

Centralise le suivi des appels externes :

```text
service
opération
résultat
statut HTTP ou code fournisseur
durée
nombre de tentatives
request_id/correlation_id
error_fingerprint
```

Ne stocke jamais :

- token ;
- en-têtes d’authentification ;
- corps complet ;
- contenu d’e-mail ;
- adresse complète du destinataire sauf nécessité explicitement justifiée.

Pour un e-mail, journaliser : modèle, type de destinataire pseudonymisé, fournisseur, résultat, identifiant de message fournisseur s’il est non sensible et durée.

## 16. Cron Center, sauvegardes et exploitation

Réutilise Cron Center comme point d’entrée unique des tâches planifiées.

Les événements existants `cron.*` doivent rester filtrables dans Admin > Logs. Complète la couverture :

- démarrage ;
- réussite ;
- échec ;
- durée ;
- code de sortie ;
- nombre d’éléments traités ;
- tentative ;
- verrou déjà détenu ;
- dépassement de durée ;
- prochain lancement calculé lorsque pertinent.

Ne recopie pas stdout/stderr brut dans `log_entries`. Cron Center peut conserver son historique d’exécution borné, mais `AppEventLogger` doit recevoir un résumé structuré et assaini.

Instrumente aussi :

- backup production ;
- dump SQL ;
- copie de fichiers ;
- manifeste ;
- purge des sauvegardes ;
- test de restauration ;
- alertes de logs ;
- purge des logs.

Une purge de logs ne doit créer qu’un événement synthétique après réussite, sans se journaliser récursivement pour chaque lot supprimé.

## 17. Stockage SQL, index et performance

Fais évoluer la migration existante plutôt que de la réécrire. Crée une nouvelle migration compatible et idempotente selon les conventions du projet.

Ajoute les colonnes réellement nécessaires et les index couvrant au minimum :

```text
occurred_at DESC
level + occurred_at
stream + occurred_at
application + occurred_at
module + occurred_at
event_name + occurred_at
request_id
correlation_id
error_fingerprint + occurred_at
actor_type + actor_id + occurred_at
entity_type + entity_id + occurred_at
```

N’ajoute pas un index pour chaque champ sans vérifier les requêtes réelles et le coût d’écriture.

Le contexte JSON ne doit pas devenir le moteur de recherche principal. Les champs fréquemment filtrés doivent être normalisés en colonnes.

Prévois :

- pagination serveur par curseur ou stratégie efficace ;
- limite maximale par page ;
- ordre stable ;
- requêtes bornées ;
- pas de chargement complet de la table ;
- pas de `COUNT(*)` coûteux répété à chaque rafraîchissement si le volume devient important ;
- agrégats périodiques pour les compteurs du tableau de bord si nécessaire.

Teste la page et les principales requêtes avec un volume représentatif, par exemple 100 000 entrées synthétiques, sans commiter cette donnée.

## 18. Regroupement des erreurs et incidents

Ajoute un mécanisme de regroupement par `error_fingerprint` afin que 500 occurrences identiques ne masquent pas les autres problèmes.

L’admin doit afficher :

- première occurrence ;
- dernière occurrence ;
- nombre d’occurrences ;
- niveau maximal ;
- applications/modules touchés ;
- exemple récent assaini ;
- statut d’incident : nouveau, reconnu, résolu, ignoré temporairement ;
- commentaire administratif facultatif sans donnée sensible.

Si une table d’incidents séparée est justifiée, elle référence les événements sans dupliquer leur contenu. Sinon, calcule proprement l’agrégat.

Lorsqu’une erreur réapparaît après résolution, réouvrir ou signaler la récidive de manière explicite.

## 19. Alertes utiles sans tempête

Analyse et étends `check_log_alerts.php`, `LogAlertsNotifier` et les paramètres d’alertes existants.

Déclencheurs par défaut configurables :

- tout événement `critical`, `alert` ou `emergency` ;
- plusieurs occurrences d’une même empreinte d’erreur dans une fenêtre donnée ;
- hausse anormale des `5xx` ;
- échecs de connexion admin répétés ;
- verrouillage ou rate limiting de sécurité répété ;
- échec d’un job Cron important ;
- sauvegarde non réalisée ou échouée ;
- test de restauration échoué ;
- stockage ou table de logs proche d’un seuil ;
- panne répétée d’une webapp privée ;
- échec répété d’un service externe.

Chaque règle doit posséder :

```text
activation
périmètre
niveaux/événements
fenêtre temporelle
seuil
cooldown
canal existant
destinataires configurés selon le système existant
dernière alerte
dernier retour à la normale
```

Implémente regroupement, cooldown et déduplication. Une erreur répétée ne doit pas envoyer un e-mail à chaque occurrence.

Ajouter :

- test manuel d’une règle ;
- aperçu du déclenchement ;
- historique des alertes ;
- journalisation synthétique de l’envoi ;
- état `revenu à la normale` pour les incidents persistants lorsque cela est fiable.

Ne crée pas de nouveau canal externe sans configuration et autorisation explicites.

## 20. Rétention, archivage et purge

Rendre la rétention configurable par classe, avec des valeurs par défaut raisonnables :

| Classe | Rétention initiale |
| --- | ---: |
| Debug non-production | 7 jours |
| Information opérationnelle | 30 jours |
| Notice et warning | 90 jours |
| Error | 180 jours |
| Critical et sécurité | 365 jours |
| Audit sensible | 365 jours minimum configurable |

Ces valeurs sont techniques et configurables ; ne les présente pas comme une règle juridique universelle.

La purge doit :

- être exécutée par Cron Center ;
- supprimer par lots bornés ;
- respecter les classes de rétention ;
- préserver les incidents ouverts si la politique le prévoit ;
- préserver les événements soumis à un gel administratif ;
- ne jamais utiliser `TRUNCATE` ;
- ne jamais purger les événements récents à cause d’un fuseau mal interprété ;
- produire un résumé ;
- supporter `dry-run` ;
- posséder un verrou ;
- être testée.

Si l’archivage froid des logs est déjà prévu ou pertinent, produire avant purge un fichier `JSONL.gz` avec manifeste et SHA-256 dans un emplacement privé hors webroot. Ne conserve pas automatiquement des archives indéfinies et ne mets pas de données sensibles dans un stockage moins protégé.

## 21. Secours en cas de panne SQL

Conserve ou crée un fallback local minimal, borné et privé pour les erreurs importantes lorsque le store SQL est indisponible.

Règles :

- emplacement runtime privé prévu par le dépôt ;
- format JSON Lines structuré ;
- mêmes règles d’assainissement ;
- permissions restrictives ;
- rotation par taille et nombre de fichiers ;
- quota global ;
- aucun secret ;
- écriture verrouillée ;
- protection contre la récursion ;
- import/rejeu vers SQL uniquement via une commande explicite, idempotente et testée ;
- marquage des lignes rejouées ou déplacement atomique du fichier traité ;
- pas de perte silencieuse si le quota est atteint : créer un signal technique minimal lorsque possible.

Le fallback ne doit pas devenir un second stockage permanent invisible dans Admin > Logs.

## 22. Interface Admin > Logs

Créer ou refondre l’écran `/<base_path>/<ADMIN_LOGIN_PATH>/logs` et l’intégrer au menu admin existant. Il doit respecter la navigation, le layout serveur, le CSS, l’accessibilité et la sécurité déjà en place.

### Vue d’ensemble

Afficher des indicateurs utiles :

- erreurs sur 24 h et 7 jours ;
- événements critiques ;
- incidents nouveaux et ouverts ;
- alertes déclenchées ;
- échecs de connexion ;
- jobs Cron échoués ;
- sauvegarde et dernier test de restauration ;
- erreurs par webapp privée ;
- volume SQL et estimation de rétention ;
- état du fallback local ;
- heure du dernier contrôle d’alertes et de la dernière purge.

### Liste des événements

Colonnes essentielles :

```text
date/heure locale
niveau
application/module
événement
message
résultat/statut
request_id ou corrélation
occurrences si regroupé
```

Prévoir :

- pagination serveur ;
- filtres persistants dans l’URL ;
- plage de dates ;
- niveau ;
- flux ;
- application ;
- webapp privée ;
- module ;
- événement ;
- statut HTTP ;
- request/correlation ID ;
- empreinte ;
- type/identifiant d’entité ;
- utilisateur/acteur selon autorisation ;
- recherche textuelle bornée ;
- bouton de réinitialisation ;
- rafraîchissement manuel et auto-rafraîchissement raisonnable, désactivable.

### Détail d’un événement

Afficher :

- données structurées autorisées ;
- chronologie corrélée ;
- événements parents/enfants ;
- informations d’erreur assainies ;
- entité concernée avec lien admin sûr si l’utilisateur y est autorisé ;
- statut de l’incident ;
- actions d’incident autorisées.

Ne jamais afficher de stack complète, secret, payload complet ou chemin système sensible.

### Vues spécialisées

Prévoir au minimum des filtres ou onglets pour :

```text
Tous les événements
Erreurs et incidents
Sécurité
Audit admin
Webapps privées
Cron et sauvegardes
Alertes
Paramètres
```

Évite de multiplier les pages si un seul écran filtrable est plus cohérent avec l’administration existante.

### Export des logs

Permettre un export CSV et JSON des résultats filtrés, dans une limite raisonnable ou via un job asynchrone.

Règles :

- autorisation dédiée ;
- réauthentification pour un export sensible si le projet la prévoit ;
- export des seules données assainies ;
- bornes de dates et de volume ;
- prévention de l’injection de formules CSV ;
- manifeste et SHA-256 pour un export volumineux ;
- fichier temporaire privé et expirant ;
- audit de l’export sans recopier son contenu.

## 23. Autorisations de l’administration

Réutilise le système de droits existant et crée les capacités nécessaires seulement s’il les supporte :

```text
logs.view
logs.view_security
logs.view_audit
logs.export
logs.manage_incidents
logs.manage_alerts
logs.manage_retention
logs.purge
```

Le simple accès à une webapp privée ne donne pas accès aux logs globaux. L’accès Admin > Logs doit être réservé aux administrateurs autorisés.

Les routes de consultation sont protégées côté serveur. Toutes les mutations exigent CSRF ; les opérations de purge, export massif ou modification de sécurité utilisent la réauthentification existante si disponible.

## 24. Paramètres d’observabilité

Ajouter ou consolider une section de configuration centralisée, sans exposer de secrets :

- niveaux actifs par environnement ;
- seuils de lenteur ;
- classes de rétention ;
- taille et rotation du fallback ;
- règles et cooldowns d’alertes ;
- capture des erreurs frontend ;
- agrégation des `404` ;
- applications/modules connus ;
- statut des tâches Cron liées ;
- destination d’archive froide si configurée ;
- test de journalisation et test d’alerte.

Valide strictement les valeurs. Stocke les overrides hors webroot selon les conventions du projet. Un secret peut seulement être remplacé, jamais réaffiché.

Chaque changement de paramètre est lui-même audité avec un diff limité aux noms de champs non sensibles.

## 25. Commandes et tâches d’exploitation

Créer ou compléter des commandes CLI intégrées aux outils du projet :

```text
logs:health-check
logs:check-alerts
logs:purge --dry-run
logs:purge --apply
logs:replay-fallback --dry-run
logs:replay-fallback --apply
logs:integrity-check
logs:aggregate
```

Adapte les noms au style réel des scripts `backend/core/tools/*.php` ou de Composer.

Les scripts pilotables depuis Cron Center doivent être explicitement autorisés, recevoir des arguments contrôlés, utiliser des verrous et retourner des codes de sortie fiables.

Le contrôle de santé doit vérifier :

- schéma SQL attendu ;
- possibilité d’écrire puis lire un événement de test identifiable et de le nettoyer proprement ;
- index principaux ;
- volume et ancienneté ;
- état du fallback ;
- jobs Cron actifs ;
- dernière purge ;
- dernière vérification d’alertes ;
- absence d’événements bloqués ou incohérents.

## 26. Migration de l’existant

Créer une migration de code et de données idempotente avec mode `dry-run` lorsque nécessaire.

Elle doit :

1. sauvegarder l’état actuel selon les outils du projet ;
2. étendre le schéma sans perdre les anciennes entrées ;
3. normaliser les niveaux et canaux existants ;
4. conserver les timestamps ;
5. attribuer un `application` et un `module` raisonnables lorsque cela peut être déduit sans inventer ;
6. laisser `NULL` plutôt que fabriquer un request ID ou un acteur pour les anciens événements ;
7. migrer les réglages d’alertes et de rétention ;
8. raccorder les webapps privées à la façade centrale ;
9. remplacer les appels directs dispersés ;
10. détecter et documenter les logs historiques non importables ;
11. comparer les compteurs avant/après ;
12. permettre une reprise sûre après interruption.

Ne supprime les anciennes tables, colonnes ou fichiers qu’après preuve de non-usage, sauvegarde et vérification. Conserve une compatibilité transitoire uniquement si elle est nécessaire et documentée, puis retire-la dans la même intervention lorsque cela est sûr.

## 27. Tests obligatoires

Ajoute les tests adaptés à la pile réelle.

### Tests unitaires

- validation du catalogue d’événements ;
- normalisation des niveaux et noms ;
- assainissement récursif ;
- masquage des secrets et données sensibles ;
- limites de taille et profondeur ;
- prévention de l’injection de logs ;
- génération request/correlation ID ;
- empreinte d’erreur stable ;
- politique de rétention ;
- règle d’alerte, seuil, fenêtre et cooldown ;
- prévention de formule CSV ;
- sérialisation du fallback.

### Tests d’intégration

- écriture et lecture SQL ;
- compatibilité des anciens appels `AppEventLogger` ;
- index et pagination ;
- panne SQL avec fallback ;
- récursion impossible du logger ;
- rejeu idempotent du fallback ;
- gestion d’exception non interceptée ;
- erreur fatale simulée si testable ;
- propagation du contexte ;
- audit transactionnel d’une action sensible ;
- agrégation d’erreurs identiques ;
- alerte sans tempête ;
- purge en `dry-run` puis par lots ;
- gel empêchant la purge ;
- migration relançable ;
- événement émis par chaque webapp privée.

### Tests fonctionnels admin

- accès refusé sans session ;
- accès refusé sans droit ;
- affichage et pagination ;
- filtres ;
- recherche par request/correlation ID ;
- vue d’un incident ;
- chronologie corrélée ;
- paramètres avec CSRF ;
- export autorisé et sécurisé ;
- purge protégée ;
- test d’alerte ;
- absence de secret et de stack dans le HTML.

### Tests de non-régression

- login admin ;
- admin existante ;
- Cron Center ;
- sauvegardes ;
- alertes actuelles ;
- pages et blog ;
- webapps privées ;
- déploiement et scripts de validation concernés.

Utilise uniquement des données synthétiques. Ne commite aucune donnée personnelle ni gros volume de test.

## 28. Vérifications de charge et de robustesse

Avec un jeu temporaire représentatif :

- vérifier les listes et filtres sur environ 100 000 événements ;
- contrôler les plans de requête principaux ;
- mesurer l’impact d’une écriture de log sur une requête métier ;
- vérifier les limites du fallback ;
- simuler une rafale d’erreurs identiques ;
- vérifier le cooldown d’alerte ;
- tester une purge par lots ;
- vérifier qu’un export ne sature pas la mémoire ;
- vérifier que la page admin reste paginée et responsive.

Supprime toutes les données synthétiques et fichiers temporaires après les tests.

## 29. Documentation

Mets à jour en priorité les documents actifs existants, notamment le document de logging et les README sécurité/déploiement, plutôt que de créer une série de nouveaux Markdown.

Documente :

- architecture et source de vérité ;
- contrat d’événement ;
- niveaux, flux, applications et modules ;
- catalogue et conventions de nommage ;
- assainissement ;
- request/correlation ID ;
- instrumentation du site, de l’admin et de chaque webapp privée ;
- écran Admin > Logs ;
- alertes ;
- rétention et purge ;
- fallback et rejeu ;
- commandes CLI et Cron Center ;
- migration ;
- tests ;
- procédure de diagnostic ;
- procédure de rollback ;
- limites réellement restantes.

Ajoute une courte fiche pratique : « retrouver l’origine d’une erreur à partir d’un request ID ».

## 30. Ordre d’exécution attendu

1. Audit complet de l’existant.
2. Cartographie des événements importants du projet et des webapps privées.
3. Contrat structuré, catalogue, sanitizer et identifiants de corrélation.
4. Migration SQL et index.
5. Évolution compatible d’`AppEventLogger`, du store et du fallback.
6. Gestionnaires PHP, middleware HTTP et frontières techniques.
7. Audit admin et sécurité.
8. Instrumentation métier du projet principal.
9. Instrumentation de chaque webapp privée.
10. Frontend significatif, sans bruit.
11. Cron Center, sauvegardes et appels externes.
12. Regroupement des erreurs et incidents.
13. Alertes, rétention, agrégation et purge.
14. Interface Admin > Logs et droits.
15. Migration des anciens logs et appels directs.
16. Tests unitaires, intégration, fonctionnels et charge.
17. Documentation et suppression du code devenu inutile.
18. Vérification finale complète.

## 31. Critères d’acceptation

Le travail est terminé uniquement si :

- `AppEventLogger` ou sa façade compatible est l’entrée centrale de tout le projet ;
- le site, l’admin et toutes les webapps privées utilisent le même contrat ;
- aucun logger privé incompatible ne reste actif ;
- chaque événement possède application/module et date cohérente ;
- request ID et correlation ID permettent de reconstruire une opération ;
- les erreurs identiques sont regroupables ;
- les secrets, tokens, sessions, payloads et stacks complètes sont absents de SQL, des fichiers et de l’HTML ;
- les actions admin sensibles disposent d’une piste d’audit ;
- les opérations importantes de Locations immobilières sont couvertes ;
- les logs SQL disposent des index nécessaires ;
- la page Admin > Logs fonctionne via `ADMIN_LOGIN_PATH` ;
- l’URL de production donnée n’est pas codée en dur ;
- les filtres, la pagination, la chronologie et les vues privées fonctionnent ;
- les autorisations sont vérifiées côté serveur ;
- les alertes sont regroupées et protégées contre les tempêtes ;
- Cron Center exécute alertes, agrégation et purge ;
- le fallback fonctionne sans récursion et peut être rejoué ;
- la rétention est configurable et la purge possède un `dry-run` ;
- les anciens logs restent accessibles après migration ;
- la charge de 100 000 événements ne rend pas l’admin inutilisable ;
- les tests pertinents passent ;
- les commandes de validation du dépôt passent ;
- la documentation décrit le comportement réel ;
- aucun fichier temporaire, donnée synthétique ou trace de debug ne reste.

## 32. Compte rendu final attendu

À la fin, fournis en français :

1. l’architecture trouvée avant intervention ;
2. les problèmes, doublons, trous de couverture et risques découverts ;
3. l’architecture effectivement mise en place ;
4. le contrat final des événements ;
5. les migrations et fichiers modifiés ;
6. la liste des domaines et webapps instrumentés ;
7. les événements majeurs ajoutés par domaine ;
8. les protections de données et de sécurité ;
9. le fonctionnement de l’écran Admin > Logs ;
10. les alertes, rétentions, tâches Cron et fallback ;
11. les commandes et tests exécutés avec leurs résultats ;
12. le résultat des tests de charge ;
13. les éventuelles capacités facultatives indisponibles dans l’environnement ;
14. les seules vérifications manuelles restantes lorsqu’elles exigent réellement la production ou un service extérieur.

Ne conclus pas par une simple proposition. Analyse, implémente, migre, instrumente, teste, documente et vérifie la solution complète sans déployer en production.
