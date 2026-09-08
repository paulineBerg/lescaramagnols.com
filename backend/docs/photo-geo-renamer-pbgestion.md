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
- `PhotoGeoCacheKey` définit la clé de cache géographique arrondie.
- `ReverseGeocoderProvider` fixe l'abstraction du fournisseur de géocodage.
- `PhotoRenameBatchService` ingère les aperçus agent, réserve les compteurs,
  construit le payload d'exécution deux-passes et stocke les résultats.
- `PhotoSequenceRepository` initialise ou réserve les séquences SQL par commune
  sans jamais diminuer le dernier numéro connu.

## Installation agent depuis le BO

Le BO `PbGestion > Agents et installation` propose deux parcours :

1. téléchargement d'un installeur PowerShell seulement après case de
   consentement et saisie de `INSTALLER`;
2. second consentement local dans le script, avec saisie de `OUI`;
3. installation sous le profil Windows courant dans
   `%LOCALAPPDATA%\PbGestionAgent`;
4. création d'une tâche planifiée locale `PbGestionAgent`;
5. appairage via un code à usage unique valable 10 minutes.

En cas de refus, aucune installation silencieuse n'est tentée. La webapp garde
un mode restreint dans `PbGestion > Photos locales` : l'utilisateur colle une
liste `nom;ville;date`, le BO calcule les noms proposés, mais il ne lit pas les
EXIF, ne géocode pas et ne renomme aucun fichier.

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
- mode restreint utilisable sans agent appairé.

## Risques résiduels

Le backend livre le contrat, l'écran BO, l'installeur avec consentement
explicite et un agent minimal capable d'exécuter les commandes fichiers bornées.
Les fonctions avancées restent à compléter côté poste : lecture EXIF riche,
miniatures, géocodage inverse réel, cache géographique et galerie interactive.
Sans agent installé, le mode restreint reste volontairement limité à une
  prévisualisation manuelle sans accès aux fichiers locaux. Le déploiement en
  production exige un runbook `.ops-sync` disponible, une sauvegarde vérifiée et
  la synchronisation du schéma privé avant activation.
