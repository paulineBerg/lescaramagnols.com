# Workflow multi-IA spécialisé — Les Caramagnols

Ce dossier est une projection locale spécialisée. Les règles communes, les
rôles, le routage `A/B/C`, le risque `R0`–`R3`, les preuves, les autorisations
et l'archivage sont définis exclusivement dans
`../../../Workspace/pauline-ai-governance/`.

En cas d'écart, appliquer le guide central et corriger cette projection. Les
noms d'outils éventuellement associés aux rôles sont une préférence locale et
n'accordent aucune autorité.

## Spécialisation du projet

- Lire `../AGENTS.md`, le profil central Les Caramagnols, `../governance.yml`
  et l'état Git avant toute intervention.
- Un seul `CURRENT_TASK.md` peut être actif dans ce worktree.
- Les modules sous `backend/src/PrivateApps/` chargent aussi leur `AGENTS.md`
  descendant lorsqu'il existe.
- La production reste la source fonctionnelle et éditoriale observée, mais son
  observation ne constitue jamais une autorisation d'écriture ou de déploiement.
- Les données privées, bases, uploads, exports, caches, logs et secrets restent
  hors de `.ai/`, des rapports et de Git.

## Autorisations et validation

Une commande documentée dans un README, un script ou une ancienne transmission
ne vaut pas autorisation. Utiliser uniquement les contrôles confirmés par le
projet et consigner leur état avec le vocabulaire central.

Avant `Terminé`, appliquer `ARCHIVAGE_DOCUMENTS_SOURCE.md`, qui relaie la
procédure centrale.
