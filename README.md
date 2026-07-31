# Les Caramagnols
==================
==================

Site associatif qui combine un backend PHP procedural et un frontend moderne compile avec Vite. Le backend gere le routage, les gabarits et l'internationalisation serveur, tandis que le frontend fournit les interactions (menus responsive, traduction client) et les styles SCSS.

## 📁 Apercu
- 🔙 Backend PHP leger avec mini-routeur `backend/core/router.php` et systeme de gabarits `backend/templates`.
- 🔄 Internationalisation mixte : chargement serveur via `backend/core/lang_bootstrap.php` et API front a implementer `frontend/src/js/i18n.js`.
- 🌐 Frontend Vite (JS/SCSS) pour le comportement responsive, les menus et l'enrichissement UX.
- 📦 Donnees editoriales servies en PHP, moteur de recherche base sur un index JSON genere par CLI.

## ⚙️ Stack technique
- PHP 8.1+ (extensions standards) ; scripts CLI dans `backend/core/tools`.
- Node.js 18+ et npm pour le bundler Vite et les assets front.
- Base MySQL/MariaDB optionnelle (voir `backend/sql/install.sql`) pour la future fonctionnalite blog/commentaires.

## TODO
| Priorite | Tache | Statut |
| -------- | ----- | ------ |
| Haute    | Finaliser l'API de traduction cote front (`frontend/src/js/i18n.js`) et lier `backend/core/api/lang.php`. | Termine |
| Haute    | Documenter et securiser le chargement des variables sensibles (`backend/.env` → `app_config()`). | Termine |
| Moyenne  | Rationaliser les mixins et composants SCSS partages pour reduire la duplication. | A planifier |
| Moyenne  | Appliquer `stylelint`/`eslint` et corriger les avertissements restants avant le prochain build. | A faire |
| Basse    | Etendre la feuille de route blog (workflow, moderation, analytics) en decoupant par livrable. | A planifier |

Automatiser un contrôle de permissions .env dans la chaîne de déploiement et étendre le monitoring de configuration selon les besoins, mettre a jour readme.md
Etendre la liste des variables critiques si d’autres services sont requis (ex. SMTP sécurisé, clés OAuth), mettre a jour readme.md
Installer les dépendances puis lancer npm run test:run et composer test, mettre a jour readme.md
Décider si Thumbs.db doit être committé ou supprimé, mettre a jour readme.md
Etendre la couverture test selon vos priorités (ex. modules backend supplémentaires), mettre a jour readme.md

## 🌐 Divers
- cache simple pour les traductions et le manifest Vite afin d'eviter des lectures disque repetitives.
- Factorisation des doublons dans `frontend/src/js/menus.js` (listeners multiples sur `DOMContentLoaded`) pour limiter le travail DOM et les effets de bord.
- Documenté un fichier `.env` et centralisé la configuration (BASE_URL, credentials BDD, mail) pour simplifier le deploiement multi-environnement.
- Mise en place d'une tache `npm run lint` (ESLint/Stylelint) pour detecter les regressions avant le bundling final

## Installation rapide
1. Installer les dependances front :
   ```bash
   cd frontend
   npm install
   ```
2. (Optionnel) Provisionner la base :
   ```bash
   mysql -u <user> -p < backend/sql/install.sql
   ```
3. Dupliquer `backend/.env.example` en `backend/.env` puis ajuster `BASE_URL`, `DB_*` et `MAIL_*` selon votre environnement.

## 🛠️ Configuration (.env)
- `APP_ENV=development|production` pilote le niveau de verification. En production, l'absence de certaines variables bloque le bootstrap.
- `BASE_URL` et `DEFAULT_LANG` pilotent l'URL du site et la langue par defaut.
- Les sections `DB_*` et `MAIL_*` centralisent les identifiants base de donnees et SMTP.
- Les valeurs sont chargees au bootstrap (`backend/core/bootstrap.php`) via `load_env()` qui valide les clefs (`A-Z0-9_`), supprime les retours de ligne et expose les booleens (`true`/`false`).
- `app_config()` fournit un acces typé : par exemple `app_config('database.host')` ou `app_config('mail.smtp_password')` dans les scripts PHP.
- Ne commitez jamais votre `.env` : la version d'exemple (`backend/.env.example`) doit rester publique, mais le vrai fichier appartient au serveur.
- Verifiez les permissions (600) et gardez `.env` hors du dossier `public/` pour éviter toute fuite via le web serveur.
- Automatisation : `php backend/core/tools/check_env.php [--path=backend/.env] [--env=production]` vérifie les permissions, la localisation du fichier et la présence des variables critiques (erreurs si clés manquantes, avertissements sinon).

**Production**
- `APP_ENV=production` impose la présence de `DB_HOST`, `DB_NAME`, `DB_USER`, `MAIL_SMTP_HOST` et `MAIL_FROM_ADDRESS`. Le bootstrap renvoie HTTP 500 (ou lève une exception en CLI) si ces clés manquent.
- Ajoutez d'autres variables critiques dans `backend/core/bootstrap.php` si nécessaire.


## Developpement local
- **Frontend** : `cd frontend && npm run dev` (serveur Vite sur http://127.0.0.1:5173).
- **Backend** : `cd backend && php -S 127.0.0.1:8000 -t public public/dev-router.php`.
- Le proxy Vite relaie `/core/*` vers http://127.0.0.1:8000 afin que `core/api/lang.php` soit accessible en mode dev.
- Naviguer sur http://127.0.0.1:8000 et utiliser `?lang=en` ou `?lang=de` pour forcer une langue.

> Astuce : en mode dev, Vite ne pousse pas automatiquement les assets dans `backend/public`. Les includes PHP attendent le manifest genere par `npm run build`.

## Build & deploiement
```bash
cd frontend
npm run build      # genere dist/ avec manifest et assets versionnes
npm run postbuild  # recopie dist/assets et dist/.vite vers backend/public
```
Après validation locale, proposer le déploiement de `backend/public` (ainsi que
`backend/data/` si la recherche est utilisée). Une demande explicite distincte
reste obligatoire avant tout déploiement ou écriture distante.
   php -S 127.0.0.1:8000 -t backend/public backend/public/dev-router.php

 tuer le processus qui occupe le port 
   lister les ports : lsof -i
   tuer le processus du PID : kill -9 <PID>
   wsl : fuser -k 5173/tcp       fuser -k 5174/tcp        fuser -k 8000/tcp
   PowerShell :  netstat -ano | findstr :5173 pour récupérer le PID.
taskkill /PID <PID> /F pour le fermer, ex : taskkill /PID 17984 /F

```

### Publication GitHub

Les commandes suivantes sont des repères historiques. Elles ne valent pas
autorisation : un commit ou un push exige une demande explicite pour la tâche
courante.

```bash
Github : paulineBerg
git add .
git commit -m "Initial commit"
# creation du repo git remote add origin https://github.com/paulineBerg/lescaramagnols.com.git
git branch -M main
git push -u origin main
```

Ne pas utiliser de force-push comme procédure nominale. Toute réécriture
d'historique exige une demande séparée, une cible vérifiée et un plan de retour
arrière.

##  🧪 Tests
- **Frontend (Vitest)** :
  - installation (une seule fois) : `cd frontend && npm install`
  - exécution ponctuelle : `npm run test:run`
  - mode watch : `npm run test:watch`
  - un rapport de couverture est généré dans `frontend/coverage/`
- **Backend (PHPUnit)** :
  - installation : `composer install --working-dir=backend`
  - exécution : `composer test` (cf. script dans `backend/composer.json`) ou `vendor/bin/phpunit`
  - les tests se trouvent dans `backend/tests/`
- `php backend/core/tools/check_env.php` est un hook utile à exécuter dans vos pipelines CI/CD avant déploiement pour valider la configuration.
- (Optionnel) définissez une variable d’environnement `APP_ENV=testing` pour isoler les tests des environnements dev/prod.

## ⚙️ Scripts utilitaires
- `php backend/core/tools/generate_search_index.php` : construit `backend/data/search_index.json` et sa version minifiee.
- `php backend/core/tools/generate_favicon.php` : regenere les favicons a partir de `frontend/src/assets/images/structure/logo.(jpg|png)`.
- `php backend/replace_image_paths.php` : normalise les chemins d'images dans les fichiers de langues.

## Internationalisation
- Detection de la langue via URL, cookie ou en-tete navigateur `backend/core/lang_bootstrap.php`.
- Fonctions serveur `t()` disponibles dans les gabarits `backend/core/i18n.php`.
- Cote client, `frontend/src/js/i18n.js` prevoit l'appel `fetch('core/api/lang.php?lang=xx')` pour alimenter les elements `data-i18n` (endpoint a finaliser).

## Structure des dossiers
```
backend/
  config/         # Config globale, connexion BDD
  core/           # Bootstrap, routeur, outils CLI, i18n
  public/         # Point d'entree HTTP, assets exposes
  templates/      # Layouts et pages thematiques
  lang/           # Traductions PHP (fr/en/de)
  data/           # Fichiers derives (index de recherche)
frontend/
  src/            # JS, SCSS, images sources
  dist/           # Build Vite (genere)
```

### Propositions

#### Optimisation
- Remplacer les references d'images absolues par des imports Vite afin de profiter du hashing et d'eviter les avertissements de build, mettre à jour readme.md
- Mutualiser les mixins SCSS communes pour reduire la duplication et simplifier la maintenance., mettre à jour readme.md

#### Amelioration
- Planifier un nettoyage progressif des fichiers SCSS pour respecter les regles Stylelint (kebab-case, espacements, doublons).
- Documenter la convention de nommage CSS afin d'aligner les contributions futures., mettre à jour readme.md

#### Correction
- Supprimer ou encapsuler les appels `console.log` restants dans src/js/i18n.js et src/js/main.js., mettre à jour readme.md
- Lancer `stylelint --fix` sur les fichiers corrigibles et traiter manuellement les cas restants avant la prochaine release., mettre à jour readme.md

### 🔐 Securite
- Mettre en place une validation/echappement systematique sur les champs commentaires, tags et contenus multilingues pour limiter XSS., mettre à jour readme.md
- Durcir la configuration des cookies (`HttpOnly`, `SameSite=Strict`) lors de la selection de langue et prevoir des tokens CSRF pour le futur dashboard admin., mettre à jour readme.md
- Ajouter un pare-feu basique (rate limiting ou captcha) autour du formulaire de commentaires pour bloquer le spam., mettre à jour readme.md

### 🎯 Ajout de fonctionnalites
- Developper le module blog complet : themes fixes (villages, animations, story), categories enfants illimitees, tags et edition multilingue avec duplication d'articles.
- Fournir un dashboard admin protege (URL aleatoire + notification email) pour gerer articles, categories, tags et moderation des commentaires.
- Ajouter un moteur de recherche cote front qui consomme `backend/data/search_index.json` avec filtres par theme, categorie et langue.

## 📊 Blog et donnees
- Le script `backend/sql/install.sql` installe les tables utilisateurs, articles, commentaires et taxonomies.
- Les gabarits et menus existants imposent trois themes principaux (villages, animations, story) auxquels doivent se rattacher les contenus.
- Les fichiers de langues et l API de traduction fournissent un socle pour publier en plusieurs langues et aligner front/backend.

## 👥 Fonction de Blog Participatif a developper

### Feuille de route proposee
1. **Phase 0 – Preparation technique**
   - Finaliser le schema SQL (themes fixes, categories enfants uniques, table de tags sans doublons, table pivot articles/tags).
   - Ajouter les variables d environnement necessaires (URL dashboard, email admin, SMTP definitif) dans `.env` et `app_config()`.
   - Construire un jeu de donnees de demonstration et definir la strategie de migration/devops (dump, seeds, rollback).

2. **Phase 1 – MVP edition/articles**
   - CRUD complet sur les articles (brouillon, publication, duplication par langue en conservant la mise en forme).
   - Editeur modulaire (texte/image/tableau) avec styles de base (gras, italic, h1/h2/h3/p) et gestion des medias (200/400/600px + alt/titre obligatoires).
   - Gestion des themes et categories enfants (selection obligatoire d un theme principal, unicite des categories).

3. **Phase 2 – Participation et moderation**
   - Comptes auteurs invites (inscription, connexion, recuperation de mot de passe).
   - Workflow de validation (brouillon > revue > publie) avec notifications email et journal des revisions.
   - Module commentaires avec anti-spam (captcha ou rate limiting), moderation, et sanitisation systematique.

4. **Phase 3 – Experience lecteur**
   - Page blog publique avec filtres par theme, categorie et tags, recherche front sur `backend/data/search_index.json`.
   - Navigation multilingue synchronisee (langue par defaut issue de la barre, duplication d article automatisee).
   - Options newsletters / suivi thematique (opt-in par theme/auteur, exports mailing list).

5. **Phase 4 – Operations & SEO**
   - Planification des publications (cron ou planificateur interne) et historique des versions.
   - Audit SEO (schema.org, metas dynamiques, plan de site, URLs propres) et optimisation performance (lazy-loading).
   - Tableau de bord analytics (statistiques lectures, clics, inscriptions newsletter).

### Ameliorations transverses
- Factoriser les mixins SCSS et normaliser les classes (kebab-case) avant integration du blog pour limiter la dette CSS.
- Prevoir des tests automatise (PHPUnit pour le backend, Vitest/Playwright pour le front) couvrant creation/article/commentaire.
- Documenter les API et workflows (diagrammes de sequence, contrats JSON) pour favoriser l onboard des contributeurs.

### Jalons et tickets proposes
- **Milestone M0 – Socle technique** : tickets pour le schema SQL (B01), la configuration `.env` (B02) et les seeds de donnees (B03).
- **Milestone M1 – Edition** : tickets CRUD articles (B10), editeur modulaire (B11), gestion themes/categories (B12).
- **Milestone M2 – Participation** : tickets comptes auteurs (B20), workflow de validation (B21), module commentaires/anti-spam (B22).
- **Milestone M3 – Experience lecteur** : tickets page blog + filtres (B30), recherche front (B31), newsletter/suivi thematique (B32).
- **Milestone M4 – Operations & SEO** : tickets planification (B40), audit SEO/URL (B41), tableau de bord analytics (B42).
- Chaque ticket doit etre lie a des tests (backend/front) et a une checklist accessibilite/securite correspondant au jalon.

#### Tableau de suivi des milestones
- Creer un tableau Kanban par milestone (M0 -> M4) avec les colonnes : `Backlog`, `En cours`, `Revue`, `Pret pour release`, `Termine`.
- Chaque ticket Bxx est place dans le board du jalon correspondant et doit garder un lien vers la documentation README.
- Ajouter une checklist par carte : exigences fonctionnelles, tests (unitaires/integres/end-to-end), accessibilite, securite.
- Prevoir un point de synchronisation hebdomadaire pour passer en revue les colonnes `Revue` et `Pret pour release`.
- Archiver le board dans `Termine` une fois le jalon livre et reporter les enseignements retrospectives dans la section Contribution.

### Points de vigilance
- Securite : tokens CSRF, gestion stricte des sessions, droits granulaires (admin vs auteurs), sanitation HTML riche.
- Performance : cache des pages publiques, regeneration incremental du `search_index.json`, optimisations images.
- Accessibilite : navigation clavier, alternatives textuelles obligatoires, contrastes valides pour les nouveaux composants.

## Contribution
- Utiliser des commits au format Conventional Commits (`type(scope): message`), en francais ou anglais selon le contexte.
- Lancer `npm run lint` dans `frontend/` et executer `php -l` sur les fichiers PHP modifies avant revue.
- Formater le code JS/SCSS avec ESLint/Stylelint et respecter les helpers PHP existants (`env()`, `app_config()`, `t()`).
- Ouvrir une pull request avec un resume concis des changements, lister les tests executes et demander une revue a au moins une personne.
- Repondre aux retours en poussant des commits supplementaires plutot qu'en reecrivant l'historique partage.
