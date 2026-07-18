# Prompt — Refonte complète de l’import, du stockage, de l’archivage, de l’export et des sauvegardes de la webapp Locations immobilières

Tu es le responsable technique senior de cette intervention. Travaille directement dans le dépôt actuellement ouvert backend\src\PrivateApps\ et réalise une refonte complète, cohérente et prête pour la production de la gestion documentaire de toutes webapp existantes ou futures.

L’objectif n’est pas d’ajouter un nouvel import isolé. Il faut remplacer ou faire converger tous les mécanismes documentaires existants vers **une structure centrale unique**, réutilisée par tous les onglets de toute webapp, sans doublon de code, de fichier ou de règle métier.

Tu dois faire les meilleurs choix directement après analyse de l’existant. Ne me demande pas de choisir entre plusieurs architectures lorsque le dépôt permet de trancher. N’effectue pas de corrections partielles onglet par onglet et ne laisse pas deux systèmes concurrents en place.

## 1. Méthode de travail obligatoire

Avant toute modification :

1. Lis intégralement le `AGENTS.md` racine, puis tout `AGENTS.md` plus proche du périmètre concerné.
2. Lis les README, documents d’architecture, conventions SQL, règles de sécurité, stockage, tâches planifiées, sauvegardes et tests utiles.
3. Localise les webapp sans supposer des chemin : recherche ses routes, menus, onglets, services, tables, migrations, modèles, contrôleurs, templates, composants frontend et tests.
4. Inventorie tous les points actuels d’import, de téléchargement et de suppression de fichiers.
5. Recherche aussi les mécanismes transversaux déjà présents dans le projet : authentification, autorisations, CSRF, journalisation, stockage privé, upload, antivirus, détection MIME, miniatures, OCR, tâches asynchrones ou cron, export ZIP, dump SQL, sauvegarde et restauration.
6. Identifie la source de vérité réelle, les conventions du dépôt et les modifications locales déjà présentes. Préserve les changements de l’utilisateur et évite toute réécriture sans rapport avec la mission.
7. Établis d’abord un état des lieux court et factuel,
8. Creer un readme "import-export-optimisation.md" très detaillé et très complet avec phases et checklist afin de pouvoir implementer en plusieurs temps.
9. lance l'implémentation selon le readme "import-export-optimisation.md"


Interdictions :

- ne déploie pas en production ;
- n’écris pas dans une base de production ;
- ne supprime aucun fichier utilisateur existant avant migration vérifiée et sauvegardée ;
- ne crée pas une nouvelle architecture parallèle si un service commun du projet peut être étendu proprement ;
- ne stocke pas de document binaire ni de Base64 en base SQL ;
- ne place pas de document privé sous un chemin publiquement accessible ;
- ne fais pas dépendre le fonctionnement de base d’une API d’IA externe ;
- ne laisse pas de TODO bloquant, de faux traitement, de bouton non branché ou de migration non testée.

Si une dépendance système facultative manque, prévois une dégradation explicite et sûre, documente la capacité manquante et fais fonctionner tout le reste. Une optimisation absente ne doit jamais entraîner la perte ou le rejet silencieux d’un original valide.

## 2. Résultat fonctionnel attendu

Tous les onglets des webapp doivent utiliser :

- un seul composant d’import réutilisable ;
- un seul service backend d’import ;
- une seule politique de formats et de tailles ;
- un seul stockage physique privé ;
- une seule bibliothèque SQL de documents ;
- une seule taxonomie globale ;
- un seul mécanisme de rattachement à toutes les entités métier ;
- une seule gestion des versions ;
- une seule politique d’archivage ;
- un seul moteur d’export ;
- une seule chaîne de sauvegarde et de restauration.

Les onglets ne doivent transmettre au module central que leur **contexte métier**. Ils ne doivent jamais écrire eux-mêmes un fichier sur disque.

Exemples de contextes à prendre en charge selon les entités réellement présentes : pour la webapp backend\src\PrivateApps\RealEstateRental

- bien ou logement ;
- propriétaire ;
- locataire ou garant ;
- bail et avenant ;
- état des lieux ;
- loyer, échéance, paiement, impayé et dépôt de garantie ;
- charge et régularisation ;
- taxe et exercice fiscal ;
- assurance et sinistre ;
- copropriété ;
- diagnostic ;
- travaux, devis, facture et intervention ;
- courrier ou discussion ;
- toute autre entité existante pouvant recevoir un justificatif.

Un document doit pouvoir être rattaché à plusieurs entités sans duplication physique. Exemple : une facture de travaux peut être liée au bien, au chantier, à la charge et à l’exercice fiscal.

## 3. Architecture centrale à mettre en place

Adapte les noms au langage et aux conventions du dépôt, mais conserve ces responsabilités.

### 3.1 Services

Créer ou faire converger vers des services clairement séparés :

- `DocumentImportService` : orchestration complète d’un import ;
- `DocumentValidationService` : taille, extension, MIME réel, signature interne et structure ;
- `DocumentStorageService` : écriture atomique, lecture et suppression contrôlée ;
- `DocumentDeduplicationService` : empreinte SHA-256 et réutilisation d’un objet existant ;
- `DocumentLinkService` : rattachements aux entités métier ;
- `DocumentClassificationService` : catégorie contextuelle, règles et score de confiance ;
- `DocumentDerivativeService` : miniatures, aperçus et autres éléments recréables ;
- `DocumentArchiveService` : cycle de vie, gel et conservation ;
- `DocumentExportService` : exports lisibles et portables ;
- `DocumentBackupService` : sauvegarde complète vérifiable ;
- `DocumentRestoreService` : contrôle et restauration, avec simulation ;
- `DocumentIntegrityService` : contrôle croisé SQL, stockage et empreintes ;
- `DocumentGarbageCollector` : purge prudente des temporaires et objets non référencés.

Utilise des interfaces et adaptateurs uniquement lorsqu’ils apportent une vraie séparation testable. Ne crée pas une abstraction cérémonielle inutile.

### 3.2 Stockage physique privé et immuable

Utilise un emplacement privé conforme à l’architecture du dépôt, hors webroot, hors Git et hors dossier déployable. La structure logique cible est : pour backend\src\PrivateApps\RealEstateRental

```text
<private-storage>/real-estate/
├── quarantine/
├── objects/
│   └── sha256/
│       └── ab/
│           └── cd/
│               └── <empreinte-sha256>
├── derivatives/
├── exports-temp/
└── restore-temp/
```

Règles non négociables :

- écrire d’abord dans un fichier temporaire privé ;
- valider avant promotion ;
- calculer SHA-256 avant stockage définitif ;
- stocker le contenu sous son empreinte, sans utiliser le nom fourni par l’utilisateur comme chemin ;
- promouvoir par déplacement atomique lorsque le système de fichiers le permet ;
- imposer une contrainte unique SQL sur l’empreinte ;
- gérer correctement deux imports concurrents du même fichier ;
- conserver le nom original uniquement comme métadonnée ;
- ne jamais modifier ni écraser un objet original déjà stocké ;
- servir les téléchargements par un contrôleur authentifié et autorisé ;
- renvoyer des en-têtes sûrs, notamment `Content-Disposition` et `X-Content-Type-Options: nosniff` ;
- empêcher tout accès par construction de chemin ou traversée de répertoire.

### 3.3 Modèle de données

Réutilise les mécanismes de migration du projet. Adapte le SQL au SGBD réel. Prévois au minimum les concepts suivants :

#### Objets physiques

```text
storage_objects
- id ou UUID stable
- sha256 unique
- mime_type réel
- extension normalisée
- storage_path ou storage_key
- original_size
- stored_size
- status
- created_at
- integrity_checked_at
```

#### Documents métier

```text
documents
- UUID stable
- storage_object_id
- category_id
- original_filename
- title
- document_date
- fiscal_year
- status
- retention_until nullable
- legal_hold ou équivalent
- created_by
- created_at
- archived_at nullable
- deleted_at nullable
```

#### Rattachements multiples

```text
document_links
- document_id
- entity_type contrôlé
- entity_id
- link_role
- created_at
- contrainte empêchant le même lien en double
```

N’utilise pas un `entity_type` arbitraire non validé. Centralise les types autorisés et vérifie l’existence et l’autorisation d’accès à chaque entité avant création du lien.

#### Versions

```text
document_versions
- document_id
- version_number
- storage_object_id
- reason
- created_by
- created_at
- unicité document/version
```

Une correction crée une nouvelle version. Aucun bail, facture ou justificatif ne doit être écrasé silencieusement.

#### Dérivés recréables

```text
document_derivatives
- document_id ou storage_object_id
- derivative_type
- storage_key
- mime_type
- size
- generator_version
- created_at
- last_accessed_at
```

#### Catégories

```text
document_categories
- UUID stable
- parent_id nullable
- code technique stable et unique
- label
- is_system
- is_active
- sort_order
- export_directory
- retention_policy_id nullable
- created_at
- updated_at
```

Limiter la hiérarchie à deux niveaux : catégorie et sous-catégorie.

#### Imports et traçabilité

```text
document_imports ou document_import_jobs
- document_id nullable pendant le traitement
- import_source
- context_type
- context_id
- classification_source
- classification_confidence
- status
- error_code nullable
- error_message_sanitized nullable
- created_by
- created_at
- started_at
- finished_at
```

Les états doivent être explicites, par exemple : `quarantined`, `validating`, `processing`, `ready`, `rejected`, `failed`.

## 4. Composant d’import unique pour tous les onglets

Créer un composant réutilisable intégré à chaque emplacement existant. Il doit prendre un profil déclaratif et non contenir de logique métier spécifique codée en dur.

Chaque profil d’import définit au minimum :

```text
import_source
context_type
default_category_code
allowed_category_codes si nécessaire
required_context_fields
metadata_fields_to_extract
allow_multiple
```

Exemples conceptuels :

```text
property.expenses.water    -> expense    -> charges.water
lease.contract             -> lease      -> leases.contract
property.tax               -> tax        -> tax.property_tax
insurance.contract         -> insurance  -> insurance.contract
inventory.entry            -> inventory  -> inventory.entry
```

Le composant doit permettre :

- glisser-déposer ;
- sélection multiple ;
- import depuis mobile et prise de photo si le frontend le permet ;
- progression réelle ;
- annulation tant que le fichier n’est pas finalisé ;
- affichage clair des erreurs par fichier ;
- catégorie proposée et modifiable ;
- date, titre et description ;
- aperçu lorsque disponible ;
- confirmation avant remplacement ou nouvelle version ;
- accessibilité clavier et libellés explicites.

Le contexte déjà connu ne doit pas être redemandé. Depuis la fiche d’une charge d’eau de la Villa Carena en 2026, le bien, la charge, l’année et la catégorie doivent être préremplis.

Mettre en place un point d’entrée backend unique selon les conventions du projet, conceptuellement :

```text
POST   /api/documents/import
GET    /api/documents
GET    /api/documents/{id}
GET    /api/documents/{id}/download
PATCH  /api/documents/{id}
POST   /api/documents/{id}/links
DELETE /api/documents/{id}/links/{linkId}
POST   /api/documents/{id}/versions
POST   /api/documents/{id}/archive
POST   /api/documents/export
```

Respecte les conventions de routage existantes plutôt que d’imposer littéralement ces URL.

## 5. Formats acceptés et validation

La liste blanche cible est :

```text
pdf
jpg
jpeg
png
webp
heic
heif
tif
tiff
docx
odt
xlsx
ods
csv
txt
```

Refuser notamment :

```text
exe, msi, bat, cmd, com
php, js, py, sh, ps1
html, htm, svg
docm, xlsm, pptm
zip, rar, 7z, tar
doc, xls, ppt
gif, bmp
```

Refuser les fichiers protégés par mot de passe, chiffrés ou impossibles à inspecter, avec une erreur claire. Ne jamais se fier au `Content-Type` du navigateur ni à l’extension seule.

Valider côté serveur :

- extension autorisée ;
- MIME déterminé par le contenu ;
- correspondance extension/MIME ;
- signature magique ;
- structure interne pour les conteneurs ZIP autorisés comme DOCX/XLSX/ODT/ODS ;
- absence de macro ;
- absence de contenu exécutable ;
- taille ;
- nombre de pixels pour les images avant décodage complet ;
- nombre de pages ou complexité raisonnable pour les PDF lorsque l’outil existe ;
- ouverture effective après traitement ;
- scan antivirus si le projet ou l’environnement dispose d’un moteur fiable.

Limites par défaut, configurables dans un seul endroit :

| Famille | Maximum par fichier |
| --- | ---: |
| PDF | 25 Mo |
| JPG, PNG, WebP, HEIC | 15 Mo |
| TIFF | 30 Mo |
| DOCX, ODT, XLSX, ODS | 15 Mo |
| CSV, TXT | 5 Mo |
| Lot complet d’un import | 100 Mo |

Limiter également une image d’entrée à 40 millions de pixels afin de prévenir l’épuisement mémoire. Ne jamais charger naïvement en mémoire un fichier proportionnellement à une taille fournie par le client.

## 6. Conservation, optimisation et économie d’espace

Le meilleur compromis pour une application locative est de protéger la valeur documentaire avant le gain de quelques mégaoctets.

### 6.1 Original immuable

Conserver l’octet original sans modification pour les documents à valeur contractuelle, comptable, fiscale, administrative ou probatoire, notamment :
pour la webapp backend\src\PrivateApps\RealEstateRental :
- bail, avenant et résiliation ;
- état des lieux et photos qui lui sont rattachées ;
- facture et justificatif de paiement ;
- taxe, assurance, diagnostic et attestation ;
- pièce d’identité ou dossier locataire ;
- courrier important ;
- document signé.

a optimiser selon les webapp deja créées.

La taille doit être optimisée principalement par :

- déduplication exacte SHA-256 ;
- absence de copie par onglet ou rattachement ;
- absence de Base64 en SQL ;
- purge des temporaires ;
- dérivés limités et recréables ;
- sauvegardes incrémentales ;
- compression des exports et dumps ;
- exclusion des aperçus des sauvegardes complètes.

### 6.2 Aperçus et dérivés

Ne pas appliquer la limite de 2 048 px à tous les fichiers. Elle concerne uniquement une copie d’affichage d’image ou de page scannée.

Pour les images :

- corriger l’orientation dans le dérivé, sans altérer l’original ;
- conserver les proportions ;
- ne jamais agrandir une petite image ;
- aperçu principal : côté le plus long limité à 2 048 px ;
- miniature de liste : 320 à 400 px selon le composant réel ;
- JPEG de prévisualisation : qualité cible 82 à 85 ;
- conserver la transparence lorsque nécessaire ;
- ne générer WebP que si le rendu du projet le sert réellement ;
- ne pas conserver plusieurs variantes inutiles ;
- supprimer et régénérer les dérivés obsolètes à partir de l’original.

Pour les PDF :

- conserver l’original ;
- ne jamais réécrire un PDF signé ;
- générer uniquement les aperçus nécessaires ;
- utiliser une optimisation structurelle sans perte si elle produit un dérivé utile et valide ;
- ne conserver un résultat optimisé que s’il est valide et significativement plus petit, par exemple au moins 5 % ;
- comparer le nombre de pages et vérifier l’ouverture du résultat.

Pour les documents Office et OpenDocument :

- conserver l’original natif ;
- ne pas convertir l’original en PDF ;
- créer un aperçu PDF uniquement si une dépendance locale fiable est disponible et si le besoin existe ;
- exécuter la conversion de manière isolée, sans macro, avec limites de temps, mémoire et répertoire temporaire dédié.

Pour CSV et TXT :

- conserver l’original pour l’export portable ;
- une compression transparente au repos n’est acceptable que si le service de stockage la gère de façon uniforme et testée ;
- ne pas complexifier le système pour un gain négligeable sur de petits fichiers.

## 7. Taxonomie globale et classement automatique

Créer une seule taxonomie globale pour toute la webapp. Fournir au minimum les catégories système suivantes, en les adaptant aux fonctions déjà présentes :
pour backend\src\PrivateApps\RealEstateRental
```text
Bien immobilier
Locataires
Baux
États des lieux
Loyers et paiements
Charges
Travaux et réparations
Fiscalité
Assurances et sinistres
Copropriété
Diagnostics et conformité
Banque et comptabilité
Courriers
Autres
À classer
```

Prévoir les sous-catégories métier utiles : eau, électricité, entretien, appels de fonds, régularisations, taxe foncière, CFE, quittances, impayés, dépôt de garantie, devis, factures, DPE, etc.

Les catégories système sont stables. L’administrateur peut :

- créer une catégorie ou sous-catégorie personnelle globale ;
- renommer une catégorie personnelle ;
- changer l’ordre ;
- définir le nom du dossier d’export ;
- désactiver ;
- fusionner ;
- déplacer les documents avant suppression.

Une catégorie utilisée ne doit pas être supprimée brutalement. Ne crée jamais automatiquement une nouvelle catégorie : l’automatisme sélectionne une catégorie existante ou suggère une création à confirmer.

Ordre de classification :

1. contexte explicite de l’onglet ;
2. choix explicite de l’utilisateur ;
3. règles déterministes sur la source, le fournisseur et le nom ;
4. extraction de texte/OCR local si disponible ;
5. catégorie `À classer` si le résultat reste incertain.

Scores :

- 90 à 100 : classement automatique ;
- 60 à 89 : proposition présélectionnée à confirmer ;
- moins de 60 : `À classer`.

Ne fais pas dépendre cette fonction d’un service d’IA distant. Si le projet possède déjà un fournisseur IA approuvé, il peut rester un enrichissement facultatif derrière une interface, jamais une condition de réussite.

## 8. Centre de documents

Ajouter un écran global `Documents`, intégré à la navigation et aux autorisations existantes, par webapp

Fonctions :

- recherche ;
- filtres par bien, logement, locataire, bail, année, catégorie, fournisseur, type de fichier et statut ;
- vue `À classer` ;
- affichage de tous les rattachements ;
- ajout et retrait de rattachement avec vérification d’autorisation ;
- téléchargement de l’original ;
- aperçu ;
- consultation des versions ;
- création d’une nouvelle version ;
- archivage ;
- export d’une sélection ;
- signalement de doublon ;
- historique des actions importantes.

Chaque onglet métier conserve une liste filtrée de ses documents, alimentée par le même service central. Un document ajouté ailleurs doit y apparaître dès lors qu’il possède le rattachement correspondant.

## 9. Archivage et cycle de vie

L’archivage est logique, pas un déplacement physique ni un ZIP permanent.

Cycle de vie cible :

```text
active -> closed -> archived -> pending_deletion -> deleted
```

Prévoir un gel `legal_hold` empêchant purge et modification destructive en cas de litige, contrôle ou procédure.

Règles :

- un document archivé reste consultable et exportable ;
- il devient non modifiable sauf création d’une nouvelle version ou permission spéciale ;
- une suppression utilisateur passe d’abord par une corbeille ;
- aucune suppression physique tant qu’un lien, une version, une conservation ou un gel existe ;
- le délai de conservation doit être configurable par catégorie, sans coder en dur une prétendue règle juridique universelle ;
- journaliser archivage, restauration, mise en corbeille, gel et purge.

## 10. Exports uniques et déterministes

Mettre en place trois produits distincts.

### 10.1 Export dossier lisible

Permettre l’export par webapp ;
Permettre l’export pour backend\src\PrivateApps\RealEstateRental par bien, locataire, bail, exercice fiscal, période, catégorie ou sélection.

Reconstruire une arborescence humaine indépendante de l’onglet d’origine :
pour backend\src\PrivateApps\RealEstateRental
```text
<Bien>/
└── <Année>/
    ├── Baux/
    ├── Loyers-et-paiements/
    ├── Charges/
    ├── Fiscalité/
    ├── Assurances/
    ├── Copropriété/
    └── Travaux/
```

Nom de fichier déterministe et sûr :

```text
AAAA-MM-JJ_categorie_description_<uuid-court>.<ext>
```

Normaliser les noms pour Windows, macOS et Linux : caractères interdits, longueur, doublons, accents et séparateurs. Conserver le nom original dans l’index.

Dans une même archive, ne pas recopier un document ayant plusieurs rattachements. Le stocker une fois et détailler tous ses liens dans le manifeste et `documents.csv`.

### 10.2 Export portable

Inclure :
pour backend\src\PrivateApps\RealEstateRental
```text
data/
├── properties.csv
├── tenants.csv
├── leases.csv
├── rents.csv
├── payments.csv
├── expenses.csv
├── taxes.csv
├── insurances.csv
├── documents.csv
└── complete-data.json
```

Adapte les fichiers aux entités réelles. Les CSV servent à la lecture dans Excel ; le JSON versionné conserve les relations, UUID et valeurs complètes nécessaires à une migration.

Inclure les documents originaux, un `LISEZ-MOI.txt`, un `manifest.json` et `SHA256SUMS`.

### 10.3 Sauvegarde complète restaurable

Une sauvegarde de reprise doit inclure :
pour backend\src\PrivateApps\RealEstateRental
```text
backup-locations-<timestamp>/
├── manifest.json
├── VERSION
├── database/
│   └── database.sql.gz
├── objects/
│   └── sha256/
├── configuration/
│   └── configuration-sanitized.json
├── checksums/
│   └── SHA256SUMS
└── restore/
    └── restore-instructions.txt
```

Ne mets jamais les secrets dans l’archive. Inclure la version de l’application, la version du schéma, la date ISO 8601, le fuseau, les compteurs attendus et les chemins relatifs portables.

Exclure les miniatures et aperçus recréables de la sauvegarde complète.

Les exports volumineux doivent être construits en tâche de fond, par lots et en flux, sans charger toute l’archive en mémoire. Utiliser ZIP64 lorsque nécessaire. Les fichiers temporaires doivent être privés, avoir une durée de vie courte et être purgés après téléchargement ou expiration.

Si le runtime supporte réellement AES-256 pour ZIP, permettre un export chiffré. Vérifier la capacité au démarrage ou dans l’écran d’administration. Ne jamais prétendre chiffrer si la bibliothèque ne le supporte pas. Ne jamais journaliser le mot de passe.

## 11. Sauvegarde, cohérence et restauration

Intègre la sauvegarde au mécanisme de tâches planifiées déjà présent. Si le projet possède un centre cron ou un ordonnanceur, réutilise-le.

Ordre d’une sauvegarde complète :

1. créer un identifiant de sauvegarde ;
2. empêcher la purge physique pendant l’opération ;
3. produire un snapshot ou dump SQL cohérent avec l’outil officiel du projet ;
4. déterminer les objets référencés par cet état ;
5. copier uniquement les originaux nécessaires ;
6. vérifier leur SHA-256 ;
7. générer le manifeste et les compteurs ;
8. générer `SHA256SUMS` ;
9. compresser et chiffrer selon les capacités validées ;
10. vérifier l’archive produite ;
11. la publier dans un emplacement de sauvegarde hors webroot et hors dépôt ;
12. lever le verrou de purge ;
13. nettoyer les temporaires ;
14. journaliser un résultat synthétique sans donnée sensible.

Politique par défaut configurable :

- quotidiennes : 14 jours ;
- hebdomadaires : 8 semaines ;
- mensuelles : 12 mois ;
- annuelles : durée configurée par l’administrateur.

Ne conserve pas l’unique sauvegarde sur le même stockage que la production. Si aucun stockage distant n’est configuré, affiche clairement l’état `sauvegarde locale uniquement` et fournis un adaptateur/configuration pour une destination distincte, sans inventer d’identifiants ni envoyer des données vers un service non autorisé.

Créer un mécanisme de restauration comprenant :

- simulation `dry-run` sans écriture ;
- lecture et validation du manifeste ;
- vérification de tous les checksums ;
- vérification de compatibilité du schéma ;
- refus d’une sauvegarde tronquée ou incohérente ;
- restauration SQL transactionnelle lorsque possible ;
- déduplication des objets déjà présents ;
- copie atomique des objets manquants ;
- régénération des dérivés ;
- rapport final détaillé ;
- rollback propre en cas d’échec.

Le test de restauration doit pouvoir s’exécuter sur une base et un stockage temporaires sans toucher à l’environnement actif.

## 12. Sécurité et confidentialité

Applique toutes les protections existantes du projet et complète si nécessaire :

- authentification obligatoire ;
- autorisation par action et par entité ;
- CSRF pour les opérations concernées ;
- contrôle empêchant l’accès à un document via un UUID deviné ;
- noms physiques non prédictibles par rapport au métier ;
- stockage privé ;
- téléchargement forcé pour les types non affichables ;
- politique de sécurité du contenu pour les aperçus ;
- aucune exécution de macro ;
- aucune inclusion directe d’un fichier importé ;
- limites de durée, mémoire et processus pour les convertisseurs ;
- répertoires temporaires séparés ;
- permissions de fichiers restrictives ;
- messages d’erreur compréhensibles sans divulguer de chemin interne ;
- journaux sans contenu de documents, mots de passe, pièces d’identité ou données bancaires ;
- traçabilité des téléchargements, exports, archivages, restaurations et suppressions sensibles selon les conventions du projet.

La déduplication ne doit jamais révéler à un utilisateur qu’un autre utilisateur possède le même fichier. Vérifie les autorisations sur le document logique, jamais uniquement sur l’objet physique partagé.

## 13. Traitements asynchrones et robustesse

Les opérations lourdes ne doivent pas bloquer une requête HTTP :

- OCR ;
- conversion Office ;
- génération d’aperçus lourds ;
- import d’un lot important ;
- export ZIP volumineux ;
- sauvegarde ;
- restauration de test ;
- contrôle d’intégrité complet.

Réutilise l’ordonnanceur existant. Les jobs doivent être :

- idempotents ;
- relançables ;
- dotés d’un nombre maximum de tentatives ;
- verrouillés contre l’exécution concurrente incompatible ;
- observables ;
- capables de reprendre ou d’échouer proprement ;
- accompagnés d’un nettoyage des fichiers temporaires.

Ne crée pas une nouvelle infrastructure de queue si le volume et l’architecture existante ne le justifient pas.

## 14. Migration de l’existant

La refonte n’est complète que si les documents déjà importés restent accessibles.

Créer une migration idempotente avec mode `dry-run` qui :

1. inventorie chaque ancien emplacement ;
2. identifie l’entité métier et le contexte correspondant ;
3. calcule SHA-256 ;
4. crée ou réutilise l’objet physique ;
5. crée le document logique ;
6. crée les rattachements ;
7. conserve le nom, la date et les métadonnées récupérables ;
8. détecte les fichiers manquants et les références orphelines ;
9. produit un rapport avant toute bascule ;
10. vérifie les compteurs et les téléchargements ;
11. bascule les écrans vers le service central ;
12. ne supprime les anciens fichiers qu’après sauvegarde, vérification et directive explicite.

Après migration, aucun onglet ne doit continuer à enregistrer dans l’ancien système. Retire proprement le code devenu mort lorsque l’absence d’usage est prouvée.

## 15. Tests obligatoires

Ajoute les tests adaptés à la pile existante.

### Tests unitaires

- normalisation des noms ;
- validation extension/MIME ;
- profils d’import ;
- classification et seuils ;
- calcul et comparaison SHA-256 ;
- détermination des chemins ;
- politique de rétention ;
- construction du manifeste ;
- génération déterministe des chemins d’export.

### Tests d’intégration

- import de chaque famille autorisée ;
- refus des formats interdits et fichiers incohérents ;
- import simultané du même fichier ;
- déduplication avec deux documents logiques ;
- rattachement multiple ;
- création d’une nouvelle version ;
- absence d’accès non autorisé ;
- archivage et restauration ;
- export sans doublon physique ;
- vérification de `SHA256SUMS` ;
- purge n’effaçant jamais un objet encore référencé ;
- migration relançable ;
- rollback sur échec.

### Tests bout en bout ou fonctionnels

- importer depuis plusieurs onglets avec le même composant ;
- vérifier le contexte prérempli ;
- retrouver le document dans le Centre de documents et dans chaque entité liée ;
- corriger une catégorie ;
- exporter un bien et une année ;
- télécharger et ouvrir l’archive ;
- lancer une sauvegarde ;
- effectuer une restauration de test isolée.

Fournis de petits fichiers fixtures sûrs et minimaux. N’ajoute aucune donnée personnelle réelle.

## 16. Contrôles de performance et d’intégrité

Ajouter les index SQL nécessaires sur :

- SHA-256 ;
- catégories ;
- statuts ;
- dates et exercices ;
- types et identifiants de rattachement ;
- dates de création ;
- états des jobs.

Évite les N+1 sur les listes documentaires. Paginer les résultats. Ne calcule pas l’empreinte d’un gros fichier en le chargeant entièrement en mémoire. Construis les archives en flux ou par fichiers temporaires bornés.

Créer un contrôle d’intégrité administrable ou exécutable en CLI qui rapporte :

- objets SQL sans fichier ;
- fichiers sans objet SQL ;
- empreintes invalides ;
- documents sans objet ;
- liens vers entités absentes ;
- dérivés obsolètes ;
- jobs bloqués ;
- exports temporaires expirés.

Par défaut, ce contrôle ne corrige ni ne supprime automatiquement. Les corrections destructives nécessitent un mode explicite et une sauvegarde préalable.

## 17. Administration et observabilité

Ajouter une page de configuration centralisée, accessible uniquement aux administrateurs autorisés :

- formats et limites effectifs ;
- disponibilité des outils d’aperçu, OCR, antivirus et chiffrement ;
- emplacement logique du stockage sans divulguer les chemins sensibles aux utilisateurs ordinaires ;
- volume des originaux et des dérivés ;
- économie obtenue par déduplication ;
- nombre de documents à classer ;
- catégories actives ;
- règles de classification ;
- politique d’archivage ;
- politique de sauvegarde ;
- dernière sauvegarde réussie ;
- dernière copie distante réussie ;
- dernier test de restauration ;
- derniers contrôles d’intégrité ;
- jobs échoués.

Journaliser via le système central du projet, sans introduire un second journal concurrent.

## 18. Documentation à mettre à jour

Mets à jour le document existant le plus pertinent plutôt que de multiplier les fichiers Markdown. Documente :

- architecture et sources de vérité ;
- stockage privé ;
- tables et cycle de vie ;
- formats, limites et règles de validation ;
- profils d’import ;
- taxonomie ;
- commandes de migration ;
- tâches planifiées ;
- génération des exports ;
- sauvegarde ;
- restauration normale et `dry-run` ;
- contrôle d’intégrité ;
- dépendances facultatives ;
- procédure de rollback ;
- limites connues réellement restantes.

## 19. Ordre d’exécution attendu

1. Audit de l’existant et cartographie de tous les imports.
2. Conception adaptée aux conventions réelles du dépôt.
3. Migration SQL et stockage privé central.
4. Services centraux et règles de sécurité.
5. Déduplication, versions, rattachements et catégories.
6. Composant d’import unique et profils déclaratifs.
7. Intégration de tous les onglets.
8. Centre de documents.
9. Archivage et cycle de vie.
10. Exports lisible et portable.
11. Sauvegarde complète, manifeste et restauration.
12. Migration idempotente des documents existants.
13. Tests unitaires, intégration et fonctionnels.
14. Contrôle d’intégrité et performance.
15. Documentation et nettoyage du code obsolète.
16. Vérification finale complète.

## 20. Critères d’acceptation

Le travail est terminé uniquement si :

- tous les onglets utilisent le même composant et le même backend ;
- aucun onglet n’écrit directement un fichier ;
- un même fichier importé plusieurs fois n’occupe qu’un objet physique ;
- un document peut être lié à plusieurs entités ;
- les originaux importants restent immuables ;
- les dérivés sont recréables et exclus des sauvegardes ;
- les catégories sont globales, personnalisables et cohérentes ;
- le contexte de l’onglet classe correctement le document ;
- une catégorie incertaine arrive dans `À classer` ;
- les formats interdits sont refusés côté serveur ;
- les fichiers privés ne sont jamais accessibles directement ;
- les documents existants restent accessibles après migration ;
- les exports sont portables, déterministes et vérifiés par SHA-256 ;
- la sauvegarde contient SQL, originaux, manifeste et checksums ;
- une restauration `dry-run` puis une restauration isolée réussissent ;
- aucun secret ni dérivé inutile n’est inclus ;
- les tests pertinents passent ;
- les commandes de validation du dépôt passent ;
- la documentation correspond au comportement réel ;
- aucun fichier temporaire ni code mort évident ne reste dans le dépôt.

## 21. Compte rendu final attendu

À la fin, donne un compte rendu en français comprenant :

1. l’architecture trouvée avant intervention ;
2. les problèmes identifiés ;
3. la solution effectivement implémentée ;
4. la liste des fichiers et migrations modifiés ;
5. la manière dont chaque ancien point d’import a été raccordé ;
6. les règles de formats et de stockage finales ;
7. la stratégie d’archivage, d’export, de sauvegarde et de restauration ;
8. les tests et commandes exécutés avec leurs résultats ;
9. les éventuelles capacités facultatives absentes de l’environnement ;
10. les vérifications manuelles restantes, uniquement si elles exigent réellement une action humaine ou un environnement extérieur.

Ne conclus pas par une simple proposition. Analyse, implémente, migre, vérifie et documente la solution complète dans le périmètre autorisé.
