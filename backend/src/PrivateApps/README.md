# PrivateApps — modules applicatifs privés

Modules métier de l'espace privé Caramagnols. Architecture « monolithe modulaire » : un seul dépôt, un seul déploiement, mais des frontières de modules explicites.

## Principes

- `backend/src/PrivatePortal/` est le socle : HTTP (routage, layout privé), authentification, sessions, permissions par module, opérations transverses (sauvegarde, protection des données, migrations). Il ne contient aucune logique métier applicative.
- Chaque module vit sous `backend/src/PrivateApps/<Module>/` et est autosuffisant : `Http/` (contrôleur), `Domain/`, `Repository/`, `Service/`, schémas SQL sous `backend/sql/private/`, tests sous `backend/tests/PrivateApps/<Module>/`.
- Chaque module déclare son intégration au portail via un manifeste implémentant `Caramagnols\PrivatePortal\PrivateAppManifest` (routes, tables, code de permission, événements d'audit, classes de tests…).
- Cible : le socle consomme les manifestes via un registre statique explicite (`PrivateAppRegistry`, chantier phase 3 — voir `docs/roadmap/optimisation-2026-07.md`). Pas d'auto-découverte, pas de hooks dynamiques, pas d'installation à chaud : la modularité sert la lisibilité, l'analyse statique et la testabilité, pas la distribution de modules tiers.

## Style éditorial des interfaces privées

- Les titres, légendes de sections, onglets et libellés visibles doivent être descriptifs et directement utiles à l'utilisateur.
- Ne pas afficher de codes de version, noms de chantier, suffixes techniques ou marqueurs internes dans les titres visibles (`V2`, `legacy`, `phase 3`, nom de migration, etc.).
- Exemple attendu : `Identifiants et descriptif`, et non `Identifiants et descriptif V2`.

## Modules

| Module | Description | Contrôleur extrait | Manifeste |
|---|---|---|---|
| `BlocNote` | Notes privées par catégories | `Http/BlocNoteController.php` | à écrire (phase 3) |
| `Documents` | Documents et fichiers privés | `Http/DocumentsController.php` | à écrire (phase 3) |
| `FamilyDiscussion` | Discussions familiales chiffrées (pièces jointes AES-256-GCM) | non (socle) | à écrire (phase 3) |
| `RealEstateRental` | Gestion locative v2 : biens, lots, bailleurs, locataires, baux, loyers, charges, imports agence | non (socle, ~2 500 lignes à extraire) | `PrivateAppManifest`, `AgencyImportsManifest` |
| `TaxDeclarationHelper` | Aide à la déclaration fiscale | non (socle) | à écrire (phase 3) |

## Stockage des fichiers privés

Les modules qui manipulent des fichiers (`Documents`, `FamilyDiscussion`, intégrations documentaires de `RealEstateRental`, exports privés) doivent écrire hors webroot et hors arborescence de déploiement.

En production depuis le 2026-07-18 :

```text
/home/lescaramgl-ssh/caramagnols-runtime/private-storage/
```

Ce chemin est fourni par `PRIVATE_STORAGE_ROOT`. Il remplace l'usage production historique de `backend/private/storage/`, qui ne doit rester qu'un chemin local de développement ou un périmètre de rollback décidé par procédure.

Structure attendue :

```text
private-storage/
├── uploads/
├── document-hub/
├── family-discussion/
├── backups/
└── exports/
```

Règles :

- ne jamais versionner, générer dans Git, ni déployer de données runtime privées ;
- ne jamais pousser de documents locaux vers la production ;
- conserver les permissions runtime `770` pour les dossiers et `660` pour les fichiers ;
- suivre `backend/docs/STORAGE_RUNTIME_POLICY.md` avant toute migration, archive ou nettoyage. L'historique de migration est conservé dans `backend/docs/archive/2026-07-storage/RUNBOOK_STORAGE_MIGRATION.md`.

## Ajouter un module

1. Créer `backend/src/PrivateApps/<Module>/` avec au minimum `Http/<Module>Controller.php`, ses classes métier et son manifeste `PrivateAppManifest`.
2. Déclarer le manifeste dans le registre du socle (tant que `PrivateAppRegistry` n'existe pas : câbler explicitement dans les consommateurs du socle — routes, dashboard, sauvegarde, migrations).
3. Ajouter les schémas SQL sous `backend/sql/private/` et les tests sous `backend/tests/PrivateApps/<Module>/`.
4. La sécurité passe toujours par le socle : authentification, permission de module (`PrivateModulePermissionRepository`), CSRF, journalisation, stockage hors webroot.

Règles détaillées pour les agents : [AGENTS.md](AGENTS.md). Un module peut préciser ses propres règles dans `<Module>/AGENTS.md` (exemple : [RealEstateRental/AGENTS.md](RealEstateRental/AGENTS.md)).
