# Photo Geo Renamer via PbGestion

## Audit 2026-09-08

Le BO Private possède déjà le socle `PbGestion`, exposé pour la sécurité réseau
et réutilisé par `PhotoGeoRenamer`. Le socle agent existe côté backend sous
`src/PbGestion` : appairage par code temporaire, requêtes signées, séquence
anti-rejeu, file de commandes, synchronisation et accusés de réception.

La décision retenue est donc de ne pas créer un agent photo autonome. Le module
photo devient un contrat de commandes `photo.*` du socle `pbgestion`, afin que
les opérations locales restent exécutées par l'agent générique.

## Architecture

- BO Private : écran `photo-rename`, sélection explicite, format principal
  `Commune-01.ext`, déclenchement de commandes, historique, compteurs et aide.
- Serveur OVH : ne manipule aucun chemin absolu et ne lit pas les photos. Il
  stocke les compteurs globaux par commune, les aperçus bornés transmis par
  l'agent, les opérations, les réservations et les statuts retournés.
- Agent local `pbgestion` livré avec le module : appairage signé, polling,
  racines locales autorisées, scan simple, aperçu de renommage, exécution en
  deux phases, journal local et rollback par aperçu validé.

Le contrat serveur utilise des identifiants opaques :

- `root_uid` : racine locale autorisée déclarée côté agent ;
- `relative_dir` : dossier relatif validé, sans chemin absolu ni traversal ;
- `items` : liste explicite de fichiers photo sélectionnés.
- `batch_uid` et `preview_uid` : identifiants opaques d'un aperçu validable.

Le serveur ne scanne jamais un dossier destination pour calculer un prochain
numéro. Les compteurs sont des séquences SQL globales par commune dans
`photo_geo_sequences`. L'allocation est faite juste avant
`photo.rename.execute`; elle avance la séquence et ne la réduit pas, même après
échec ou rollback. Une collision locale externe doit être signalée par l'agent
avec `target_exists`, sans écrasement, puis traitée par une nouvelle exécution
bornée.

## Commandes

- `photo.roots.list` : demander les racines locales autorisées.
- `photo.folder.scan` : demander l'analyse d'un dossier relatif.
- `photo.rename.preview` : demander un aperçu de renommage, sans mutation.
- `photo.rename.execute` : réserver les numéros SQL, puis exécuter un aperçu
  validé via `preview_uid`.
- `photo.rename.rollback_preview` : demander l'aperçu inverse d'un lot.
- `photo.rename.rollback_execute` : exécuter un rollback validé.

Les champs `path`, `cmd`, `url`, `host`, `ip` et équivalents restent interdits
par `CommandPolicy`. Les extensions photo acceptées par le contrat sont
`jpg`, `jpeg`, `png`, `webp` et `heic`; l'agent doit annoncer ses capacités
réelles avant d'activer un format.

## Synchronisation agent

L'agent publie les aperçus détaillés dans `photo_rename_previews` lors de
`sync`. Chaque aperçu contient `batch_uid`, `preview_uid`, `root_uid`,
`relative_dir`, le modèle normalisé, le tri et les opérations relatives. Le
serveur stocke ces opérations avec le statut `previewed` ou `conflict`.

Au moment où l'utilisateur valide l'exécution, le BO appelle
`PhotoRenameBatchService::executePayload()`. Le service vérifie le propriétaire,
l'agent, la source relative et l'aperçu, réserve les plages SQL par commune,
calcule les noms finaux et envoie à l'agent un payload enrichi :

- `no_overwrite: true` ;
- `two_pass: true` ;
- `operations[]` avec `relative_path`, `old_name`, `new_name`,
  `temporary_name`, `commune_key`, `commune_name` et `assigned_number`.

L'agent publie ensuite les résultats dans `photo_rename_results`. Le serveur
met à jour `photo_geo_operations` et le statut du lot (`completed`, `partial`
ou `failed`).

## Services ajoutés

- `PhotoPathPolicy` valide racines, dossiers relatifs et fichiers sélectionnés.
- `PhotoFilenameNormalizer` produit des noms compatibles Windows, macOS et
  Linux, y compris noms réservés Windows.
- `PhotoRenameTemplate` construit les noms à partir de blocs.
- `PhotoRenamePlanner` détecte doublons, conflits et prépare les noms
  temporaires pour un renommage en deux phases.
- `PhotoRollbackPlanner` prépare l'annulation sans écrasement.
- `PhotoGeoCacheKey` définit la clé de cache géographique arrondie à 5
  décimales.
- `ResolvedPlace` transporte le lieu administratif résolu : code pays, code
  administratif, commune, code postal, département, région, fournisseur et date
  de résolution.
- `ReverseGeocoderProvider` fixe l'abstraction du fournisseur de géocodage.
- `AdministrativePlaceResolver` orchestre le cache et les fournisseurs de
  géocodage sans cache d'échec durable.
- `PhotoRenameBatchService` ingère les aperçus agent, réserve les compteurs,
  construit le payload d'exécution deux-passes et stocke les résultats.
- `PhotoSequenceRepository` initialise ou réserve les séquences SQL par commune
  sans jamais diminuer le dernier numéro connu.

## Modes navigateur et agent

Photo Geo Renamer propose deux parcours complémentaires.

Le mode navigateur fonctionne sur Android, iOS, Windows, Linux et macOS sans
agent local. L'utilisateur sélectionne explicitement des photos via le
navigateur ; les fichiers restent côté appareil, les noms sont calculés en
JavaScript et le résultat est téléchargé comme archive ZIP de copies renommées.
Ce mode ne modifie pas les originaux et ne réserve pas les compteurs SQL
globaux, car le serveur ne peut pas garantir que les copies téléchargées seront
réellement conservées.

Depuis la correction du 2026-09-08, le mode navigateur lit les coordonnées GPS
EXIF des JPEG sélectionnés et demande au serveur privé de résoudre uniquement
la commune à partir des coordonnées arrondies. Les photos ne sont pas envoyées
au serveur. Le champ `Commune de secours` est utilisé seulement lorsqu'une photo
n'a pas de coordonnées GPS lisibles ou lorsque le géocodage inverse ne répond
pas.

La résolution automatique n'utilise plus de liste locale, de bounding box du
Golfe de Saint-Tropez, ni d'inférence depuis les photos voisines. Le serveur
interroge d'abord l'API officielle française de découpage administratif
`geo.api.gouv.fr/communes?lat=...&lon=...`, qui retourne la commune contenant
les coordonnées et son code INSEE. Si cette source ne répond pas, il essaie le
géocodage inverse Géoplateforme `data.geopf.fr/geocodage/reverse/`, puis
Nominatim/OpenStreetMap pour les coordonnées internationales. Chaque appel a un
User-Agent applicatif, des délais courts et une relance bornée. Les résultats
positifs sont stockés dans `photo_geo_places` avec une clé de coordonnées à 5
décimales ; les échecs ne sont pas conservés durablement.

En production, si l'hébergement refuse une connexion sortante vers ces APIs, le
navigateur essaie lui-même les sources publiques France `geo.api.gouv.fr` puis
`data.geopf.fr`. La CSP privée autorise uniquement ces connexions externes pour
ce parcours, toujours avec les seules coordonnées et sans fichier photo.

Le tri par défaut du mode navigateur est `date de prise de vue`. L'ancien tri
`ordre de sélection` n'est plus proposé.

Le mode agent est réservé aux ordinateurs Windows, Linux et macOS. Après
consentement explicite, l'agent peut accéder aux dossiers autorisés, lire les
métadonnées, demander les réservations SQL au moment de l'exécution et renommer
directement les fichiers sans écrasement. C'est le parcours rapide et automatisé
pour de grands dossiers locaux. La webapp conserve plusieurs agents par compte
avec leur nom d'ordinateur ou d'usage, l'OS, la version et le dernier contact,
ce qui permet de retrouver plusieurs PC depuis le menu `Agents`.

Sur téléphone, une webapp classique ne peut pas renommer arbitrairement la
photothèque ni surveiller un dossier en arrière-plan. Un vrai renommage direct
mobile nécessiterait une application native ou hybride dédiée avec permissions
plateforme, confirmations explicites et export contrôlé.

## Installation et suppression agent depuis le BO

Le BO `PbGestion > Agents et installation` propose deux parcours :

1. téléchargement d'un installeur Windows, Linux ou macOS seulement après
   confirmation par popup côté BO ;
2. appairage intégré au script téléchargé, sans saisie de `INSTALLER` ni de
   code dans le parcours standard ;
3. installation sous le profil courant :
   `%LOCALAPPDATA%\pbgestion\agent` sur Windows,
   `~/.local/share/pbgestion/agent` sur Linux,
   `~/Library/Application Support/pbgestion/agent` sur macOS ;
4. tâche planifiée Windows, timer systemd utilisateur Linux ou LaunchAgent macOS
   lorsque la plateforme le permet ;
5. appairage via un jeton à usage unique valable 30 minutes.

En cas de refus, aucune installation silencieuse n'est tentée. La webapp garde
un mode navigateur fonctionnel qui produit des copies renommées téléchargées.
La révocation côté BO bloque les synchronisations de l'agent. Pour nettoyer le
poste local, le BO fournit aussi un script de suppression qui retire la tâche
planifiée ou le service utilisateur et supprime le dossier agent local.

Le code manuel reste disponible comme secours si l'utilisateur copie ou lance
l'agent lui-même. Le délai de 30 minutes remplace l'ancien délai de 10 minutes.
Les alternatives à étudier ensuite sont :

- lien d'appairage local ouvrant directement l'installeur généré ;
- QR code d'appairage pour un second appareil ;
- agent lancé en attente puis approbation depuis la webapp ;
- jeton de longue durée révocable pour les postes déjà authentifiés.

## Procédure de test

Tests ciblés :

```bash
vendor/bin/phpunit tests/PbGestion tests/PrivateApps/PbGestion
```

Contrôles attendus :

- traversal et chemins absolus rejetés;
- extensions non supportées rejetées;
- noms spéciaux et noms réservés Windows normalisés;
- doublons et conflits existants détectés avant exécution;
- permutation de noms couverte par noms temporaires;
- rollback bloqué si un ancien nom écraserait un fichier existant.
- téléchargement installeur bloqué sans consentement explicite;
- téléchargement installeur Windows, Linux et macOS avec appairage intégré ;
- téléchargement de script de suppression locale ;
- mode navigateur utilisable sans agent appairé ;
- archive ZIP générée côté navigateur sans upload des photos ;
- message haut explicite lorsqu'aucun agent local n'est détecté.

## Risques résiduels

Le backend livre le contrat, l'écran BO, l'installeur avec consentement
explicite et un agent minimal capable d'exécuter les commandes fichiers bornées.
Les fonctions avancées restent à compléter côté poste : lecture EXIF riche,
miniatures, géocodage inverse réel, cache géographique et galerie interactive.
Sans agent installé, le mode navigateur produit des copies renommées dans une
archive ZIP mais ne peut pas modifier directement les originaux ni surveiller
un dossier mobile. Les installeurs Linux/macOS sont des scripts shell non
notariés et non packagés ; une distribution signée reste à prévoir pour une
livraison grand public. Le déploiement en production exige un runbook
`.ops-sync` disponible, une sauvegarde vérifiée, un test de boot complet et la
synchronisation du schéma privé avant activation.
