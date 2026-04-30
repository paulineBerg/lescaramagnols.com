# Modernisation Admin Editorial Et Navigation

Date : 2026-03-17

Ce document cadre le prochain chantier de modernisation du projet : rendre l'administration editoriale exploitable, fiabiliser le lien menus/pages/traductions et refondre le menu haut sans casser le front-office PHP.

References :
- `README_MODERNISATION_V1.md`
- `docs/archive/README_AUDIT_PLAN_ACTION_V1.md`
- `docs/README_REFONTE_LOT_C.md`
- `docs/README_CONSOLIDATION_LOT_D.md`
- `docs/pages-dynamiques.md`
- `backend/README_PUBLIC_ENTRYPOINTS.md`

## Statut 2026-03-18

Ce document contient encore une partie de journal de conception. L'etat courant a retenir est :
- le registre editorial public de pages ne porte plus que des `structured_page`
- le service `legacy_template` n'est plus actif dans le front-office pages
- `backend/templates/admin/menus.php` n'est plus un textarea JSON : le builder visuel serveur est la voie normale
- les formulaires admin `pages` et `menus` serialisent maintenant leur etat dans un champ JSON cache avant POST pour eviter les troncatures `max_input_vars`
- les mentions restantes de `legacy_template` plus bas sont historiques si elles ne sont pas explicitement marquees comme "etat courant"

Mise a jour 2026-04-16 (Lot C, isolation de la refonte) :
- les suppressions suivies de `backend/templates/pages/site/**`, `backend/config/menu_data.php` et des points d'entree admin obfusques relevent de la bascule vers `structured_page`, `NavigationRepository` et l'admin canonique
- ce perimetre doit etre documente et livre a part du nettoyage de depot ; le mapping de remplacement est centralise dans `docs/README_REFONTE_LOT_C.md`

Mise a jour 2026-04-16 (Lot D, consolidation du code neuf) :
- le bloc admin/editorial/navigation doit etre consolide comme domaine fonctionnel autonome, avec `backend/src/Admin/*`, `backend/src/Content/*`, `backend/src/Navigation/*`, `backend/templates/admin/*` et leurs tests associes
- l'ordre de commit et les README a maintenir sont centralises dans `docs/README_CONSOLIDATION_LOT_D.md`

Mise a jour 2026-03-19 (P1 en cours) :
- `backend/core/router.php` et `backend/core/menu_loader.php` sont des wrappers de compatibilite ; la logique metier est migree dans `backend/src/Http/LegacyRouteResolver.php` et `backend/src/Navigation/LegacyMenuRuntime.php`.
- `AdminController` delegue la normalisation des formulaires JSON caches a `backend/src/Admin/AdminSerializedFormNormalizer.php`.
- `AdminNavigationService` delegue le parsing d'action builder et le codec de chemins d'items a `backend/src/Admin/Navigation/*`.
- `AdminSettingsService` delegue la gestion des overrides de traductions a `backend/src/Admin/Settings/AdminTranslationSettingsManager.php`.
- un script de sauvegarde/restauration editorial est disponible : `backend/core/tools/editorial_backup_restore.php`.
- le tableau de bord admin est recentre sur les elements cles d'exploitation, avec un focus prioritaire sur la moderation des discussions en attente.
- la colonne de navigation gauche admin est fixe en desktop (hauteur viewport constante) pour garder une ergonomie identique sur pages/articles/discussions/menus/logs/settings.

Mise a jour 2026-03-20 (ticket W1-04 execute) :
- stabilisation du builder menus validee sur les cas systeme `footer_notice`, `banner`, `remonter`.
- couverture de non-regression renforcee :
  - `AdminSerializedFormNormalizerTest` couvre la fusion des champs systeme quand l'etat JSON serialize est incomplet,
  - `AdminNavigationServiceTest` verifie la purge du cache navigation apres sauvegarde.
- validation operationnelle : `tests/AdminSerializedFormNormalizerTest.php`, `tests/AdminNavigationServiceTest.php`, `tests/AdminControllerTest.php` -> verts.

Mise a jour 2026-03-20 (menu mobile) :
- rendu mobile aligne sur la logique de fusion homonyme : si un groupe et son premier lien enfant partagent le meme libelle, le lien est promu au niveau du groupe et le doublon enfant est retire.
- couverture de non-regression ajoutee dans `tests/MenusHeaderPartialTest.php`.

Mise a jour 2026-03-20 (menu desktop tactile + sous-menus) :
- les sous-menus desktop imbriques s'ouvrent maintenant sous leur parent (plus de decalage lateral penalissant le pointage).
- les interactions desktop restent hybrides : survol souris conserve + ouverture au tap/clic sur ecran tactile.
- couverture de non-regression frontend etendue dans `frontend/src/js/__tests__/menus.test.ts` (cas tap tactile desktop).

Mise a jour 2026-03-20 (editeur pages, iframe) :
- les regions `rich_text` acceptent maintenant les embeds `iframe` YouTube en mode admin (persistance stable apres sauvegarde et re-ouverture du formulaire).
- les URLs `youtube.com/embed/*` sont canonicalisees en `youtube-nocookie.com/embed/*` pour rester compatibles avec la CSP en place.
- les sources `iframe` hors liste de confiance sont supprimees au rendu (durcissement anti-injection).

Mise a jour 2026-03-21 (articles blog, publication planifiee) :
- ajout du statut `scheduled` dans l'admin articles avec date de publication programmee dediee.
- publication automatique sans cron : un article planifie devient visible sur le front (liste, detail, RSS, sitemap) des que sa date est atteinte.
- ajout d'une commande CLI `backend/core/tools/publish_scheduled_blog_articles.php` pour promouvoir les articles arrives a echeance en `published`, avec rappel du binaire PHP et de la ligne cron dans `Parametres > Observabilite ops`.

Mise a jour 2026-04-28 (taxonomie blog) :
- l admin articles n accepte plus de categorie ou tag libre.
- les categories, sous-categories, tags autorises, traductions et statuts SEO viennent de `backend/config/blog_taxonomy.php`.
- la categorie est obligatoire, la sous-categorie est optionnelle et depend de la categorie, les tags sont selectionnes par cases a cocher avec une limite de `3` a `5`.
- une sauvegarde article est refusee si la categorie manque, si un tag est inconnu, si plus de `5` tags sont envoyes ou si la sous-categorie ne correspond pas a la categorie.
- le diagnostic `php backend/core/tools/diagnose_blog_taxonomy.php` permet d identifier les variantes existantes avant migration.

Mise a jour 2026-04-28 (backups production) :
- ajout d'une section `Parametres > Sauvegardes` affichant le dossier cible, la retention, le script CLI et la commande cron recommandee.
- ajout de `backend/core/tools/backup_production.php` pour produire une archive du dossier backend prod et un dump SQL compresses, avec verrouillage, manifestes et refus d'ecrire dans le backend ou le webroot.
- la section admin permet de modifier le dossier racine, les dossiers fichiers/SQL/manifestes, la retention, les binaires CLI et la connexion SQL utilisee par le dump; le mot de passe SQL n'est jamais reaffiche et peut seulement etre remplace.
- la configuration peut aussi rester systeme/env (`PRODUCTION_BACKUP_ROOT`, `PRODUCTION_BACKUP_RETENTION_DAYS`, `PRODUCTION_BACKUP_TAR_BINARY`, `PRODUCTION_BACKUP_MYSQLDUMP_BINARY`) et n'expose pas de secret SQL dans l'admin.

Mise a jour 2026-04-28 (Cron Center) :
- ajout d'une section `Parametres > Cron Center` pour piloter des jobs PHP locaux stockes en SQL (`cron_jobs`) avec historique limite aux 100 dernieres executions par job (`cron_runs`).
- le cron OVH doit appeler un seul point d'entree : `php backend/core/tools/run_cron_center.php --quiet`; Cron Center decide ensuite quels jobs actifs doivent partir selon leur expression cron.
- les jobs par defaut sont actifs : publication des articles planifies, backup production, alertes logs et purge automatique des logs SQL.
- chaque job peut etre teste manuellement depuis l'admin ; l'action lance le script PHP autorise, journalise l'execution SQL et met a jour la derniere execution.
- les scripts administrables sont limites a une liste autorisee dans `backend/core/tools/*.php` (extensible par `CRON_CENTER_ALLOWED_SCRIPTS`), les arguments sont passes par JSON controle, et stdout/stderr/code retour sont conserves dans l'historique SQL.
- les evenements `cron.*` sont journalises via `AppEventLogger` et filtrables dans `Admin > Logs`.
- le push editorial `backend/tools/push-local-sql-to-ovh.sh --live` capture les reglages admin Cron/Sauvegardes avant et apres restauration SQL ; il echoue si les champs editables de `cron_jobs` ou la configuration de backup prod ont change.

Mise a jour 2026-03-21 (images editoriales V1) :
- admin articles : ajout d'une image de couverture (URL ou upload), avec metadonnees SEO (`alt`, `title`, `caption`, dimensions).
- admin pages : ajout d'une image SEO par langue (stockee en `translations[*].meta.image`) avec upload.
- admin pages : ajout d'une section dediee "medias partages" en tete de formulaire (hors traductions), stockee en `meta.shared_media`.
- upload medias partages : traitement serveur avec redimensionnement automatique (max 2048px) + conversion WebP, stockage mutualise dans `backend/public/uploads/editorial/media/YYYY/MM`.
- rendu front : la galerie `meta.shared_media` est injectee en haut de page (section hero) et reste independante des contenus traduits.
- stockage runtime des uploads dans `backend/public/uploads/editorial/**`, preserve en deploiement (scripts fast/release exclus de ce scope).
- rendu front enrichi (cartes blog + detail article + chroniques rattachees) et balises head `og:image` / `twitter:image`.

TODO V2 media :
- etudier un module multimedia dedie (images + videos + metadata + droits d usage) avec bibliotheque unifiee admin et API de selection cross-contenus.

## Taxonomie blog

L ecran admin `Articles` utilise une taxonomie fermee, definie dans `backend/config/blog_taxonomy.php`.

Regles de saisie :
- categorie obligatoire par liste deroulante
- sous-categorie optionnelle, filtree selon la categorie selectionnee
- tags par cases a cocher uniquement
- 3 a 5 tags autorises par article
- aucune creation automatique de categorie, sous-categorie ou tag depuis le formulaire

Validation serveur :
- refus si la categorie est absente ou inconnue
- refus si un tag n appartient pas au referentiel
- refus si plus de `5` tags sont envoyes
- refus si la sous-categorie ne depend pas de la categorie

Bonnes pratiques :
- garder peu de categories et peu de tags
- ajouter un nouveau tag seulement s il servira a plusieurs articles
- preferer un tag concret (`austin-seven`, `longbridge`, `moteur-a-series`) a une phrase longue
- verifier les variantes existantes avec `php backend/core/tools/diagnose_blog_taxonomy.php` avant une migration ou une fusion

Mise a jour 2026-03-21 (admin menus, suppression) :
- bouton `Supprimer` du builder menus protege par une confirmation explicite.
- comportement par defaut en cas d'annulation/fermeture de la popup : suppression refusee (`Non`).

Mise a jour 2026-03-21 (admin menus, labels multilingues) :
- le builder menus permet maintenant d'editer le libelle des items par langue (`fr/de/en`) avec choix d'une langue par defaut.
- la projection front respecte un fallback explicite : langue courante -> langue par defaut -> libelle principal -> `translationKey`.
- la persistance SQL navigation stocke les labels i18n (`label_default_language`, `label_translations_json`) via migration `006_navigation_item_label_i18n.sql`.

Mise a jour 2026-04-20 (admin pages, onglets de traduction) :
- l ecran `pages_edit` groupe maintenant `fr / en / de` en onglets au lieu d afficher toutes les langues a la suite.
- chaque onglet garde son propre bouton `Enregistrer la page`, mais la sauvegarde reste celle de la page complete pour conserver un seul contrat de persistance.
- l onglet actif est restaure cote client apres navigation ou sauvegarde pour eviter de revenir systematiquement sur `fr`.

Mise a jour 2026-04-22 (tuiles after_body) :
- ajout d un module admin `Tuiles` distinct de `Menus du site`
- stockage SQL dedie pour les groupes, les items, leurs traductions et les placements de page
- rattachement des groupes uniquement en `after_body`, avec ordre configurable dans l editeur de page
- chaque item peut maintenant choisir un format `small`, `medium`, `large` ou `rectangle`
- le format visuel par defaut est maintenant `rectangle`, avec image editoriale visible dans la tuile
- l ecran `Tuiles` reste la source de verite pour l edition complete d un groupe
- le catalogue `Tuiles` expose aussi une action `Dupliquer` pour cloner rapidement un groupe existant avant ajustements
- dans l ecran `Tuiles`, chaque item de groupe peut maintenant etre masque a la source via une case a cocher ; une tuile masquee reste editable mais ne sort plus au rendu public tant qu elle reste decochee
- l ecran `pages_edit` ne sert qu a rattacher un groupe a une page, definir son ordre, regler la visibilite locale d une tuile et, si besoin, remplacer sa cible par une page du site
- les surcharges plus anciennes de type route, URL ou textes traduits restent conservees si elles existent deja, mais ne sont plus exposees dans l UI simplifiee de `pages_edit`
- rendu front `windows10-classic` base sur les assets existants, avec grille dense type W10 cote serveur, hover leger seulement et pile verticale sur mobile
- en rendu public desktop, les groupes successifs partagent le meme maillage visuel afin qu une tuile du groupe suivant puisse combler l espace libre de la ligne precedente si sa taille le permet
- en rendu public, le libelle d une tuile `medium` ne doit pas etre tronque par `...`; le retour a la ligne est autorise pour afficher le titre complet dans la tuile
- une tuile qui pointe vers la page actuellement rendue est masquee automatiquement cote public
- script de migration legacy disponible : `php backend/core/tools/migrate_legacy_page_tiles.php` puis `--apply` apres backup SQL
- migration auto-retro normalisee sur `1` tuile par marque, triee par ordre alphabetique, avec ajout de `Citroën`

Mise a jour 2026-04-23 (tuiles, duplication) :
- le bouton `Dupliquer` du catalogue `Tuiles` recopie maintenant tout le groupe, y compris les items, leurs traductions, leurs cibles et leurs medias
- le groupe duplique est cree avec un titre suffixe en ` - copie`, puis ` - copie 2`, ` - copie 3`, etc.
- apres duplication, le catalogue `Tuiles` revient maintenant sur lui-meme avec un message de succes detaille (groupe source, groupe cree, titre et nombre de tuiles recopiees)
- en cas d echec, le catalogue affiche aussi un message d erreur detaille au lieu d une redirection muette

Mise a jour 2026-04-22 (mega menu desktop, sections longues) :
- dans le mega menu desktop, une meme section ne rend pas plus de `5` liens consecutifs par colonne.
- au dela de ce seuil, le rendu ouvre une colonne voisine dans le meme bloc au lieu d imposer un faux sous-groupe editorial.
- cette redistribution est pilotee par `NavigationViewModelBuilder` puis rendue par `menus_header.php`; le mobile conserve son arborescence normale.

Mise a jour 2026-04-22 (mega menu desktop, pleine largeur utile) :
- le mega menu desktop n aligne plus son contenu sous le seul bouton parent quand cela laisse un grand vide inutile a gauche.
- le panneau interne utilise maintenant toute la largeur disponible avant de retomber a la ligne.
- le rendu PHP n est plus borne artificiellement a `6` colonnes: il suit les unites de grille effectivement demandees par les sections mega menu.

## Objectif

Obtenir un socle moderne et coherent pour :
- creer et modifier les menus sans editer du JSON brut
- creer et modifier les pages sans multiplier les templates legacy
- lier proprement un item de menu a une page ou a une route
- gerer les traductions editoriales depuis un workflow unique
- moderniser le header et la navigation principale sur desktop et mobile

Le rendu doit rester cote serveur. Le projet ne doit pas devenir une SPA.

## Audit Precis De L Existant

## 1. Administration

Etat actuel :
- `backend/src/Admin/AdminController.php` gouverne le login, le dashboard, la liste/edition des pages, l'edition des menus et la deconnexion
- `backend/src/Admin/AdminPageService.php` porte le formulaire pages, l'edition par langue et le workflow `draft / published`
- l'ecran `backend/templates/admin/menus.php` passe maintenant par un builder visuel serveur, avec un mode expert JSON en lecture/depannage

Constat :
- l'admin est securisee, centralisee et couvre maintenant le cycle critique "creer une page / traduire / publier"
- le principal reste editorial est desormais la preparation de la decision F7 (retrait ecriture JSON) et la poursuite de la bascule SQL exploitee

## 2. Pages

Etat actuel :
- les pages editoriales publiques du registre sont maintenant rendues via `backend/templates/pages/dynamic.php`
- les pages dynamiques vivent dans `backend/data/pages.json`
- `backend/core/content/pages_loader.php` tolere encore `blocks` en compatibilite, mais le flux admin courant ecrit en `regions`
- `backend/src/Content/StructuredPageRenderer.php` supporte aujourd'hui `heading`, `rich_text` et `facts`
- `backend/src/Content/PageRepository.php` sait maintenant sauver le registre et resoudre une page par `slug` ou par `route`
- l'admin expose `/<base_path>/<ADMIN_LOGIN_PATH>/pages`, `/pages/new` et `/pages/{slug}`
- l'edition multi-langue passe par un POST compact `page_state_json` pour rester robuste quand le formulaire grossit

Constat :
- la strategie phase 3 est maintenant industrialisee pour un usage admin cote pages
- le registre public ne maintient plus de pages `legacy_template` en parallele
- la prochaine marche utile est surtout le pilotage plus fin des traductions et l'alignement UX avec le builder menus

## 3. Menus

Etat actuel :
- la lecture/ecriture passe par `backend/src/Navigation/NavigationRepository.php` selon `EDITORIAL_STORAGE` (`json`, `dual-write`, `sql`)
- `backend/data/menus.json` reste la source fichier en mode `json` et un snapshot d'import utile, sans etre forcement la source active
- `backend/config/menu_data.php` n'est plus la source canonique du workflow admin
- `backend/core/menu_loader.php` normalise maintenant le schema en sortie via `normalize_menu_config()`
- les items peuvent pointer vers une page metier via `page_slug`
- `backend/core/menu_loader.php` resout maintenant `page_slug -> route` depuis le registre et coupe les liens vers les brouillons
- le builder admin soumet maintenant un POST compact `builder_state_json` pour contourner `max_input_vars`
- en mode `sql`, un emplacement de navigation absent en base retombe desormais sur `backend/data/menus.json` pour ne pas faire disparaitre un bloc front-office critique comme le pied de page pendant une migration incomplète

Constat :
- la normalisation backend corrige deja un ecart legacy important entre `menu_droit` / `menu_gauche` et `menuDroit` / `menuGauche`
- le contrat metier `page_slug` existe et fonctionne, mais l'ergonomie d'edition reste insuffisante
- le builder serveur existe ; le travail utile restant est sa fiabilisation, l'edition par langue et la poursuite de la bascule SQL

## 4. Traductions

Etat actuel :
- les traductions d'interface restent dans `backend/lang/*.php`
- les contenus editoriaux peuvent etre traduits dans `backend/data/pages.json`
- le blog JSON porte aussi ses propres traductions par langue

Constat :
- la separation implicite "UI chrome en PHP / contenu editorial en JSON" est bonne
- elle n'est pas encore explicitement outillee dans l'admin
- il ne faut pas fusionner trop tot ces deux familles de traduction, sinon on recree de la confusion

## 5. Menu Haut

Etat actuel :
- rendu PHP concentre dans `backend/templates/partials/menus_header.php`
- structure desktop et mobile dupliquee dans le meme partial
- comportement frontend dans `frontend/src/js/menus.ts`
- styles legacy dans `frontend/src/scss/_menus.scss`

Constat :
- la navigation depend encore d'IDs et de structures DOM tres specifiques
- le desktop repose sur du hover et des classes `open`, ce qui est insuffisant pour l'accessibilite
- le CSS contient des positionnements rigides et des valeurs magiques comme `margin-left: 42%`
- la recherche, les langues et les reseaux sont encore hardcodes dans le partial

## Choix Recommandes

## Choix 1 - Garder Un Rendu Serveur PHP

Meilleur choix :
- conserver `backend/public/index.php` comme entree
- continuer a rendre HTML et navigation cote PHP
- utiliser TypeScript seulement pour l'interactivite et l'ergonomie

Pourquoi :
- c'est coherent avec le SEO, l'historique du site et l'architecture deja modernisee

## Choix 2 - Separer Nettement UI Et Contenu Editorial

Meilleur choix :
- garder `backend/lang/*.php` comme source de verite pour l'interface technique
- stocker les contenus traduisibles du site dans les donnees editoriales (`pages.json`, futur `menus.json` enrichi)

Pourquoi :
- un bouton, un message de formulaire ou un label systeme ne se gere pas comme un contenu de page
- cette frontiere simplifie la maintenance et evite les regressions i18n

## Choix 3 - Introduire Un Registre De Pages

Meilleur choix :
- ajouter une notion de page metier canonique, qu'elle soit legacy ou structuree
- faire reference aux pages via un `page_slug` ou un `page_id`, jamais uniquement via une URL brute

Pourquoi :
- c'est la cle pour relier proprement menus, pages, SEO et traductions
- cela permet de conserver les templates legacy sans les migrer tous d'un coup

## Choix 4 - Refondre Le Header Sur Un View Model Unique

Meilleur choix :
- calculer un seul arbre de navigation cote backend
- rendre desktop et mobile a partir de la meme source
- gerer les etats via boutons/disclosure accessibles, pas via hover seul

Pourquoi :
- un seul contrat de navigation reduit les divergences entre desktop, mobile et admin
- c'est le levier principal pour moderniser le menu haut sans refaire tout le site

## Choix 5 - Migrer L Editorial Vers SQL Sans Perdre La Notion De Section

Meilleur choix :
- migrer progressivement les donnees editoriales vers MariaDB/MySQL
- garder les repositories metier comme facade (`pages`, `navigation`)
- stocker chaque texte dans sa section logique de page, jamais dans un blob global sans contexte

Pourquoi :
- un texte editorial n'a de valeur que rattache a une zone metier : `hero`, `intro`, `body`, `left`, `right`, `bottom`, `footer`
- cela evite qu'un contenu saisi pour la colonne gauche se retrouve rendu dans le corps principal
- cela simplifie l'admin, les validations et les futures evolutions de layout
- cela permet une migration JSON -> SQL sans perdre le contrat semantique deja pose par `regions`

## Choix 6 - Remplacer Le JSON Des Menus Par Un Builder Visuel Serveur

Meilleur choix :
- garder un stockage canonique fichier ou SQL via `NavigationRepository`
- supprimer le JSON brut du parcours normal d'administration
- construire un builder HTML serveur avec amelioration progressive TypeScript
- garder un mode expert secondaire pour inspection ou depannage, jamais comme ecran principal

Pourquoi :
- la structure navigation actuelle est deja assez stable pour alimenter une UI metier
- les administrateurs doivent manipuler des items, des groupes et des blocs lateraux, pas une syntaxe JSON
- cela reste coherent avec le choix global du projet : rendu serveur, interactivite legere, pas de SPA admin

## Choix 7 - Adopter Un Mega Menu Moderne Type Prestashop

Meilleur choix :
- remplacer les sous-menus flottants legacy par un mega menu compact rendu cote serveur
- garder une barre principale simple en niveau 1 et ouvrir les groupes riches dans un panneau horizontal large
- piloter ce mega menu depuis l'admin avec stockage SQL, sans introduire de SPA

Pourquoi :
- c'est le meilleur compromis entre modernite, SEO, accessibilite et compatibilite avec le front-office PHP
- le parcours utilisateur gagne en lisibilite : moins de cascades imbriquees, plus de colonnes claires et de mises en avant
- cette structure correspond bien a un site editorial riche avec pages, rubriques et cartes visuelles laterales

## Choix 8 - Ajouter Une Section Parametres Securisee Dans L Admin

Meilleur choix :
- ajouter un ecran `/<base_path>/<ADMIN_LOGIN_PATH>/settings`
- permettre la gestion des parametres de connexion BDD : adresse, port, nom de base, identifiant, mot de passe
- permettre la gestion de la connexion admin : identifiant, mot de passe, rotation des secrets
- ecrire ces parametres dans la configuration runtime hors webroot, jamais dans le HTML public

Pourquoi :
- l'exploitation du site devient plus simple pour un administrateur non technique
- cela prepare proprement la bascule finale vers SQL comme stockage principal
- la gouvernance des acces admin doit etre centralisee dans un ecran dedie, pas dispersee entre fichiers et variables d'environnement
- cette section doit rester securisee : mot de passe admin hashé uniquement, mot de passe BDD masque dans l'UI et ecriture hors webroot

## Architecture Cible Recommandee

## 1. Pages : Un Registre Unique

Recommandation :
- faire evoluer `backend/data/pages.json` vers un vrai registre editorial
- garder un contrat de page public unique pour eviter deux services en parallele

Type retenu au 2026-03-18 :
- `structured_page`

Exemple cible :

```json
{
  "meta": {
    "version": 2
  },
  "pages": [
    {
      "slug": "association",
      "type": "structured_page",
      "status": "published",
      "layout": "standard_page",
      "route": "/association",
      "translations": {
        "fr": {
          "title": "L'association Les Caramagnols",
          "regions": {
            "hero": {
              "component": "heading",
              "title": "L'association"
            }
          },
          "meta": {
            "description": "Presentation de l'association"
          }
        }
      }
    }
  ]
}
```

Effet attendu :
- l'admin peut lister, filtrer et lier toutes les pages
- le front-office conserve ses routes actuelles

Regle a conserver en SQL :
- chaque texte doit rester rattache a sa section fonctionnelle
- une page structuree ne doit pas stocker son contenu comme un unique champ `content`
- la couche de persistence doit connaitre le `layout` et les sections autorisees pour ce layout

## 2. Menus : Un Schema Canonique Oriente Pages

Recommandation :
- garder `backend/data/menus.json` comme source principale
- enrichir les items pour pointer vers une page, une route, une URL externe ou un groupe
- ne plus faire du `chemin` brut l'unique contrat metier

Schema cible :

```json
{
  "meta": {
    "version": 2
  },
  "locations": {
    "remonter": {},
    "banner": {},
    "utility": [],
    "primary": [],
    "footer": [],
    "sideLeft": [],
    "sideRight": []
  }
}
```

Item recommande :

```json
{
  "id": "menu-auto-retro",
  "kind": "group",
  "label": {
    "text": "Auto retro",
    "translationKey": null
  },
  "target": {
    "pageSlug": null,
    "route": null,
    "url": null,
    "openInNewTab": false
  },
  "media": {
    "image": null
  },
  "accessibility": {
    "alt": "Auto retro",
    "title": "Auto retro"
  },
  "children": []
}
```

Valeurs `kind` recommandees :
- `page`
- `route`
- `external`
- `group`
- `content_card`

Compatibilite :
- `backend/core/menu_loader.php` doit continuer a lire l'ancien schema
- l'ecriture admin produit maintenant le schema canonique `v2`

Evolution recommandee pour l'edition visuelle :
- conserver `locations` comme source de verite
- etendre progressivement le contrat d'item pour supporter les blocs lateraux via `kind=content_card`
- reserver `content_card` aux emplacements `sideLeft` et `sideRight`
- continuer a utiliser `pageSlug` comme lien metier prioritaire pour les liens internes

Structure d'item recommandee pour un bloc lateral :

```json
{
  "id": "side-left-club",
  "kind": "content_card",
  "label": {
    "text": "Nos voitures"
  },
  "target": {
    "pageSlug": "association",
    "route": null,
    "url": null,
    "openInNewTab": false
  },
  "media": {
    "image": "/assets/images/accueil/mini.jpg"
  },
  "accessibility": {
    "alt": "Mini Austin",
    "title": "Nos voitures"
  },
  "content": {
    "text": "Découvrez les modèles du club"
  },
  "children": []
}
```

Ce choix permet :
- une edition visuelle des blocs gauche et droit sans inventer un deuxieme systeme
- une reutilisation du meme repository et de la meme validation serveur
- un apercu coherent entre l'admin et le rendu front-office

Extension recommandee pour un mega menu :
- garder `kind=group` pour les rubriques racines
- ajouter une couche de presentation optionnelle pour dire comment ce groupe se rend
- permettre un mode `link`, `dropdown` ou `mega` sans changer le contrat metier des cibles

Presentation recommandee pour un item racine :

```json
{
  "id": "menu-auto-retro",
  "kind": "group",
  "label": {
    "text": "Auto retro"
  },
  "target": {
    "pageSlug": null,
    "route": null,
    "url": null,
    "openInNewTab": false
  },
  "presentation": {
    "displayMode": "mega",
    "columnCount": 3,
    "menuTemplate": "prestashop_like"
  },
  "featured": {
    "title": {
      "text": "Les incontournables"
    },
    "text": {
      "text": "Decouvrir les modeles iconiques du club"
    },
    "image": "/assets/images/accueil/mini.jpg",
    "target": {
      "pageSlug": "association",
      "route": null,
      "url": null,
      "openInNewTab": false
    }
  },
  "children": []
}
```

Ce choix permet :
- un rendu moderne type Prestashop sans perdre la compatibilite avec les groupes actuels
- une edition admin des colonnes et de la carte mise en avant
- une projection unique desktop/mobile/admin a partir du meme repository

## 3. Persistance SQL Editoriale

Recommandation :
- preparer une migration vers MariaDB/MySQL, coherent avec `DB_*` et `DB_TABLE_PREFIX`
- ne pas brancher directement les controllers admin sur du SQL inline
- garder les contrats de repository et changer l'implementation de stockage sous ces contrats

Etat actuel utile a la migration :
- la configuration DB est deja presente dans `backend/config/config.php`
- un schema historique existe dans `backend/sql/install.sql`, mais il ne couvre pas les pages et menus editoriaux
- il n'existe pas encore de repository SQL pour les contenus editoriaux

Schema cible recommande :

```text
car_pages
- id
- slug unique
- type
- status
- route unique
- layout
- template
- created_at
- updated_at

car_page_translations
- id
- page_id
- locale
- title
- meta_description
- editor_mode
- created_at
- updated_at
- unique(page_id, locale)

car_page_translation_sections
- id
- page_translation_id
- section_key
- component_type
- sort_order
- payload_json
- created_at
- updated_at
- unique(page_translation_id, section_key, sort_order)

car_navigation_sets
- id
- code unique
- version
- created_at
- updated_at

car_navigation_items
- id
- navigation_set_id
- parent_id nullable
- sort_order
- kind
- display_mode nullable
- column_count nullable
- menu_template nullable
- page_id nullable
- route nullable
- url nullable
- open_in_new_tab
- label_text nullable
- label_translation_key nullable
- label_default_language nullable
- label_translations_json nullable
- image nullable
- alt_text nullable
- title_text nullable
- created_at
- updated_at

car_navigation_item_featured
- id
- navigation_item_id unique
- locale
- title nullable
- text nullable
- image nullable
- page_id nullable
- route nullable
- url nullable
- open_in_new_tab
- created_at
- updated_at
- unique(navigation_item_id, locale)
```

Point cle :
- la table `car_page_translation_sections` est la garantie que chaque texte reste dans la bonne section de page
- `section_key` doit reprendre les cles semantiques du layout : `hero`, `intro`, `body`, `after_body`, `left`, `right`, `bottom`, `footer`
- `payload_json` permet de garder la souplesse des composants (`heading`, `rich_text`, `facts`) sans casser le modele
- la navigation doit aussi porter son contrat de presentation : un item racine peut etre un mega menu sans cesser d'etre un `group`
- la table `car_navigation_item_featured` permet de gerer proprement la carte visuelle a droite du mega menu, avec traduction par langue

Ce qu il faut eviter :
- une table `pages.content` unique avec tout le HTML concatene
- une migration qui perd l'information de section lors de l'import depuis `regions`
- une bascule directe JSON -> SQL sans phase de verification

## 3. Traductions : Deux Couloirs Simples

Couloir 1 :
- UI systeme dans `backend/lang/*.php`
- gere par les developpeurs tant que les ecrans restent techniques

Couloir 2 :
- contenus editoriaux dans `pages.json` et `menus.json`
- geres depuis l'admin par onglets `fr / en / de`

Bon compromis :
- ne pas ouvrir des formulaires admin sur toute la couche `backend/lang/*.php` dans un premier temps
- commencer par l'edition des textes de pages et labels de menus

## 4. Parametres D Exploitation

Recommandation :
- ajouter une section `Parametres` dans l'admin pour piloter la configuration d'exploitation necessaire au site
- distinguer clairement les parametres systeme des contenus editoriaux

Parametres cibles :
- connexion BDD
  - `DB_HOST`
  - `DB_PORT`
  - `DB_NAME`
  - `DB_USER`
  - `DB_PASSWORD`
  - `DB_TABLE_PREFIX`
- connexion admin
  - identifiant admin
  - mot de passe admin
  - confirmation du mot de passe

Regles de securite :
- le mot de passe admin ne doit jamais etre stocke en clair
- le mot de passe admin doit etre hashé avec `password_hash()`
- le mot de passe BDD ne doit jamais etre reaffiche en clair apres sauvegarde
- la persistence doit viser un fichier de configuration hors webroot, ou un store systeme dedie
- l'ecran doit etre reserve a un niveau d'acces super-admin
- chaque changement de parametre sensible doit etre journalise

Ce qu il faut eviter :
- stocker le mot de passe admin dans `pages.json`, `menus.json` ou une table editoriale
- rendre les secrets modifiables sans re-authentification
- afficher ou renvoyer les secrets dans le HTML ou dans les logs applicatifs
- melanger les parametres systeme avec l'edition des pages et des menus

## 5. Admin : Ecrans Cibles

Minimum utile :
- `/<base_path>/<ADMIN_LOGIN_PATH>/pages`
  - liste, filtre, statut, langue disponible
- `/<base_path>/<ADMIN_LOGIN_PATH>/pages/new`
  - creation d'une page editoriale `structured_page`
- `/<base_path>/<ADMIN_LOGIN_PATH>/pages/{slug}`
  - edition meta, slug, statut, SEO, traductions
- `/<base_path>/<ADMIN_LOGIN_PATH>/menus`
  - builder visuel par emplacement et non plus textarea JSON
  - panneau principal avec tabs `utility / banner / primary / footer / sideLeft / sideRight`
  - liste arborescente des items avec tri et deplacement
  - panneau d'edition contextuel pour l'item selectionne
  - apercu desktop/mobile du header et apercu des blocs lateraux
  - gestion des blocs lateraux `sideLeft` et `sideRight`
  - edition des images, textes et liens cibles vers une page (`pageSlug`) ou une route
  - choix du mode de rendu des rubriques racines : `link / dropdown / mega`
  - edition des colonnes du mega menu
  - edition d'une carte mise en avant (image, texte, bouton, cible)
  - apercu simplifie du mega menu desktop et du drawer mobile
- `/<base_path>/<ADMIN_LOGIN_PATH>/translations`
  - vue de pilotage sur les langues manquantes des pages et des menus
- `/<base_path>/<ADMIN_LOGIN_PATH>/settings`
  - edition des parametres BDD : adresse, port, nom de base, identifiant, mot de passe, prefixe
  - edition de la connexion admin : identifiant, mot de passe
  - sauvegarde securisee hors webroot
  - affichage masque des secrets
  - journalisation des modifications sensibles

Choix ergonomique recommande :
- formulaires serveur classiques + rechargement HTML
- TypeScript uniquement pour drag and drop, duplication de bloc, tri, onglets langue, apercu et repli des panneaux
- un "mode expert" optionnel peut afficher le JSON canonique en lecture ou edition de secours, mais ne doit plus etre l'ecran par defaut

Il ne faut pas introduire une SPA admin complete pour ce besoin.

## 6. Navigation Haute : Cible UI

Refonte recommandee du header :
- une barre utilitaire pour reseaux, recherche et langues
- un bloc marque/banniere clairement separe
- une navigation principale sticky, compacte
- un mega menu horizontal type Prestashop pour les rubriques de niveau 1 qui le necessitent
- une variante mobile en drawer, alimentee par le meme arbre de navigation

Regles d'implementation :
- remplacer le hover seul par des boutons de disclosure avec `aria-expanded`
- supprimer les dependances a des IDs rigides quand un `data-*` suffit
- sortir la logique JS dans un module dedie type `header.ts`
- decouper le rendu PHP en partials specialises
- limiter les entrees racines a un petit nombre lisible et deleguer la profondeur au mega panneau
- preferer des colonnes simples, plus une carte mise en avant, plutot que des cascades flottantes imbriquees

Structure UX recommandee pour ce mega menu :
- niveau 1 : barre compacte avec `5 a 7` entrees racines maximum
- niveau 2 : panneau horizontal aligne sur le header
- contenu du panneau : `2 a 4` colonnes de liens + `1` carte mise en avant optionnelle
- une section desktop ne doit pas depasser `5` liens consecutifs par colonne; le surplus repart automatiquement dans une colonne voisine du meme bloc, sous un titre unique de section
- mobile : drawer avec accordions, gouverne par le meme arbre
- admin : meme view model que le front, avec apercu simplifie

Structure cible :
- `backend/templates/partials/header/utility.php`
- `backend/templates/partials/header/brand.php`
- `backend/templates/partials/header/navigation.php`
- `frontend/src/js/header.ts`
- `frontend/src/scss/_header.scss`
- `frontend/src/scss/_navigation.scss`

## Plan De Mise En Oeuvre Recommande

## Section A - Stabiliser Les Contrats De Donnees

Etat au 2026-03-17 :
- en place

Livrables :
- registre des pages unifie sur `structured_page`
- schema canonique des menus versionne
- repository PSR-4 pour pages et navigation

Implementation livree :
- `backend/src/Content/PageRepository.php`
  - lecture du registre `backend/data/pages.json`
  - schema courant `v2`
  - registre public unifie autour de `structured_page`
- `backend/src/Navigation/NavigationRepository.php`
  - schema navigation `v2`
  - conversion legacy <-> canonique
- `backend/core/content/pages_loader.php`
  - wrapper legacy branche sur `PageRepository`
- `backend/core/menu_loader.php`
  - wrapper legacy branche sur `NavigationRepository`

Contrat stabilise dans cette section :
- `backend/data/pages.json`
  - top-level `meta.version`
  - collection `pages`
  - chaque page declare `type`, `status`, `route`
  - etat courant : pages editoriales publiques en `structured_page`
- `backend/data/menus.json`
  - top-level `meta.version`
  - collection `locations`
  - emplacements stabilises : `utility`, `primary`, `footer`, `sideRight`, `sideLeft`, `banner`, `remonter`

Impact volontaire :
- le front-office continue d'utiliser les wrappers legacy
- l'admin menus ne manipule plus un textarea JSON comme workflow principal
- le stockage est deja pret pour une future UI pages/menus plus riche

Readmes a maintenir :
- `README_ADMIN_EDITORIAL_NAV_V1.md`
- `docs/pages-dynamiques.md`

## Section B - Industrialiser L Admin Editorial

Etat au 2026-03-17 :
- en place

Livrables :
- ecrans liste/edition pour les pages
- edition par langue
- workflow brouillon/publie
- lien menu <-> page par `page_slug`

Implementation livree :
- `backend/src/Admin/AdminPageService.php`
  - synthese liste pages
  - normalisation formulaire
  - edition par langue `fr / en / de`
  - sauvegarde du registre avec workflow `draft / published`
- `backend/src/Admin/AdminController.php`
  - routes admin pages branchees
  - ecrans `pages`, `pages_new`, `pages_edit`
- `backend/templates/admin/pages_list.php`
  - liste filtrable par statut, type, langue et recherche
  - les filtres de liste admin sont gardes en memoire en session jusqu a reinitialisation explicite
- `backend/templates/admin/pages_form.php`
  - edition multi-langue
  - support `regions` structurees ou `EditRegion*` legacy
  - plan visuel du `standard_page` dans l'ecran d'edition
  - ouverture de popups d'edition depuis chaque zone dessinee
  - rappel des correspondances `hero/intro/body/...` avec `EditRegion1..11` et `EditRegion9`
- `backend/core/router.php`
  - resolution par route depuis le registre
  - application effective du statut `draft / published` sur les pages enregistrees
- `backend/core/menu_loader.php`
  - resolution `page_slug -> route` cote front-office

Impact volontaire :
- le CRUD pages passe par des formulaires serveur classiques
- les pages structurees peuvent etre publiees sans toucher au code
- l'editeur montre maintenant ou chaque texte sera rendu dans le layout standard et ouvre chaque zone dans une popup dediee
- le footer editorial `EditRegion9` est aussi represente dans ce plan, car il est rendu dans le pied de page global
- l'ecran menus n'est plus un textarea JSON : il passe maintenant par un builder visuel serveur

Readmes a maintenir :
- `README_ADMIN_EDITORIAL_NAV_V1.md`
- `backend/README_PUBLIC_ENTRYPOINTS.md`

## Section C - Remplacer L Edition JSON Des Menus

Etat au 2026-03-17 :
- fait

Implementation livree :
- `backend/src/Admin/AdminNavigationService.php`
  - service metier du builder admin au-dessus de `NavigationRepository`
  - lecture du schema canonique versionne avec fallback legacy
  - actions serveur `ajouter / dupliquer / supprimer / monter / descendre / ajouter un enfant`
  - validation stricte a la sauvegarde, dont l'interdiction de cibler une page en brouillon
- `backend/templates/admin/menus.php`
  - ecran admin en deux colonnes : structure et edition contextuelle
  - tabs par emplacement `utility / primary / footer / sideLeft / sideRight`
  - edition distincte de la banniere et du bouton `remonter`
  - apercus simplifies desktop/mobile du header et des colonnes laterales
  - mode expert replie en lecture seule pour le JSON canonique
- `backend/src/Navigation/NavigationRepository.php`
  - support canonique du type `content_card`
  - preservation du texte editorial lateral via `content.text`
  - conversion legacy <-> canonique etendue pour `menuDroit` / `menuGauche`
- `backend/templates/partials/menus_fixes.php`
  - rendu front-office des cartes laterales avec image, titre, texte et lien optionnel

Impact volontaire :
- l'administration ne demande plus d'editer du JSON brut pour les menus
- les blocs gauche et droit sont traites comme des emplacements editoriaux de premier niveau
- les cartes laterales restent clairement distinguees des items de navigation classiques
- le builder fonctionne sans JavaScript via des soumissions serveur, tout en gardant un socle pret pour une amelioration progressive

Meilleure solution retenue :
- un builder visuel serveur, organise par emplacement
- une colonne "structure" avec cartes reorderables
- une colonne "edition" avec formulaire contextuel selon le `kind`
- un apercu de rendu simplifie du header et des blocs lateraux
- un mode expert replie pour consulter le JSON canonique sans en faire le workflow principal

Livrables :
- builder de menus par emplacement
- support des groupes, pages, routes et liens externes
- support du type `content_card` pour `sideLeft` et `sideRight`
- gestion admin des blocs gauche et droit comme emplacements editoriaux de premier niveau
- pour chaque bloc lateral : image, texte, lien vers une page, une route ou un lien externe
- apercu visuel desktop/mobile du menu haut
- apercu visuel des colonnes laterales
- validation serveur stricte du schema canonique

Decoupage recommande :
- C1 : creer un `AdminNavigationService` au-dessus de `NavigationRepository`
- C2 : rendre l'ecran `/<base_path>/<ADMIN_LOGIN_PATH>/menus` par tabs d'emplacements
- C3 : remplacer le textarea par une liste de cartes avec actions `ajouter / dupliquer / supprimer / monter / descendre`
- C4 : ajouter un panneau d'edition d'item selon `kind`
- C5 : ajouter un apercu simplifie du header desktop/mobile et des blocs lateraux
- C6 : releguer le JSON canonique dans un mode expert secondaire

Regles de conception :
- ne pas demander a l'admin de saisir de `chemin` interne si une page du registre peut etre selectionnee
- interdire la selection d'une page en brouillon comme cible active
- distinguer clairement les items de navigation (`page`, `route`, `external`, `group`) des cartes editoriales laterales (`content_card`)
- conserver une validation serveur stricte a la sauvegarde, meme si le drag and drop est gere en TypeScript
- garder un fallback sans JavaScript : boutons `monter / descendre / supprimer / ajouter`

Points ouverts pour la suite :
- remplacer les boutons de reordonnancement serveur par du drag and drop TypeScript sans perdre le fallback
- migrer a terme `menus.json` vers SQL en conservant le meme schema metier
- introduire le mode de rendu `mega` pour les groupes racines et son apercu admin

Readmes a maintenir :
- `README_ADMIN_EDITORIAL_NAV_V1.md`
- `backend/README_PUBLIC_ENTRYPOINTS.md`

## Section D - Refondre Le Menu Haut

Etat au 2026-03-17 :
- fait

Livrables :
- un view model de navigation unique
- nouveau header desktop/mobile
- accessibilite clavier et tactile
- styles responsive sans positionnement legacy rigide

Implementation retenue :
- `backend/src/Navigation/NavigationViewModelBuilder.php` est la source de projection unique pour le header, le mobile et l'aperçu admin
- `backend/core/menu_loader.php` expose `navigation_view_model()` pour servir le meme arbre de navigation au front-office
- `backend/templates/partials/menus_header.php` ne reconstruit plus plusieurs contrats legacy : il rend desktop et mobile depuis le meme view model
- `frontend/src/js/menus.ts` pilote les sous-menus au clic, la fermeture `Escape`, le clic exterieur et le toggle mobile
- `frontend/src/scss/_menus.scss` porte les styles responsives du nouveau header sans dependre du positionnement legacy rigide

Resultat attendu apres la section D :
- desktop et mobile consomment le meme arbre de navigation
- l'ouverture de sous-menu ne depend plus du hover
- le header mobile est un vrai rendu dedie, gouverne par le meme arbre backend
- l'aperçu admin des menus reprend la meme structure que le header public

Points ouverts pour la suite :
- rendre les labels de menus editables par langue sans casser le schema `v2`
- ajouter un drag and drop TypeScript dans le builder menus en gardant les boutons serveur en fallback
- preparer ensuite la bascule du stockage navigation vers SQL sans changer le contrat metier
- faire evoluer le header actuel vers un mega menu compact type Prestashop, pilote par le meme view model

Readmes a maintenir :
- `README_ADMIN_EDITORIAL_NAV_V1.md`
- `README_MODERNISATION_V1.md`

## Section E - Couvrir Et Fiabiliser

Etat au 2026-03-17 :
- fait

Livrables :
- tests PHP sur repositories pages/menus
- tests HTTP sur les nouvelles routes admin
- tests Vitest sur la navigation haute
- build CI valide sur le nouveau header

Implementation retenue :
- les repositories editoriaux `PageRepository` et `NavigationRepository` sont couverts par des tests PHP dedies et des regressions sur le view model de navigation
- `backend/src/Http/FrontController.php` porte maintenant la gouvernance HTTP testable hors du fichier `public/index.php`
- des tests d'integration HTTP couvrent la connexion admin, les redirections de routes protegees, les alias legacy et l'edition de page/menus via le vrai front-controller
- les tests Vitest de `frontend/src/js/menus.ts` couvrent maintenant le clic exterieur, la fermeture au `resize` et l'unicite du sous-menu ouvert
- la CI GitHub execute en plus un smoke test HTTP sur le header public et les routes admin critiques

Resultat attendu apres la section E :
- les routes admin critiques sont verifiees hors navigation manuelle
- le front-controller public est testable sans dupliquer sa logique dans les tests
- le header refondu est verifie en PHP, en Vitest et en CI
- les regressions sur les aliases admin legacy sont detectees automatiquement

Readmes a maintenir :
- `docs/archive/README_AUDIT_PLAN_ACTION_V1.md`
- `README_MODERNISATION_V1.md`

## Section F - Migrer L Editorial Vers SQL

Etat au 2026-03-17 :
- implemente avec activation conservative
- le code supporte `json`, `sql` et `dual-write`
- le stockage par defaut reste `json` tant que la bascule finale n'est pas explicitement decidee

Livrables :
- couche DB commune pour MariaDB/MySQL
- repositories SQL pour pages et navigation
- schema SQL editorial versionne
- commande d'import `pages.json` -> SQL et `menus.json` -> SQL
- mode de stockage configurable `json | sql | dual-write`
- validation stricte que chaque texte reste dans la bonne section de page

Ordre recommande :
- F1 : ajouter une couche infrastructure SQL commune
- F2 : creer le schema `pages / translations / sections / navigation`
- F3 : importer les donnees JSON existantes sans perte de `section_key`
- F4 : brancher les services admin sur les repositories SQL
- F5 : activer un mode `dual-write`
- F6 : basculer progressivement la lecture du front-office vers SQL
- F7 : retirer ensuite l'ecriture JSON quand la bascule est stable

Implementation retenue :
- couche DB commune via `backend/src/Database/DatabaseConfig.php`, `backend/src/Database/PdoConnectionFactory.php`, `backend/src/Database/EditorialDatabase.php` et `backend/src/Database/EditorialSchemaManager.php`
- schema SQL versionne via `backend/sql/editorial/001_editorial.sql`
- `backend/src/Content/PageRepository.php` et `backend/src/Navigation/NavigationRepository.php` restent les facades de reference, avec implementations `json`, `sql` ou `dual-write`
- `backend/src/Content/SqlPageStore.php` et `backend/src/Navigation/SqlNavigationStore.php` portent les repositories SQL
- import JSON -> SQL via `backend/src/Editorial/EditorialImportService.php` et `composer editorial-import-sql`
- validation stricte des sections de page via `backend/src/Content/EditorialSectionValidator.php`

Regles de stockage retenues :
- `json` : lecture + ecriture fichier, comportement legacy stable
- `dual-write` : lecture JSON, ecriture SQL puis JSON pour fiabiliser la transition
- `sql` : lecture + ecriture SQL

Validation sectionnelle :
- les regions semantiques autorisees viennent de `StandardPageLayout::semanticSlots()`
- les blocs legacy autorises restent `EditRegion1..EditRegion12`
- une sauvegarde ou un import SQL echoue si une section inconnue essaye de changer de zone

Statut des etapes :
- [x] F1 : couche infrastructure SQL commune
- [x] F2 : schema `pages / translations / sections / navigation`
- [x] F3 : import des donnees JSON existantes sans perte de `section_key`
- [x] F4 : services admin branches sur les repositories storage-aware
- [x] F5 : mode `dual-write`
- [x] F6 : lecture front-office en `sql` disponible et verifiee localement
- [ ] F7 : suppression de l'ecriture JSON, volontairement differee apres stabilisation

Preparation decision F7 (etat courant) :
- [x] criteres techniques explicites poses (storage SQL stable + scheduler alertes logs + preuves runbook J+1/J+7).
- [x] garde-fou rollback documente (retour `dual-write` si anomalie post-bascule).
- [ ] decision GO finale F7 a prononcer apres fenetre d'observation exploitation complete.

Readmes a maintenir :
- `README_ADMIN_EDITORIAL_NAV_V1.md`
- `README_MODERNISATION_V1.md`
- `backend/README_PUBLIC_ENTRYPOINTS.md`

## TODO Prioritaires

- implementer un mega menu moderne type Prestashop, configurable depuis l'admin
- stocker en SQL la presentation du mega menu, ses colonnes et sa carte mise en avant
- ajouter une section `Parametres` dans l'admin pour gerer la connexion BDD et la connexion admin
- ecrire ces parametres sensibles dans une configuration runtime securisee hors webroot
- remplacer les boutons de reordonnancement du builder menus par du drag and drop TypeScript avec fallback serveur
- etendre les tests HTTP pour couvrir le header desktop/mobile et les chemins de langue
- continuer la reduction du legacy autour des partiels de navigation restants (`menu3`, blocs lateraux, footer)
- preparer la decision finale de bascule par defaut entre `json` et `sql`
- planifier la suppression du mode ecriture JSON seulement apres verification d'exploitation

## Definition De Reussite

Le chantier sera considere reussi quand :
- un administrateur peut creer une page, la traduire et la publier sans toucher au code
- un item de menu peut cibler une page par identifiant metier et non par URL recopiere
- desktop et mobile consomment le meme arbre de navigation
- le menu haut est accessible clavier et tactile
- les templates PHP legacy encore utiles restent compatibles pendant la transition
