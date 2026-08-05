# Règles du projet Les Caramagnols

- La production est la source de vérité fonctionnelle et éditoriale ; le local
  sert au développement et aux tests.
- Toute tâche concernant la production, l'authentification, les permissions,
  les jetons, le RGPD, SQL, les migrations ou les flux de données est de niveau C.
- Un dry run doit rester sans écriture et sans nettoyage destructif.
- Ne jamais recopier automatiquement des données locales en production.
- Ne jamais versionner ni synchroniser comme du code les uploads, stockages
  privés, caches, journaux, sauvegardes, dumps, secrets ou données générées.
- Toute suppression ou tout nettoyage requiert une validation explicite.


<!-- BEGIN MANAGED CENTRAL GUIDE -->
## Gouvernance centrale

Lire le routeur `../pauline-ai-governance/guide-architecture/README.md`, appliquer
`../pauline-ai-governance/guide-architecture/core/00-essentiel.md`, puis charger le
profil, les guides et la checklist sélectionnés. Les règles locales du présent
projet restent applicables et spécialisent le socle sans réduire ses
protections. Si `governance.yml` existe, exécuter le validateur et le résolveur
du socle sans lancer automatiquement les commandes déclarées.

Le workflow, le modèle de tâche et les prompts sont lus directement dans
`../pauline-ai-governance/.ai/README.md`, `../pauline-ai-governance/.ai/TASK_TEMPLATE.md` et
`../pauline-ai-governance/.ai/prompts/` ; ils ne sont pas recopiés dans le projet. Pour toute
modification ou livraison, créer ou mettre à jour `.ai/CURRENT_TASK.md` dans le
présent projet. Ses transmissions terminées vont dans
`.ai/archive/transmissions/YYYY/MM/` du même projet selon la procédure centrale
`../pauline-ai-governance/.ai/ARCHIVAGE_DOCUMENTS_SOURCE.md`.
<!-- END MANAGED CENTRAL GUIDE -->
