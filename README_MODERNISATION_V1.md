# Plan De Modernisation Du Projet

Date : 2026-03-17

Ce document propose une modernisation progressive du projet en conservant le rendu serveur actuel des pages.

Reference :
- `docs/archive/README_AUDIT_COMPLET_V1.md`
- `docs/archive/README_AUDIT_PLAN_ACTION_V1.md`
- `README_ADMIN_EDITORIAL_NAV_V1.md`
- `README_TRANSITION_V1_S1_S8.md` (plan operationnel, tickets S1, trajectoire 8 semaines)

## Note 2026-03-18

Mise a jour importante sur le volet pages editoriales :
- le runtime public de pages n'exploite plus de `legacy_template`
- le registre editorial public est maintenant unifie autour de `structured_page`
- les formulaires admin `pages` et `menus` serialisent maintenant leur etat dans un champ JSON cache pour ne plus dependre de `max_input_vars`
- les mentions de `legacy_template` plus bas servent d'historique de conception si elles n'ont pas encore ete reecrites

## Avancement Verifie Au 2026-03-17

Phase 0, phase 1 admin/RSS, phase 3 contenu et phase 4 blog/outillage realises :
- installateurs web supprimes
- installation hors HTTP documentee
- `check-i18n` repare puis valide
- bootstrap i18n converge sur une seule logique
- valeurs admin de demonstration retirees
- front-controller aligne sur de vrais statuts HTTP `404`
- route admin canonique decouplee du dossier public legacy
- RSS et admin passent maintenant par une gouvernance HTTP commune
- tests ajoutes sur les nouvelles briques `AdminRouteResolver` et `RssFeedService`
- Vite est maintenant centralise cote PHP dans `backend/src/Assets/ViteAssetManager.php`
- le build frontend publie automatiquement vers `backend/public/` avec purge des anciens bundles hashés
- la CI verifie le build frontend publie
- la configuration PHPUnit est alignee sur le schema courant sans deprecation runner
- un point d'entree root `./dev.sh` lance maintenant le workflow local PHP + Vite avec verification de ports et arret propre
- la phase 3 de contenu/templates est engagee avec un layout standard factorise et un schema JSON a regions semantiques
- le blog est maintenant explicite comme MVP JSON `experimental`
- une persistance JSON unique est activee dans `backend/data/blog`
- l'ecriture blog passe par une API admin canonique et securisee
- le logging est maintenant branche sur auth admin, sauvegarde menus et ecriture blog
- des tests utiles couvrent maintenant les endpoints admin critiques et l'API blog
- les sections A, B, C et D de la phase 5 sont en place avec un registre de pages `v2`, un builder menus visuel et un header desktop/mobile aligne sur un view model unique
- la section E de la phase 5 est en place avec un front-controller testable, des tests HTTP admin, des tests Vitest et un smoke test CI du header
- le contrat legacy de pages (`legacy_template`, champ `template`) a ete retire du registre editorial public et de l'admin pages

Reste prioritaire ensuite :
- preparer la future migration SQL des contenus editoriaux
- continuer la reduction progressive de `backend/core/*`
- preparer la decision F7 (retrait ecriture JSON) apres stabilisation d'exploitation

## Objectif

Moderniser le projet sans casser :
- le rendu HTML cote serveur
- la structure de pages existante
- le SEO naturel des pages PHP
- le contenu et les URLs publiques

Le projet ne doit pas devenir une SPA.

## Choix Cibles Recommandes

## Choix 1 - Conserver un rendu serveur PHP

Meilleur choix :
- garder PHP comme moteur principal de rendu HTML
- conserver les templates `backend/templates/pages/**`
- faire evoluer le socle, pas rebasculer vers React, Vue, Next ou Nuxt

Pourquoi :
- le site est deja massivement structure autour de templates PHP
- les pages, menus et assets sont encore fortement couples au rendu serveur
- une migration SPA couterait cher et n'apporterait pas assez de valeur a court terme

## Choix 2 - Garder Vite comme pipeline d'assets

Meilleur choix :
- conserver Vite pour JS, CSS, TypeScript, tests et build
- utiliser Vite pour les bundles applicatifs
- eviter d'en faire la couche de rendu de l'application

Pourquoi :
- Vite est deja en place
- la toolchain frontend est saine
- le besoin principal est l'asset pipeline, pas un framework frontend complet

## Choix 3 - Une seule logique de bootstrap

Meilleur choix :
- faire converger tout le trafic public vers un bootstrap commun
- reduire les scripts publics "a cote"
- centraliser securite, i18n, config, logs et reponse HTTP

Pourquoi :
- aujourd'hui la surface publique est trop heterogene
- c'est le meilleur levier pour fiabiliser le projet sans reécriture massive

## Choix 4 - Une seule source de verite pour l'i18n

Meilleur choix :
- garder `backend/lang/*.php` comme source de verite
- exposer ces traductions au frontend via l'API JSON
- faire converger la resolution de langue sur `backend/src/I18n/*`

Pourquoi :
- le site rend deja les traductions cote PHP
- le frontend a seulement besoin d'une projection JSON du meme contenu
- cela evite de dupliquer les fichiers de langue

## Choix 5 - Stabiliser les images de contenu

Meilleur choix :
- conserver des chemins publics stables pour les images de contenu dans `/assets/images/...`
- ne pas imposer un hash Vite sur toutes les images historiques
- reserver le manifest Vite aux bundles JS/CSS et aux assets techniques modernes

Pourquoi :
- les templates PHP referencent deja massivement `/assets/images/...`
- une migration complete vers des imports Vite casserait beaucoup de templates legacy
- la valeur est faible par rapport au cout

## Choix 6 - Ne pas faire de "big bang rewrite"

Meilleur choix :
- modernisation par couches
- convergence progressive legacy vers `backend/src/`
- maintien du rendu existant pendant toute la transition

Pourquoi :
- le projet a beaucoup de contenu
- la dette principale est structurelle, pas un simple probleme de syntaxe

## Architecture Cible

## Cible Backend

- `backend/public/index.php`
  - point d'entree web principal
- `backend/src/`
  - couche applicative moderne
  - Request, Response, i18n, securite, logging, services
- `backend/core/`
  - zone transitoire legacy
  - progressivement reduite
- `backend/templates/`
  - rendu HTML serveur conserve
- `backend/data/`
  - contenu dynamique et index
- `backend/public/assets/`
  - sortie de build et assets publics stabilises

## Cible Frontend

- `frontend/src/js/`
  - comportements UI et modules applicatifs
- `frontend/src/scss/`
  - styles
- `frontend/src/assets/`
  - source d'assets buildes
- Vite
  - build JS/CSS
  - dev server en mode explicite et documente

## Contrat Front/Back Recommande

- HTML : rendu par PHP
- JS/CSS : livres par Vite via manifest en production
- images de contenu : chemins publics stables
- i18n : source PHP, projection JSON vers le frontend

## Ce Qu Il Ne Faut Pas Faire

- ne pas migrer vers une SPA
- ne pas reécrire tout le site en une fois
- ne pas convertir tous les templates en composants frontend
- ne pas hash-er de force toutes les images historiques
- ne pas garder des scripts d'installation accessibles publiquement

## Roadmap De Modernisation

## Phase 0 - Securiser Et Stabiliser

Objectif :
- retirer les risques majeurs sans changer le rendu

Travaux :
- sortir les installateurs du webroot
- corriger les endpoints publics legacy non harmonises
- corriger `composer check-i18n`
- nettoyer les valeurs de demo admin
- clarifier l'admin public reel

Checklist :
- [x] `backend/public/installsql.php` n'est plus publiquement accessible
- [x] `backend/public/assets/install.php` n'est plus publiquement accessible
- [x] `composer check-i18n` passe
- [x] aucune valeur admin de demo n'est exploitable en production
- [x] les redirections admin legacy inutiles sont supprimees ou documentees

## Phase 1 - Unifier Le Socle Technique

Objectif :
- faire converger le projet vers un seul coeur technique

Travaux :
- unifier la resolution de langue
- unifier le chargement du manifest Vite
- centraliser les helpers d'assets
- reduire la logique globale dans `core/*`
- faire de `backend/src/` la couche de reference

Checklist :
- [x] une seule logique de resolution de langue est utilisee
- [x] un seul mecanisme de lecture du manifest Vite est utilise
- [x] `scripts_head.php` ou equivalent ne duplique plus la logique helper
- [x] les nouvelles evolutions backend passent par `backend/src/`
- [x] `core/*` est clairement identifie comme legacy ou transitoire

## Phase 2 - Stabiliser Le Workflow De Dev Et De Build

Objectif :
- rendre le developpement local predicible

Travaux :
- definir clairement le mode dev
- soit integrer un vrai mode Vite dev
- soit documenter un mode "build local obligatoire"
- automatiser la copie et la purge des artefacts
- fiabiliser la CI autour des sorties attendues

Meilleur choix :
- supporter explicitement deux modes
  - mode dev avec serveur PHP + Vite dev server
  - mode build local avec manifest genere

Checklist :
- [x] le comportement dev est documente sans ambiguite
- [x] le rendu en dev fonctionne sans manipulation cachee
- [x] les anciens bundles sont purges automatiquement
- [x] `backend/public/assets` n'accumule plus les anciens fichiers hashes
- [x] la CI verifie le build frontend attendu

## Phase 3 - Rationaliser Le Contenu Et Les Templates

Objectif :
- reduire la dette structurelle sans casser le rendu

Travaux :
- factoriser les partials repetitifs
- deplacer le contenu dynamique neuf vers JSON ou structures de donnees
- garder les pages historiques en PHP tant qu'elles sont stables
- etablir une convention claire pour les nouvelles pages

Meilleur choix :
- ne pas migrer tout le legacy
- definir une regle simple :
  - ancien contenu stable : reste en template PHP
  - nouveau contenu repetitif ou editable : va dans `backend/data/`

Checklist :
- [x] une convention "ancien vs nouveau contenu" est documentee
- [x] les nouvelles pages ne repliquent pas les anti-patterns historiques
- [x] les partials dupliques sont identifies et factorises
- [x] les images de contenu restent servies via chemins stables

## Phase 4 - Finaliser Les Fonctions Annexes

Objectif :
- transformer les modules partiels en fonctions claires

Travaux :
- statuer sur le blog
- finaliser ou retirer les endpoints inacheves
- brancher le logging de facon plus uniforme
- completer les tests sur les points d'entree critiques

Meilleur choix pour le blog :
- court terme : assumer un blog JSON `experimental`, persistant et limite au besoin editorial reel
- moyen terme : ne migrer vers MySQL que si le volume ou le workflow l'impose vraiment

Choix recommande pour la persistance :
- si le blog doit vraiment etre administre : MySQL unique via backend
- sinon : JSON structure tant que le besoin reste editorial et faible volume

Checklist :
- [x] le statut technique du blog est explicite
- [x] un seul mode de persistance est retenu
- [x] les endpoints admin critiques sont testes
- [x] la journalisation est branchee sur les zones sensibles

## Phase 5 - Industrialiser L Editorial Et La Navigation

Objectif :
- rendre l'administration utile au quotidien pour les contenus, les menus et les traductions
- moderniser le menu haut sans casser le rendu serveur

Reference detaillee :
- `README_ADMIN_EDITORIAL_NAV_V1.md`

Travaux :
- introduire un registre de pages manipulable par l'admin
- faire evoluer `menus.json` vers un schema canonique oriente pages
- remplacer le textarea JSON des menus par un builder progressif
- distinguer clairement traductions d'interface et traductions editoriales
- refondre le header desktop/mobile autour d'un view model unique

Meilleur choix :
- rester en formulaires serveur progressifs pour l'admin
- reserver TypeScript a l'ergonomie des builders et du header
- conserver seulement les pages legacy importantes hors registre editorial tant qu'elles ne justifient pas une migration

Checklist :
- [x] un registre de pages `v2` unifie maintenant les pages editoriales publiques sur `structured_page`
- [x] un schema navigation `v2` versionne est pose cote stockage
- [x] une page peut etre creee et publiee depuis l'admin sans creation de template PHP ad hoc
- [x] un item de menu peut cibler une page via `page_slug`
- [x] les labels de menus sont editables par langue
- [x] le menu haut desktop et mobile consomment le meme arbre de navigation
- [x] le header n'utilise plus de hover comme seul mecanisme d'ouverture
- [x] le front-controller et les routes admin critiques sont couverts par des tests HTTP
- [x] la CI verifie le header public apres build
- [x] une couche SQL editoriale versionnee existe pour pages et navigation
- [x] une commande d'import JSON -> SQL est disponible
- [x] les modes `json`, `dual-write` et `sql` sont supportes par les repositories editoriaux
- [ ] l'ecriture JSON n'est pas encore retiree par defaut

Decision F7 (retrait ecriture JSON) :
- pre-requis formels : `EDITORIAL_STORAGE=sql` stable en exploitation, scheduler alertes logs actif, cycle runbook J+1/J+7 archive sans anomalie critique.
- execution ciblee : bascule d'abord en preprod sur une fenetre d'observation dediee, puis production avec rollback documente (retour `dual-write`).

## Meilleurs Choix Par Sujet

## Rendu

Choix recommande :
- PHP server-rendered

Alternative non recommandee maintenant :
- SPA React/Vue

## Assets JS/CSS

Choix recommande :
- Vite + manifest en prod

## Images de contenu

Choix recommande :
- chemins publics stables dans `/assets/images/...`

Alternative a eviter :
- migration totale et immediate vers imports Vite hashes

## I18n

Choix recommande :
- fichiers `backend/lang/*.php` comme source de verite
- API JSON derivee de ces memes fichiers

## Admin

Choix recommande :
- admin derriere bootstrap commun
- chemin configurable
- aucune valeur de demo exploitable
- stockage editorial progressif via `json`, `dual-write`, `sql`

Choix retenu pour l'editorial :
- garder `json` comme mode par defaut tant que l'exploitation n'a pas valide la bascule
- utiliser `composer editorial-import-sql` pour synchroniser les donnees existantes
- valider ensuite `EDITORIAL_STORAGE=dual-write` puis `EDITORIAL_STORAGE=sql`

## Blog

Choix recommande :
- le presenter comme un MVP JSON editorial, pas comme un CMS complet

## Qualite

Choix recommande :
- garder PHPUnit + PHPStan + PHPCS + Vitest + ESLint + Stylelint
- ajouter des tests d'integration sur les points d'entree publics
- garder un smoke test HTTP en CI pour les routes publiques et admin critiques

## Checklist De Lancement De Chantier

- [ ] valider que le projet reste server-rendered
- [ ] valider qu'aucune migration SPA n'est lancee
- [ ] valider la source de verite i18n
- [ ] valider le contrat dev/prod Vite
- [ ] valider la politique d'assets de contenu
- [ ] valider la trajectoire blog
- [ ] valider la suppression des installateurs publics
- [ ] prioriser les travaux Phase 0 et Phase 1 avant toute refonte cosmetique

## Ordre Recommande Des Travaux

1. Securite et surface publique
2. Outillage casse et coherence i18n
3. Contrat Vite dev/prod
4. Unification progressive du backend moderne
5. Rationalisation templates et contenu
6. Finalisation blog/admin/outillage secondaire

## Definition De Reussite

La modernisation sera reussie si :
- le rendu serveur est intact
- le workflow de dev est clair
- le projet n'expose plus de scripts dangereux
- le backend a un coeur technique plus coherent
- le frontend reste un pipeline d'assets moderne, pas une couche de rendu concurrente
- les nouvelles evolutions peuvent etre faites sans replonger dans les duplications legacy
