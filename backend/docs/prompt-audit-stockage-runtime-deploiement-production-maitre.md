# Prompt — Audit et sécurisation du stockage runtime, des migrations et du déploiement avec production maîtresse

Tu es le responsable technique senior de cette intervention. Travaille dans le dépôt **Les Caramagnols** actuellement ouvert dans VS Code.

Ta mission est d’analyser complètement l’organisation du stockage privé, les dossiers créés sous `backend/private/storage`, les scripts de déploiement et les flux de données local/production, puis de choisir et d’implémenter dans le dépôt la solution la plus sûre et la plus cohérente.

Ne pars pas du principe que les nombreux dossiers visibles sont inutiles. Les captures montrent :

- en production : `/home/lescaramgl-ssh/caramagnols/backend/private/storage/uploads` avec environ 100 dossiers nommés `01`, `04`, `09`, `0b`, `10`, `11`, `1d`, etc. ;
- en local : `backend/private/storage/uploads` avec les mêmes préfixes hexadécimaux et parfois un second niveau, par exemple `0b/36` et `0b/c6` ;
- d’autres espaces runtime locaux : `backups`, `document-hub`, `exports`, `family-discussion`, `uploads`.

Cette nomenclature peut correspondre à un stockage par empreinte ou par hachage. Elle peut aussi provenir d’une précréation inutile ou d’un déploiement `rsync` qui recopie l’arborescence locale. Tu dois le démontrer par le code et les fichiers réels avant toute décision.

## 1. Invariant absolu

La **production OVH est l’unique source de vérité des données**.

Le local sert exclusivement :

- au développement ;
- aux tests ;
- aux fixtures synthétiques ;
- éventuellement à une copie descendante de production, sécurisée et protégée.

Flux autorisés :

```text
Local/Git -> Production : code, migrations de structure et assets de build uniquement
Production -> sauvegarde : données SQL et fichiers runtime
Production -> local : copie de test explicitement demandée et protégée
Local -> Production : aucune donnée SQL, aucun upload, aucun document runtime
```

Interdictions absolues :

- aucun dump SQL local restauré en production ;
- aucun upload local synchronisé en production ;
- aucun document de test envoyé en production ;
- aucun nettoyage de production pendant un déploiement ;
- aucun script de migration comportant SSH, SCP, SFTP, rsync ou une source distante ;
- aucune commande destructrice sur OVH ;
- aucune connexion ou écriture en production pendant cette intervention ;
- aucune suppression locale des données présentes dans `backend/private/storage` ;
- aucune modification d’un fichier de données utilisateur ;
- aucun `--delete-excluded` dans un déploiement ;
- aucune conclusion fondée uniquement sur l’apparence des dossiers dans VS Code ou FileZilla.

Tu peux modifier et tester le **code local**, les configurations d’exemple, les scripts, les tests et la documentation. Pour la production, tu dois uniquement produire un runbook exact qui sera exécuté plus tard après validation humaine.

## 2. Lire les règles du projet

Avant toute modification :

1. lis intégralement le `AGENTS.md` racine et tout `AGENTS.md` applicable ;
2. lis les README et documents actifs concernant architecture, sécurité, stockage privé, portail familial, Locations immobilières, déploiement, sauvegardes et Cron Center ;
3. respecte les conventions réelles du dépôt ;
4. préserve les changements locaux de l’utilisateur ;
5. ne modifie pas les fichiers générés ou publiés lorsqu’une source canonique existe ailleurs.

Prends particulièrement en compte les règles déjà documentées :

- la production est la source de vérité en cas de divergence ;
- `backend/public/uploads/**`, `var/**`, `data/logs/**` et `data/snapshots/**` sont des données runtime à préserver ;
- les scripts de déploiement doivent exclure les données et artefacts non déployables ;
- les uploads éditoriaux ne sont pas des assets Vite ;
- les opérations SQL de production exigent une directive, une sauvegarde et une vérification.

Vérifie et corrige le manque actuel probable : `backend/private/storage/**` doit être reconnu explicitement comme stockage runtime protégé.

## 3. Audit local complet en lecture seule

Commence par une cartographie factuelle. Utilise `rg` et les outils de lecture adaptés.

### 3.1 Arborescence et contenus

Pour chaque sous-dossier de `backend/private/storage` :

- déterminer le nombre de dossiers ;
- déterminer le nombre de dossiers réellement vides ;
- déterminer le nombre de fichiers ;
- mesurer la taille logique ;
- relever les extensions et types de fichiers sans afficher leur contenu ;
- relever les profondeurs d’arborescence ;
- identifier les noms hexadécimaux sur un ou plusieurs niveaux ;
- vérifier si le nom final correspond à une empreinte SHA-256, à un UUID ou à un autre identifiant ;
- vérifier les dates de création/modification disponibles ;
- détecter les liens symboliques sans les suivre ;
- ne jamais afficher de donnée personnelle ou de contenu de document.

Ne qualifie un dossier de vide qu’après vérification réelle du système de fichiers. Un dossier replié dans VS Code ou FileZilla n’est pas une preuve qu’il est vide.

### 3.2 Git et fichiers ignorés

Vérifier :

- quels éléments de `backend/private/storage/**` sont suivis par Git ;
- quels éléments sont ignorés ;
- quelles règles `.gitignore` s’appliquent ;
- présence de `.gitkeep`, `.keep`, `.htaccess`, `index.php` ou fichier sentinelle ;
- présence de fichiers non suivis dans le répertoire de travail ;
- scripts qui créent ces dossiers pendant installation, tests, build ou démarrage.

Rappel : Git ne versionne pas un dossier véritablement vide. Si une arborescence vide arrive en production, elle est créée par un fichier sentinelle, un script, l’application ou la synchronisation du répertoire de travail.

### 3.3 Algorithme de stockage

Rechercher dans tout le code :

- `private/storage` ;
- `storage/uploads` ;
- `document-hub` ;
- fonctions de hachage ;
- `sha256`, `hash_file`, `substr` appliqué à une empreinte ;
- `mkdir`, création récursive et préallocation de dossiers ;
- résolution du chemin de stockage ;
- upload, export, sauvegarde, discussion familiale et Locations immobilières ;
- références SQL aux chemins de fichiers ;
- nettoyage et purge ;
- initialisation du stockage ;
- tests couvrant les chemins.

Établir précisément :

```text
entrée utilisateur
-> service d’upload
-> calcul éventuel d’empreinte
-> génération du chemin
-> création des dossiers
-> écriture du fichier
-> référence SQL
-> téléchargement
-> suppression
```

Déterminer si les préfixes comme `0b/36` sont :

- les deux premiers octets d’une empreinte ;
- un découpage volontaire pour limiter le nombre de fichiers par dossier ;
- une arborescence précréée de 256 ou 65 536 compartiments ;
- un résidu de tests ;
- une arborescence copiée depuis le local.

## 4. Audit des scripts de déploiement

Inspecte tous les scripts et workflows de publication, notamment selon leur présence réelle :

```text
backend/tools/deploy-fast.sh
backend/tools/deploy-release.sh
backend/tools/push-local-sql-to-ovh.sh
backend/tools/sync-editorial-uploads.sh
.ops-sync/**
workflows CI/CD
scripts rsync/scp/sftp
scripts de backup et restauration
```

Pour chaque script, documenter :

- source exacte ;
- destination exacte ;
- exécution depuis Git ou depuis le répertoire de travail ;
- options rsync ;
- présence de `--delete`, `--delete-excluded`, filtres protect/exclude et `--prune-empty-dirs` ;
- traitement des fichiers non suivis ;
- traitement des dossiers vides ;
- traitement de `backend/private/storage/**` ;
- traitement de `backend/public/uploads/**` ;
- traitement des bases SQL ;
- possibilité d’écraser une donnée production par une donnée locale ;
- mécanisme de prévisualisation ;
- contrôles avant/après ;
- rollback.

Reconstituer le payload réel de déploiement. Ne suppose pas qu’un script « déploie du code » parce que son nom le suggère.

## 5. Audit critique des flux SQL

Le dépôt documente l’existence d’un script `push-local-sql-to-ovh.sh --live`. Vérifie son comportement réel.

Répondre précisément :

- restaure-t-il toute la base ou seulement des tables ciblées ?
- les tables des webapps privées partagent-elles la même base ?
- peut-il écraser Locations immobilières, portail familial, Cron Center, sauvegardes ou logs ?
- pousse-t-il aussi des uploads ?
- existe-t-il une liste blanche ou seulement une liste d’exclusions ?
- est-il appelé par un déploiement normal ?
- une commande locale peut-elle l’exécuter accidentellement contre la production ?

La décision cible est ferme : aucune donnée locale ne doit être poussée en production.

Si ce script permet encore une restauration locale vers la production :

- le retirer du déploiement normal ;
- ajouter un garde-fou bloquant conforme à la politique production maîtresse ;
- séparer déploiement du code et opérations éditoriales ;
- remplacer les restaurations complètes par des migrations de schéma exécutées sur la base de production ;
- ne jamais intégrer les tables privées à un éventuel workflow éditorial ciblé ;
- privilégier l’administration en production pour les contenus dont la production est maîtresse.

N’exécute pas ce script et ne tente pas de le tester contre OVH.

## 6. Sémantique obligatoire de `dry-run`

Normalise toutes les nouvelles commandes de migration et nettoyage.

Un `--dry-run` :

- analyse uniquement l’environnement dans lequel la commande est exécutée ;
- n’ouvre aucune connexion vers un autre environnement ;
- ne copie aucune donnée ;
- n’écrit aucune donnée métier ;
- ne crée, déplace, renomme ni supprime aucun fichier métier ;
- ne modifie aucune référence SQL ;
- ne nettoie rien ;
- peut uniquement produire un rapport temporaire privé et un log synthétique ;
- doit permettre de vérifier avant/après que base et stockage sont inchangés.

Conséquences :

- `dry-run` local : analyse uniquement les fixtures et données locales ;
- `dry-run` production : devra être exécuté plus tard directement sur OVH et analysera uniquement les données OVH ;
- aucun `dry-run` local ne vaut diagnostic de la production ;
- aucune commande locale ne doit utiliser le rapport pour envoyer des données vers OVH.

Une migration et un nettoyage doivent être deux commandes différentes.

## 7. Architecture cible à évaluer et retenir

Après audit, choisis une seule architecture et justifie-la. La préférence de sécurité est la suivante, sauf contrainte technique démontrée :

### Code et runtime physiquement séparés

Le stockage runtime de production doit vivre hors de l’arborescence synchronisée du code, par exemple dans un emplacement dédié sous le compte OVH :

```text
/home/lescaramgl-ssh/caramagnols-runtime/private-storage
```

Ce chemin est une proposition à valider contre l’hébergement et les conventions existantes. Ne le crée pas et ne le modifie pas pendant cette intervention.

Le code doit lire une configuration non versionnée, par exemple :

```text
PRIVATE_STORAGE_ROOT
```

Exigences :

- valeur absolue validée ;
- refus du webroot, de la racine du projet, de `/`, de `$HOME` et d’un chemin dangereux ;
- permissions vérifiées ;
- aucun secret réaffiché ;
- valeur locale distincte ;
- stockage exclu du dépôt et des déploiements ;
- sauvegarde explicite de ce chemin ;
- contrôle de santé dans l’administration ou en CLI.

Si l’architecture de releases utilise déjà un dossier partagé, utilise le mécanisme partagé existant au lieu d’en inventer un autre, à condition qu’il soit réellement hors du périmètre de suppression du code.

### Création paresseuse des dossiers

Ne précrée pas 256 ou 65 536 dossiers vides.

Si le découpage par empreinte est légitime :

- conserver l’algorithme de sharding ;
- créer seulement les deux niveaux nécessaires lors de l’écriture effective d’un fichier ;
- utiliser une création récursive sûre ;
- gérer la concurrence ;
- ne pas créer une arborescence par catégorie, bien ou année ;
- reconstruire l’arborescence humaine uniquement lors d’un export.

### Compatibilité transitoire

Prévoir si nécessaire :

- un nouveau chemin primaire configuré ;
- un chemin legacy en lecture seule ;
- aucun nouvel écrit dans le chemin legacy après bascule ;
- migration idempotente exécutée plus tard sur OVH ;
- désactivation du fallback legacy seulement après vérification complète.

Ne maintiens pas indéfiniment deux sources de vérité.

## 8. Déploiement code-only à implémenter localement

Refondre les scripts locaux afin qu’un déploiement normal transporte exclusivement :

- fichiers de code suivis ;
- dépendances production autorisées ;
- assets Vite générés et contrôlés ;
- migrations SQL de structure ;
- fichiers publics versionnés nécessaires.

Le meilleur choix est un artefact déterministe construit depuis la liste des fichiers suivis par Git et les sorties de build explicitement ajoutées, pas un rsync aveugle de tout le répertoire de travail.

Si rsync reste nécessaire, ajouter des règles ancrées adaptées à la racine réelle du transfert pour exclure **et protéger côté destination** :

```text
backend/private/storage/**
backend/public/uploads/**
backend/var/**
backend/data/logs/**
backend/data/snapshots/**
backend/.env
backend/config/*.override.php
backups/**
```

Règles :

- ne jamais utiliser `--delete-excluded` ;
- protéger les chemins runtime contre `--delete` ;
- utiliser `--prune-empty-dirs` si compatible avec le payload ;
- prévisualiser avec `--dry-run --itemize-changes` ;
- parser la prévisualisation ;
- faire échouer le déploiement si une ligne cible un chemin protégé ;
- ne pas considérer un simple `--exclude` comme une preuve suffisante de protection à la suppression ;
- effectuer un contrôle post-déploiement du nombre et du volume des fichiers runtime ;
- ne pas créer les répertoires runtime depuis le poste local.

Ajoute des tests automatisés des filtres avec un faux arbre source/destination temporaire. Vérifie notamment qu’un `--delete` de code ne supprime jamais un fichier runtime distant.

## 9. Contrat de données production maîtresse

Centralise et documente cette politique dans le dépôt :

```text
code_master = git/local
production_data_master = production
runtime_uploads_master = production
production_backups = production -> backup target
test_data = local only
```

Ajouter des garde-fous :

- un déploiement ne contient aucune étape de restauration de dump local ;
- les scripts de push de données refusent l’environnement production ;
- les migrations de structure travaillent uniquement sur la connexion de l’environnement courant ;
- toute migration de données production s’exécute en CLI sur OVH, jamais depuis le poste local ;
- le code de migration ne contient aucun transfert réseau ;
- `--apply` en production exige un flag explicite comme `--confirm-production` en plus d’une sauvegarde validée ;
- un nettoyage exige sa propre commande, son `--dry-run`, son `--apply` et sa confirmation ;
- aucun automatisme de déploiement n’exécute une migration destructive.

## 10. Migration future du stockage production

Implémente uniquement le code local nécessaire et produis le runbook. N’exécute rien sur OVH.

La future procédure production devra suivre cet ordre :

1. sauvegarde SQL et fichiers runtime de production ;
2. manifeste, compteurs, tailles et vérification de la sauvegarde ;
3. déploiement du code uniquement ;
4. activation du support du nouveau chemin avec lecture legacy ;
5. exécution sur OVH du diagnostic `--dry-run` ;
6. revue humaine du rapport ;
7. exécution sur OVH de `--apply --confirm-production` ;
8. migration serveur-vers-serveur des seuls fichiers production ;
9. calcul et comparaison SHA-256 ;
10. mise à jour des références dans des transactions bornées ;
11. vérification des téléchargements et des webapps ;
12. maintien temporaire du legacy en lecture seule ;
13. second backup ;
14. nettoyage séparé après délai de sécurité.

La migration doit être :

- idempotente ;
- reprenable ;
- verrouillée ;
- bornée par lots ;
- vérifiable ;
- sans données locales ;
- sans suppression pendant la première passe ;
- accompagnée d’un rollback documenté.

Choisis après audit entre copie-vérification, déplacement atomique ou autre mécanisme local au serveur. Justifie le compromis espace/rollback et vérifie que source et destination se trouvent ou non sur le même système de fichiers. Ne force pas un hardlink ou un symlink sans preuve de compatibilité OVH.

## 11. Nettoyage des dossiers vides

Ne mélange pas le nettoyage avec le déploiement ou la migration.

Créer si nécessaire une commande locale/CLI générique, destinée à être utilisée plus tard sur l’environnement courant :

```text
storage:prune-empty-directories --dry-run
storage:prune-empty-directories --apply --confirm-production
```

Garanties obligatoires :

- racine lue depuis la configuration et résolue en chemin canonique ;
- comparaison avec une liste de racines autorisées ;
- refus de `/`, du home, de la racine projet, du webroot et d’un chemin vide ;
- `mindepth` équivalent à 1 ;
- parcours du bas vers le haut ;
- aucun suivi de lien symbolique ;
- aucune suppression de fichier ;
- aucune suppression de la racine ;
- suppression uniquement si le dossier est encore vide au moment de l’opération ;
- verrou d’exécution ;
- rapport complet ;
- idempotence ;
- tests sur arbre temporaire ;
- aucun lancement automatique lors d’un déploiement.

Important : les dossiers vides consomment peu d’espace. La priorité est d’identifier et supprimer leur cause. Le nettoyage ne vient qu’après.

## 12. Décisions conditionnelles après preuve

Applique cette logique :

### Cas A — sharding légitime avec fichiers référencés

- conserver le sharding ;
- ne supprimer aucun dossier utilisé ;
- empêcher la précréation massive ;
- créer les branches à la demande ;
- sortir le stockage du périmètre de déploiement.

### Cas B — dossiers vides précréés par l’application

- corriger l’initialisation ;
- ne créer que la racine ;
- créer les sous-dossiers à l’écriture ;
- fournir le nettoyage séparé en dry-run.

### Cas C — dossiers copiés par le déploiement

- remplacer le payload récursif par un artefact code-only ;
- ajouter exclude + protect ;
- ajouter le gate qui bloque toute mutation runtime ;
- corriger les tests et la documentation.

### Cas D — fichiers runtime suivis dans Git

- ne pas les supprimer immédiatement ;
- identifier leur origine et leurs références ;
- préparer leur sortie de Git sans perte ;
- demander une confirmation avant toute opération d’index Git qui pourrait retirer des fichiers suivis ;
- ajouter les bonnes règles d’ignore.

### Cas E — script SQL local vers production

- le retirer du déploiement normal ;
- empêcher son utilisation pour les données privées ;
- si la politique production maîtresse couvre tout le projet, désactiver entièrement le push local de données ;
- conserver uniquement les migrations de structure exécutées sur l’environnement production.

## 13. Tests obligatoires

Ajouter les tests adaptés à la pile réelle :

- génération du chemin par empreinte ;
- création paresseuse des sous-dossiers ;
- concurrence lors de la création ;
- validation de la racine configurée ;
- refus des chemins dangereux ;
- lecture legacy et écriture primaire ;
- dry-run strictement sans mutation ;
- migration idempotente et reprenable ;
- aucun transfert réseau dans la migration ;
- prune des dossiers vides sans suppression de fichier ni racine ;
- filtres rsync sur faux arbre ;
- protection avec `--delete` ;
- blocage de `--delete-excluded` ;
- payload construit uniquement à partir des fichiers autorisés ;
- refus d’un push SQL/upload local vers production ;
- déploiement laissant inchangés les compteurs runtime ;
- sauvegarde incluant le nouveau stockage ;
- webapps privées retrouvant leurs fichiers après configuration.

## 14. Documentation à corriger

Mettre à jour les documents actifs plutôt que créer des notes dispersées :

- `AGENTS.md` ;
- documentation de déploiement ;
- documentation du portail privé ;
- documentation de sauvegarde ;
- documentation de Locations immobilières/import-export si concernée.

Ajouter explicitement :

```text
backend/private/storage/** = runtime non déployable et protégé
production = source maîtresse des données
local -> production = code uniquement
aucun dump ou upload local vers production
```

Documenter :

- chemin runtime ;
- configuration ;
- stratégie de backup ;
- création paresseuse ;
- comportement dry-run ;
- migrations exécutées sur l’environnement courant ;
- runbook production ;
- rollback ;
- commande de diagnostic ;
- commande de nettoyage séparée.

## 15. Ordre de travail attendu

1. Audit des règles et de l’architecture.
2. Audit réel de l’arborescence locale.
3. Audit Git et `.gitignore`.
4. Identification de l’algorithme de stockage.
5. Audit de tous les déploiements.
6. Audit des flux SQL et uploads.
7. Diagnostic de la cause des dossiers visibles.
8. Choix argumenté d’une architecture unique.
9. Implémentation locale des garde-fous code-only.
10. Configuration du stockage hors déploiement.
11. Création paresseuse et compatibilité legacy si nécessaire.
12. Scripts dry-run/migration/nettoyage strictement séparés.
13. Tests.
14. Documentation.
15. Runbook production sans exécution.
16. Vérification finale du diff et des tests.

## 16. Critères d’acceptation

Le travail est terminé uniquement si :

- la cause des dossiers hexadécimaux est démontrée par le code et le système de fichiers ;
- le rapport distingue dossiers affichés, dossiers réellement vides et dossiers contenant des fichiers ;
- le rôle de chaque sous-dossier de `backend/private/storage` est documenté ;
- `backend/private/storage/**` est classé runtime protégé ;
- aucun déploiement normal ne peut copier ou supprimer ce stockage ;
- aucun déploiement normal ne restaure un SQL local ;
- aucun upload local ne peut être envoyé en production ;
- le payload de déploiement contient uniquement du code et les sorties de build autorisées ;
- les règles rsync protègent la destination même avec `--delete` ;
- la production reste seule maîtresse des données ;
- les migrations de données opèrent uniquement sur l’environnement courant ;
- le dry-run ne modifie strictement rien ;
- migration et nettoyage sont séparés ;
- le stockage peut vivre hors du dossier backend via configuration ;
- le sharding utile est créé à la demande et non préalloué ;
- les sauvegardes incluent le stockage externe ;
- aucun fichier local ou distant n’a été supprimé pendant cette intervention ;
- les tests passent ;
- la documentation reflète le comportement réel ;
- un runbook production précis est livré sans avoir été exécuté.

## 17. Compte rendu final attendu

Réponds en français avec :

1. le diagnostic exact des dossiers `01`, `0b`, `1d`, etc. ;
2. la preuve de leur origine ;
3. le nombre de dossiers réellement vides, de fichiers et le volume local, sans exposer de données personnelles ;
4. la liste des éléments suivis ou ignorés par Git ;
5. le comportement exact de chaque script de déploiement ;
6. le risque exact de `push-local-sql-to-ovh.sh` ;
7. la décision architecturale retenue et pourquoi elle est la meilleure ;
8. les fichiers modifiés ;
9. les garde-fous ajoutés ;
10. les tests exécutés et leurs résultats ;
11. ce qui devra être exécuté plus tard sur OVH, étape par étape ;
12. ce qui ne doit surtout pas être exécuté ;
13. les seules questions restantes si une information production ne peut pas être obtenue localement.

Ne supprime rien, ne pousse aucune donnée et ne contacte pas la production. Analyse l’existant, implémente localement la sécurité du flux code-only, prends la meilleure décision fondée sur les preuves et fournis le runbook production.

Documente agents et readme dédiés.
