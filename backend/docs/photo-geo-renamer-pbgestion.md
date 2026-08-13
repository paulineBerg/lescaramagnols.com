# Photo Geo Renamer via PbGestion

## Audit 2026-08-13

Le BO Private possède déjà un module `PbGestion` sous
`src/PrivateApps/PbGestion`, exposé comme console privée `Sécurité réseau`.
Le socle agent existe côté backend sous `src/PbGestion` : appairage par code
temporaire, requêtes signées, séquence anti-rejeu, file de commandes et
accusés de réception. Aucun dépôt agent local séparé n'a été trouvé dans le
workspace pendant l'audit.

La décision retenue est donc de ne pas créer un agent photo autonome. Le module
photo devient un contrat de commandes `photo.*` du socle `pbgestion`, afin que
les opérations locales restent exécutées par l'agent générique.

## Architecture

- BO Private : écran `pbgestion/photos`, sélection explicite, modèle de nom,
  déclenchement de commandes et historique via la file existante.
- Serveur OVH : ne manipule aucun chemin absolu et ne lit pas les photos. Il
  stocke seulement des commandes bornées et les statuts retournés par l'agent.
- Agent local `pbgestion` livré avec le module : appairage signé, polling,
  racines locales autorisées, scan simple, aperçu de renommage, exécution en
  deux phases, journal local et rollback par aperçu validé.

Le contrat serveur utilise des identifiants opaques :

- `root_uid` : racine locale autorisée déclarée côté agent ;
- `relative_dir` : dossier relatif validé, sans chemin absolu ni traversal ;
- `items` : liste explicite de fichiers photo sélectionnés.

## Commandes

- `photo.roots.list` : demander les racines locales autorisées.
- `photo.folder.scan` : demander l'analyse d'un dossier relatif.
- `photo.rename.preview` : demander un aperçu de renommage, sans mutation.
- `photo.rename.execute` : exécuter un aperçu validé via `preview_uid`.
- `photo.rename.rollback_preview` : demander l'aperçu inverse d'un lot.
- `photo.rename.rollback_execute` : exécuter un rollback validé.

Les champs `path`, `cmd`, `url`, `host`, `ip` et équivalents restent interdits
par `CommandPolicy`. Les extensions photo acceptées par le contrat sont
`jpg`, `jpeg`, `png`, `webp` et `heic`; l'agent doit annoncer ses capacités
réelles avant d'activer un format.

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
prévisualisation manuelle sans accès aux fichiers locaux.
