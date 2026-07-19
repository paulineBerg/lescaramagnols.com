# Audit De Nettoyage Priorise Du Depot Local

Date : `2026-04-16`  
Depot : chemin local du clone Git (WSL, sous `/home/...`)

## Etat Git Au Moment De L Audit

Snapshot `git status --porcelain=v1` :

- `2226` entrees au total
- `109` fichiers suivis modifies
- `111` fichiers suivis supprimes
- `2006` fichiers non suivis

Conclusion immediate :

- le depot n est **pas** dans un etat nettoyable par une commande globale
- il melange du `travail produit reel`, du `bruit local`, des `artefacts generes`, et une `migration structurelle` deja entamee
- un nettoyage aveugle de type `git clean -fdx` ou `git reset --hard` serait destructif

## Priorites

### P0 - Gel De Securite Avant Nettoyage

Ne rien supprimer globalement tant que les blocs suivants ne sont pas isoles :

- modifications suivies backend et frontend encore non consolidees
- suppressions suivies massives dans les anciens templates et anciens points d entree publics
- ajout de nouvelles arborescences source non suivies dans `backend/src`, `backend/tests`, `backend/templates/admin`, `backend/sql/editorial`, `frontend/tools`

Action recommandee :

1. figer l etat courant dans une branche de sauvegarde locale
2. separer ensuite le nettoyage en lots courts et reversibles

### P1 - Bruit Local A Sortir Du Depot En Priorite

Ces elements polluent le depot sans apporter de valeur Git immediate.

#### 1. `frontend/node_modules`

Constat :

- `14` fichiers suivis modifies sous `frontend/node_modules`
- `.gitignore` ignore bien `node_modules/`, mais des fichiers y sont deja suivis

Risque :

- bruit permanent
- diffs inutiles
- forte probabilite de conflit et de fausses alertes

Decision recommandee :

- sortir `frontend/node_modules` de l index Git
- garder l installation purement locale

#### 2. `backups/`

Constat :

- `12` fichiers non suivis
- plusieurs sauvegardes SQL / prod / local sont stockees dans le repo de travail

Risque :

- depot pollue par des artefacts volumineux et sensibles
- duplication locale sans utilite de versionning

Decision recommandee :

- ignorer `backups/`
- conserver les sauvegardes hors repo, ou dans un stockage ops dedie

#### 3. Outils locaux non arbitres

Constat :

- `.ops-sync/` non suivi
- `.nvmrc` non suivi
- `dev.sh` non suivi
- `php` non suivi

Risque :

- bruit local recurrent
- ambiguite entre outillage personnel et outillage projet

Decision recommandee :

- soit commit explicite si ces outils deviennent standard projet
- soit ajout a `.gitignore` si usage strictement local

### P2 - Strategie Des Assets Publics A Clarifier D Urgence

#### 1. `backend/public/assets/images`

Constat :

- `1947` fichiers non suivis
- `38` fichiers suivis supprimes dans cette zone
- `.gitignore` re-inclut volontairement `backend/public/assets/images/**`

Lecture technique :

- cette arborescence contient a la fois des images publiques utiles, des conversions `webp`, des variantes `@480w`, et du stock historique
- elle est actuellement la **premiere source de bruit du depot**

Risque :

- statut Git illisible
- confusion entre `source asset`, `asset derive`, `asset historique`, `asset runtime`

Decision recommandee :

choisir une strategie unique :

1. soit `backend/public/assets/images/**` reste une source versionnee, et il faut alors trier, normaliser et committer proprement
2. soit cette arborescence devient derivee / publiee, et il faut alors l ignorer presque completement

Recommandation :

- ne versionner que la source canonique
- eviter de versionner les derives publics quand ils existent deja ailleurs
- reduire fortement les exceptions `.gitignore` sur `backend/public/assets/images/**`

#### 2. Fichiers aux noms sales ou artefacts de synchro

Exemples vus dans les suppressions suivies :

- suffixes `:com.amazon.drive.sync`
- suffixes `:com.apple.quarantine`
- noms avec `copy`
- accents ou apostrophes dans les chemins historiques

Risque :

- hygiene faible des assets
- portabilite mediocre
- scripts de build/deploiement plus fragiles

Decision recommandee :

- traiter ce sujet comme un lot de normalisation de noms
- sortir les faux fichiers de metadata du patrimoine versionne

### P3 - Migration Structurelle A Isoler, Pas A Nettoyer

Ces suppressions ressemblent a une refonte du site, pas a du bruit.

Constat :

- `46` suppressions suivies sous `backend/templates/pages/site/**`
- suppressions suivies de points d entree publics anciens :
  - `backend/public/adminFtyhik5642sZ/**`
  - `backend/public/site/adminFtyhik5642sZ/**`
  - `backend/public/installsql.php`
- suppressions suivies de fichiers heritage :
  - `backend/config/menu_data.php`
  - `backend/templates/partials/sitemap.php`
  - `docs/archive/blog-plan-v1.md`

Lecture technique :

- ce bloc sent la migration vers le routeur / contenu dynamique / admin moderne
- il ne doit **pas** etre melange avec un nettoyage de depot

Decision recommandee :

- sortir ce bloc dans une branche ou un commit de migration dedie
- documenter ce qui est remplace, conserve, ou abandonne

### P4 - Vrai Travail Produit A Commiter, Pas A Nettoyer

Ces ajouts non suivis ressemblent a du code projet reel.

Volumes releves :

- `59` fichiers non suivis sous `backend/src`
- `43` fichiers non suivis sous `backend/tests`
- `12` fichiers non suivis sous `backend/templates/admin`
- `6` fichiers non suivis sous `backend/sql/editorial`
- `37` fichiers non suivis sous `frontend/src/assets/images`
- `7` fichiers non suivis sous `frontend/tools`

Lecture technique :

- ce n est pas du bruit
- ce sont des ajouts de fonctionnalites, d infra ou de migration

Decision recommandee :

- faire un tri fonctionnel
- committer par domaines
- ne pas laisser ces ajouts se noyer sous les artefacts locaux

### P5 - Documentation Et Rapports

Constat :

- plusieurs `README*` et documents `docs/` sont modifies ou non suivis
- une partie semble relever de la documentation produit legitime
- une autre partie peut etre du brouillon ou de la trace de travail

Decision recommandee :

- garder uniquement les documents qui servent le runbook, la maintenance ou l architecture
- sortir les notes temporaires et duplications

## Trous Ou Ambiguites Dans `.gitignore`

Points confirmes :

- `node_modules/` est ignore, mais cela ne protege pas les fichiers deja suivis
- `backups/` n est pas ignore
- `.ops-sync/` n est pas ignore
- `backend/public/assets/*` est ignore, puis `backend/public/assets/images/**` est entierement re-inclus

Recommandations `.gitignore` :

1. ajouter `backups/`
2. arbitrer `.ops-sync/`
3. sortir `frontend/node_modules` de l index
4. revoir la politique `backend/public/assets/images/**`

## Plan De Nettoyage Recommande

Ordre conseille :

1. creer une branche de sauvegarde locale avant toute suppression
2. nettoyer d abord le bruit local :
   - `frontend/node_modules`
   - `backups/`
   - outils purement locaux
3. arbitrer la politique des assets publics
4. isoler la migration structurelle dans un lot a part
5. regrouper et committer le vrai code produit non suivi
6. seulement apres, refaire un audit Git

## Proposition De Lots Concrets

### Lot A - Nettoyage Sans Risque Produit

- ignorer `backups/`
- arbitrer `.ops-sync/`, `.nvmrc`, `dev.sh`, `php`
- sortir `frontend/node_modules` de l index

### Lot B - Hygieniser Les Assets

- decider si `backend/public/assets/images/**` est `source` ou `derive`
- supprimer les artefacts de synchro / noms sales
- reduire le bruit image non suivi

### Lot C - Isoler La Refonte

- commit dedie pour les suppressions heritage
- verifier la couverture fonctionnelle avant de supprimer definitivement

### Lot D - Consolider Le Nouveau Code

- committer `backend/src/**`, `backend/tests/**`, `backend/templates/admin/**`, `backend/sql/editorial/**`, `frontend/tools/**`
- faire des commits courts par domaine

## Decision Technique Recommandee

La meilleure suite est :

1. `Lot A` tout de suite
2. `Lot B` ensuite, car c est la plus grosse source de bruit
3. `Lot C` et `Lot D` dans des branches / commits separes

Sans cette sequence, le depot restera trop sale pour des revues ou deploiements fiables.
