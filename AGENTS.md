# Caramagnols Workspace AGENTS

Version de reference: 2026-04-22

Ce fichier est la source de verite pour le depot `/home/surfacepro8/www/caramagnols`.
Son but est de fixer des regles communes de developpement, d'architecture, de langage, de verification et de documentation, a partir des conventions reelles du projet.

## 0. Mode d'emploi rapide

Ordre de travail recommande:
- lire ce fichier avant toute intervention, puis lire les documents de reference utiles au domaine touche
- identifier la nature du changement: code, contenu editorial, media, stockage, build, deploiement ou exploitation
- reperer la source canonique avant d'ecrire: `backend/src/` pour la logique moderne, `frontend/src/` pour les assets et interactions, `backend/data/pages.json` comme registre de travail/versionnement editorial, SQL comme source active quand `EDITORIAL_STORAGE=sql`
- ne modifier que le perimetre necessaire; ne pas melanger code produit, contenu editorial, artefacts generes et operations de production dans un meme passage sans raison explicite
- pour une modification editoriale en mode SQL actif, faire un backup adapte, importer la correction en SQL via les repositories/outils prevus, regenerer l'index de recherche et verifier le rendu public cible
- pour un media public durable, versionner la source dans `frontend/src/assets/images/**`, produire `jpg` et `webp`, publier vers `backend/public/assets` via le pipeline, puis verifier dimensions, droits, sources et rendu
- terminer par les validations adaptees au risque: JSON, PHP, frontend, index, build, smoke HTTP ou verification manuelle ciblee
- signaler explicitement toute divergence restante entre JSON, SQL, prod, index ou assets publies

## 1. Portee et autorite

Regles de gouvernance:
- ce `AGENTS.md` racine est le document maitre du depot
- tout `AGENTS.md` ajoute dans un sous-dossier doit relayer ce fichier racine et non redefinir des regles contradictoires
- en cas de doute, la convention deja en place dans le code et la doc du depot prime sur une preference personnelle

## 2. Contexte reel du projet

Stack observee:
- backend PHP rendu serveur
- frontend Vite 7 avec JS/TS partiel + SCSS
- tests backend via PHPUnit 10
- analyse statique backend via PHPStan
- style backend via PHPCS
- lint/tests frontend via ESLint, Stylelint et Vitest
- publication frontend vers `backend/public/` sans runtime Node en production

Versions et prerequis:
- PHP `8.1+`
- Node recommande: `22.22.1` via `.nvmrc`
- Node accepte par le projet: `>=20.19.0 <25`
- Composer et npm requis

Contrainte environnement locale:
- le depot est maintenu sous WSL dans `/home/...` et doit y rester
- eviter de deplacer le depot sous `/mnt/*`

## 3. Zones du depot et discipline d'ecriture

Le depot entier est lisible.
Le depot entier est techniquement modifiable, mais certaines zones ne doivent pas etre touchees par defaut sauf besoin explicite ou tache d'exploitation ciblee.

Zones normalement modifiables pour du code produit:
- `backend/src/`
- `backend/core/` uniquement pour wrappers legacy, bootstrap commun ou outillage deja etabli
- `backend/templates/`
- `backend/config/` hors secrets et overrides locaux
- `backend/lang/`
- `backend/tests/`
- `backend/sql/`
- `frontend/src/`
- `frontend/tools/`
- `frontend/*.config.*`
- `docs/`
- `README*.md`
- `.github/`
- `Makefile`
- `dev.sh`

Zones a ne pas modifier sans raison explicite:
- `backend/vendor/`
- `frontend/node_modules/`
- `frontend/dist/`
- `backend/public/.vite/`
- `backend/public/assets/` sauf fichiers suivis explicitement (`index.php`, `rss.php`)
- `backend/public/tarteaucitron/`
- `backend/var/`
- `backend/data/logs/`
- `backups/`
- `.ops-sync/`
- `backend/.env`
- `backend/config/database.override.php`
- `backend/config/admin.override.php`
- `backend/config/site.override.php`

Regles associees:
- ne pas editer un artefact genere si la source canonique existe ailleurs
- ne pas toucher aux secrets ou aux overrides locaux sauf demande explicite
- ne pas supprimer `backend/public/uploads/editorial/**` ni le traiter comme un cache

Discipline de `worktree`:
- sauf demande explicite contraire, finir un passage de travail avec un `git status` propre
- supprimer avant cloture les fichiers temporaires, payloads de deploiement, scripts jetables et artefacts locaux crees pour la tache
- ne pas laisser de modifications partielles, brouillons ou fichiers non suivis sans utilite claire dans le depot
- si le depot etait deja sale avant intervention, isoler strictement le diff de la tache et revenir a un `worktree` propre sans ecraser un travail utilisateur non valide

## 4. Documents de reference obligatoires

Avant une modification non triviale, prendre en compte les documents de reference lies au sujet:
- `README.md`
- `README_DOCUMENTATION_INDEX.md`
- `backend/README_BOOTSTRAP_I18N.md`
- `backend/README_PUBLIC_ENTRYPOINTS.md`
- `backend/README_LOGGING.md`
- `frontend/README_BUILD_PIPELINE.md`
- `README_ADMIN_EDITORIAL_NAV_V1.md`
- `README_BLOG.md`
- `docs/rapport-style-editorial-2026-04-16.md`
- `docs/README_REFONTE_LOT_C.md`
- `docs/README_CONSOLIDATION_LOT_D.md`

Principe:
- si un README existant couvre deja le domaine touche, il doit etre considere comme source de verite documentaire
- si un changement modifie comportement, exploitation, verification ou deploiement, mettre a jour la doc pertinente

## 5. Architecture cible du depot

Le modele du projet est hybride:
- rendu serveur PHP en source de verite
- frontend Vite pour les assets et interactions
- modernisation progressive autour de `backend/src/` sans casser le legacy utile

Flux HTTP de reference:
- entree publique: `backend/public/index.php`
- bootstrap commun: `backend/core/bootstrap.php`
- app HTTP: `backend/src/Http/FrontController.php`
- routage public legacy/structure: `backend/src/Http/LegacyRouteResolver.php`
- routage admin: `backend/src/Admin/AdminRouteResolver.php`
- rendu via templates PHP dans `backend/templates/`

Regle d'architecture:
- toute nouvelle logique applicative doit aller dans `backend/src/` quand c'est possible
- `backend/core/*` doit rester un socle commun, un wrapper de compatibilite, un helper legacy maitrise ou un outil CLI deja inscrit dans l'architecture
- `backend/public/*` ne doit pas devenir un nouvel espace de logique metier

Ne pas faire:
- ajouter de la logique metier dans `backend/public/index.php`
- repliquer du routage ou du bootstrap dans plusieurs points d'entree
- entasser de nouvelles regles metier dans les wrappers legacy quand une classe `src/` dediee est possible

## 6. Regles backend PHP

Pour tout nouveau code moderne dans `backend/src/` et `backend/tests/`:
- ajouter `declare(strict_types=1);`
- typer arguments, retours et proprietes
- preferer des classes `final`
- preferer l'injection par constructeur
- preferer une responsabilite claire par classe
- preferer des retours precoces et des fonctions courtes

Conventions observees a suivre:
- logique metier dans des services, repositories, normalizers, builders ou facades dedies
- controllers et resolvers minces
- templates PHP sans calcul metier complexe
- wrappers legacy conserves pour compatibilite, pas comme nouvelle source de verite

Guides concrets:
- nouvelle logique de routage HTTP: `backend/src/Http/*`
- nouvelle logique admin: `backend/src/Admin/*`
- nouvelle logique i18n: `backend/src/I18n/*`
- nouvelle logique assets cote PHP: `backend/src/Assets/*`
- nouvelles regles de persistence/editorial: `backend/src/Content/*`, `backend/src/Navigation/*`, `backend/src/Blog/*`, `backend/src/Database/*`

## 7. Routage, entrees publiques et compatibilite

Le projet a une gouvernance HTTP explicite. Toute nouvelle entree doit respecter ce cadre.

Regles:
- preferer une route geree par `backend/public/index.php` et `FrontController`
- ne pas recreer un nouveau fichier public PHP si le besoin peut etre absorbe par le front-controller
- si un shim legacy reste necessaire, il doit deleguer et non embarquer sa propre logique metier
- toute evolution du routage doit tenir compte du multilingue et du fallback `DEFAULT_LANG`
- l'admin doit rester gouverne par `ADMIN_LOGIN_PATH`

Haute sensibilite:
- `backend/public/index.php`
- `backend/src/Http/FrontController.php`
- `backend/src/Http/LegacyRouteResolver.php`
- `backend/src/Admin/AdminRouteResolver.php`
- `backend/src/Admin/AdminController.php`

Tout changement sur ces points exige tests et verification manuelle.

## 8. I18n et textes visibles

Conventions du projet:
- documentation et echanges humains en francais
- identifiants de code nouveaux en anglais
- textes visibles cote PHP via `t()`
- textes visibles cote frontend via le module i18n frontend ou des valeurs runtime exposees proprement

Regles:
- ne jamais hardcoder un texte metier visible si une traduction est attendue
- toute nouvelle cle doit etre ajoutee de maniere coherente dans `backend/lang/fr.php`, `backend/lang/en.php`, `backend/lang/de.php`
- toute logique de resolution de langue doit passer par le socle existant (`LanguageResolver`, `Translator`, `lang_bootstrap`)
- ne pas contourner `CURRENT_LANG`, `DEFAULT_LANG` et les helpers de traduction deja en place
- dans l'admin, ne pas utiliser directement la langue publique du visiteur ni le cookie public pour les libelles d'interface; les textes admin doivent passer par la langue d'interface administrateur (`admin_interface_language()` / `admin_translate()`) afin d'eviter tout melange `fr`/`en`/`de`
- `fr` reste le texte maitre pour les pages editoriales, mais toute creation, modification significative ou suppression d'une page doit etre repercutee sur `en` et `de` dans le meme passage de travail si possible
- pour les pages et articles publics, les versions `en` et `de` doivent refleter fidelement la version `fr`: traduction correcte, meme fond, memes faits, memes limites, memes liens utiles et memes informations pratiques, sans omission, ajout non justifie ni changement de sens; seule l'adaptation naturelle de ton, de formulation et d'expression locale est admise
- toute nouvelle creation editoriale publique doit etre preparee nativement en `fr`, `en` et `de`; ne pas creer seulement `fr` avec l'idee de revenir plus tard, sauf blocage explicite documente
- apres creation d'une page publique en `fr`, preparer aussi ses versions `en` et `de` avant cloture ou signaler explicitement si elles restent a faire
- apres modification d'une page publique en `fr`, mettre a jour `en` et `de` pour conserver un registre editorial aligne
- apres suppression d'une page, verifier que `fr`, `en` et `de` sont supprimes ou retires ensemble pour ne pas laisser de traduction orpheline ni de route incoherente

## 9. Ligne editoriale, style litteraire et structure des articles

Le site doit garder une voix editoriale stable sur l'ensemble de ses pages publiques, qu'il s'agisse d'auto-retro, de territoire, de vie du club ou de pages partenaires.

### 9.1 Positionnement editorial
- le style recherche est litteraire, mais maitrise
- il ne doit etre ni platement administratif, ni lyrique, ni publicitaire
- l'effet litteraire doit venir de la precision, du rythme, du regard et du detail juste, pas d'une accumulation d'adjectifs
- un article doit donner au lecteur la sensation d'un sujet reellement observe, situe et compris

### 9.2 Ligne editoriale commune
- pas d'invention
- partir d'un fait concret avant tout effet de style: date, lieu, situation, etat, usage, observation ou repere technique
- garder une voix claire, calme, vivante et incarnee
- faire sentir la matiere, le paysage, la mecanique ou l'ambiance par des details justes et non par des formules convenues
- conserver les informations utiles: chronologie, contexte, saisonnalite, contraintes, repere technique, etat, travaux, usage, acces, singularite
- conclure court, sans morale generale ni formule vide
- sur les sujets personnels, assumer `nous`, `notre`, `nos` quand cela correspond a une experience reelle
- sur les sujets historiques, patrimoniaux ou pratiques, preferer une voix informative stable plutot qu'un recit artificiellement intime
- `fr` reste le texte maitre; `en` et `de` doivent adapter le ton sans traduction mot a mot rigide, mais sans retirer, ajouter ou deformer une information de fond

### 9.3 Style vise
- phrases fluides, le plus souvent courtes a moyennes
- paragraphes de `4` a `10` phrases
- une idee principale par paragraphe
- une alternance lisible entre information, detail concret et respiration narrative
- un vocabulaire simple, precis, avec quelques mots plus expressifs quand ils apportent une image juste
- des intertitres qui annoncent un angle reel, pas un simple effet de manche
- une progression nette: ouverture concrete, developpement organise, fermeture breve

### 9.4 Ce qu'il faut eviter
- surenchere adjectivale, superlatifs automatiques et emphase patrimoniale
- conclusion grandiloquente, morale abstraite ou phrase de remplissage
- ouverture vague sans date, lieu, contexte ni fait observable
- memes tournures recyclees d'un article a l'autre
- questions rhetoriques repetitives
- langage publicitaire ou promesses marketing
- mots et tournures uses quand ils remplacent l'information
- aucune recopie directe d'une source
- les references de source dans le corps editorial, du type `d'apres`, `selon`, `source`, `l'office de tourisme publie`, `le site officiel rappelle`, `la commune presente`, ou leurs equivalents en `en` et `de`
- les formulations qui racontent la recherche documentaire au lieu de raconter le sujet; transformer ces passages en faits sobres, et garder les sources uniquement dans la section `Sources`

### 9.5 Tournures a employer avec parcimonie
- `icone`, `emblematique`, `mythique`, `legendaire`, `incontournable`
- `charme`, `ecrin`, `joyau`, `petit bijou`
- `role crucial`, `empreinte indelebile`, `tournant de l'histoire`
- `avant-garde`, `design audacieux`, `fait battre le coeur`

Regle de fond:
- ces mots ne sont pas interdits par principe, mais ils ne doivent apparaitre que s'ils sont historiquement justes, rares et soutenus par un fait

### 9.6 Architecture canonique d'un article ou d'une page
- `1` seul `h1`, descriptif, centre sur le sujet et son angle
- juste sous le `h1`, un chapo ou paragraphe d'ouverture de `2` a `4` phrases
- pour un article standard, `3` a `5` `h2`
- pour un article court, `2` a `3` `h2`
- pour un article long, `5` a `6` `h2` maximum sauf dossier exceptionnel
- les `h3` ne servent qu'a subdiviser un `h2` dense; ne jamais sauter directement de `h1` a `h3`
- chaque intertitre doit correspondre a un bloc logique autonome
- l'article se termine par une fermeture breve: etat actuel, repere final, suite logique, conseil de visite, constat ou ouverture sobre

Ordre recommande:
- `h1` : sujet + angle editorial clair
- chapo : fait concret + promesse de lecture
- `h2` : contexte, origine, situation ou cadre
- `h2` : coeur du sujet, chronologie, singularites ou faits saillants
- `h2` : details utiles, technique, parcours, acces, saison, restauration, usage ou points d'attention
- `h2` : ce qu'il faut retenir aujourd'hui, etat actuel, pratique de visite, philosophie de conservation ou de transmission
- blocs optionnels : fiche technique, reperes pratiques, galerie, liens internes, sources si utiles

### 9.7 Longueur indicative
- si pas de demande spécifique, faire le meilleur choix de longueur selon le sujet traité
- article court : environ `800` a `1000` mots quand le sujet est simple ou tres visuel
- article standard : environ `1200` a `1800` mots
- dossier long : au-dela de `2000` mots seulement si la matiere l'impose vraiment
- ne pas allonger un article pour atteindre un volume arbitraire

### 9.8 Variantes attendues selon le type d'article
- article historique de marque ou modele : commencer par situer la periode, le contexte industriel ou l'usage du modele; garder une chronologie lisible; n'ajouter les caracteristiques techniques que si elles eclairent le propos
- article d'experience ou `notre voiture` : commencer par l'evenement declencheur (achat, decouverte, cadeau, transport, restauration); decrire l'etat initial, les decisions prises, les travaux ou usages, puis la place actuelle de l'objet dans notre histoire
- article territoire / promenade : dire d'abord ou se trouve le lieu, sa taille, son relief, sa saisonnalite ou sa fonction; ensuite expliquer ce que l'on voit vraiment, ce qu'il faut regarder, les contraintes et le bon moment pour venir
- page partenaire ou vitrine : presentation sobre, activite, savoir-faire, evolution, services, liens utiles; ne pas glisser vers la plaquette commerciale
- article de blog : garder le meme style editorial, la meme structure logique et la meme exigence de precision que pour une page, mais avec une lecture plus rapide et plus directe
- article de blog : le blog complete les pages editoriales; une page peut etre plus complete et plus developpee, tandis qu'un article de blog doit aller plus vite a lire sans devenir superficiel
- article de blog : dans `80 %` des cas, viser `1000` a `1500` mots
- article de blog : dans `20 %` des cas, viser `800` a `1000` mots quand le sujet est plus simple, plus ponctuel ou tres visuel
- article de blog : depasser `1800` mots doit rester exceptionnel et etre reserve a un sujet qui le justifie clairement
- article de blog : le maillage interne est obligatoire, avec des liens vers des pages complementaires et des sujets lies, mais jamais vers la page actuelle elle-meme
- article de blog : ne jamais commenter la fonction SEO, l'utilite editoriale, le statut de brouillon ou la structure de l'article lui-meme; le texte doit parler du sujet, pas de sa propre redaction
- article de blog : proscrire les formulations meta du type `ce brouillon`, `cet article`, `page de reference`, `utile pour`, `pour le lecteur`, `le sujet gagne en clarte` ou `donne au sujet une fonction`; remplacer ces phrases par des faits, des dates, des usages, des contraintes ou des observations concretes
- article de blog : livrer une version finie des le premier jet; ne jamais ecrire en pensant qu'une version publiee devra etre completee plus tard
- article de blog : s'adresser au lecteur par le sujet lui-meme, sans commenter l'intention de redaction; eviter les phrases du type `l'article doit`, `le but est`, `le premier reflexe utile consiste a`, `il faut segmenter le sujet`, `un bon article`, `un article pratique utile`
- article de blog : privilegier les informations qui servent vraiment la lecture: symptomes, controles, dates, modele concerne, pieces, contraintes d'achat, entretien, usage, limites connues, couts possibles, disponibilite, chronologie et faits industriels verifies
- article de blog : refuser le remplissage, les certitudes de facade et les generalites pedagogiques; quand une information depend du modele, de l'etat ou du marche, le dire clairement et expliquer ce que le lecteur peut verifier concretement
- article de blog : chaque slug public doit exister en `fr`, `en` et `de`; `fr` reste la version maitre, mais `en` et `de` doivent etre de vrais articles adaptes, pas des coquilles vides ni des traductions automatiques non relues

### 9.9 Regles de structuration HTML et editoriale
- un seul `h1` visible par article
- respecter la hierarchie `h1` > `h2` > `h3` sans niveau saute
- `EditRegion8 - Intro` ne peut contenir qu'une petite image d'appel ou un texte court
- pour `INTRO`, privilegier d'abord une petite image; si aucune image n'est utile, limiter le texte a un bloc tres court
- ne pas utiliser `INTRO` pour loger un second corps d'article, un long developpement ou une grande image; si le contenu depasse ce role, le basculer dans `EditRegion3 - Corps` ou une autre region adaptee
- rechercher, quand le sujet s'y prete, des images libre de droit pour agrementer les sections et ne pas se limiter aux visuels deja presents
- pour un article de blog: image de couverture obligatoire, un texte d'intro compact et minimum une image utile dans le corps
- pour un article de blog: au moins 1 image dans le contenu est obligatoire, une 2e image dans le contenu seulement si elle apporte une information précise (repere technique, comparaison, etat, usage)
- pour un article de blog: éviter les images décoratives, l'empilement ou la répétition du meme visuel; prioriser des images utiles, structurantes et non redondantes entre intro/couverture et corps
- ne pas laisser de `h2` ou `h3` vides
- pas de tiret cadratin ou autres sigles qu'un clavier français n'a pas directement
- preferer des paragraphes reels a des successions de lignes separees par `<br>`
- reserver les listes `ul/li` aux reperes, etapes, caracteristiques ou points pratiques
- sur les pages tres illustrees, garder des blocs de texte plus courts entre les images
- ne pas cacher l'information cle uniquement dans une legende ou une image
- si une fiche technique est utile, la presenter comme un bloc lisible et structure, pas comme un pave compact
- en fin d'articles ajouter les sources si possible, dans la section : EditRegion11 - Post-scriptum
- les sources s'ouvrent dans un nouvel onglet
- le corps de l'article ne doit pas citer les sources par des tournures d'attribution; les liens de provenance restent regroupes en fin d'article dans `Sources`
- le bloc de maillage interne doit se situer dans la section : EditRegion4 - Apres corps
- ne pas creer de section intitulee `A lire`, `À lire` ou `À lire aussi`; le maillage interne doit rester sobre, descriptif et integre au parcours editorial, sans bloc standardise de recommandation
- pour les pages publiques `/auto-retro/**` exposees dans le menu Auto-retro / Bouger, ajouter en fin de `EditRegion4 - Apres corps` un court paragraphe de maillage interne quand une page liee existe dans le registre editorial courant
- ce maillage auto-retro doit etre ajoute seulement s'il manque, rester limite a `1` ou `2` phrases, pointer vers `1` page prioritaire et au plus `1` page complementaire, ne jamais lier la page a elle-meme, ne pas creer de lien mort et ne pas dupliquer un paragraphe deja present
- pour appliquer ce maillage sur le registre JSON, utiliser l'outil idempotent `php backend/core/tools/add_auto_retro_internal_links.php`; le relancer en `--dry-run` avant ecriture pour verifier les pages et routes ciblees

### 9.10 Metadonnees et completude editoriale
- renseigner un titre d'article distinct, lisible et utile hors contexte
- renseigner un extrait qui resume l'angle de lecture sans recopier le `h1`
- choisir une categorie stable et peu nombreuse; ne pas inventer des categories inutilement
- choisir des tags concrets: marque, lieu, theme, usage, restauration, technique
- image de couverture obligatoire pour tout article de blog, avec `alt`, `title`, `caption`, `width` et `height` quand l'information existe
- l'attribut `alt` decrit l'image factuellement; la legende apporte le contexte editorial si necessaire
- rattacher l'article a sa page parent ou a son article parent seulement si cela a un sens editorial reel
- verifier que les liens internes servent la lecture et ne forcent pas un maillage artificiel

### 9.11 Uniformite editoriale a l'echelle du site
- un article = un angle dominant; ne pas melanger sans controle dossier historique complet, recit personnel, guide de visite et argumentaire commercial
- chaque article doit repondre implicitement a `qu'est-ce que c'est`, `pourquoi ce sujet ici`, `qu'est-ce qui est concret`, `qu'est-ce que le lecteur retient`
- chaque article doit conserver une densite utile: pas de remplissage pour "faire long"
- le maillage interne doit rester naturel et utile: pointer vers la page parent, les pages soeurs ou un contenu complementaire quand cela aide vraiment la lecture
- si le sujet ne justifie pas un long texte, preferer un article plus court mais plus tenu

### 9.12 Taxonomie obligatoire des articles de blog

Source de verite:
- la taxonomie blog canonique est `backend/config/blog_taxonomy.php`
- elle definit les categories principales, les sous-categories, les tags autorises, les traductions `fr`, `en`, `de` et le statut SEO `index` ou `noindex`
- aucune categorie, sous-categorie ou tag de blog ne doit etre cree automatiquement depuis une saisie libre

Structure cible:
- `3` a `4` categories principales maximum; la cible courante est `auto-retro`, `territoire`, `vie-locale`, `patrimoine`
- `6` a `8` sous-categories maximum au total; la cible courante est `histoire-automobile`, `modeles-et-versions`, `restauration-et-entretien`, `conduite-et-collection`, `golfe-saint-tropez`, `villages-et-balades`, `evenements-et-animations`, `lieux-et-memoire`
- `20` a `30` tags maximum dans le referentiel; la cible courante est limitee a `30` tags reutilisables et non a des variantes de modele
- chaque article de blog doit avoir `1` categorie obligatoire, `0` ou `1` sous-categorie et `3` a `5` tags autorises
- les valeurs stockees doivent etre normalisees en minuscules, sans accents, au format `kebab-case`
- les libelles publics et admin doivent provenir des traductions du referentiel, pas des slugs bruts
- les tags de marque ou modele doivent rester controles: garder `mini-austin` plutot que creer `mini`, `Austin Mini` ou une variante libre; garder des tags generiques comme `histoire`, `modele`, `version`, `restauration`, `entretien`, `mecanique`, `collection`, `route`, `experience` quand ils servent plusieurs articles

Regles admin:
- categorie par liste deroulante, jamais par champ libre
- sous-categorie dependante de la categorie selectionnee
- tags par cases a cocher ou autocomplete strictement limite au referentiel
- refuser la sauvegarde si la categorie est absente, si plus d'une sous-categorie est envoyee, si un tag est inconnu, si moins de `3` ou plus de `5` tags sont envoyes, si un tag est duplique, ou si la sous-categorie ne depend pas de la categorie

Regles front et SEO:
- afficher categorie, sous-categorie et tags avec les libelles traduits
- les pages de categories importantes peuvent rester indexables
- les pages de tags sont `noindex` par defaut pour eviter une indexation massive de pages faibles
- les pages filtrees par tag doivent emettre `noindex,follow`
- les suggestions internes d'articles doivent privilegier la meme categorie, puis la meme sous-categorie, puis au moins `2` tags communs, avec `3` articles suggeres maximum

Migration et controle:
- utiliser `php backend/core/tools/diagnose_blog_taxonomy.php` pour detecter tags inconnus, accents, doublons, variantes et mappings necessaires
- le diagnostic doit rester a zero sur `taxonomy_config_issues` et `issues` avant commit
- ne pas supprimer ou fusionner une taxonomie existante sans backup adapte du stockage actif
- exemples de normalisation attendus: `mini austin` ou `Austin Mini` -> `mini-austin`, `saint tropez` ou `St Tropez` -> `saint-tropez`

### 9.13 Maillage interne des articles de blog

Principe:
- chaque article de blog doit etre rattache a une page parent via `page_slug`; cette page parent est la page pilier ou la page de contexte editorial de l'article
- quand une page parent publiee existe, le lien interne prioritaire vers l'article doit pointer vers la page parent avec ouverture de l'article attache: `/<lang>/<route-parent>?open_article=<slug>#attached-article-<slug>`
- la route directe `/blog/article/<slug>` reste un fallback technique et ne doit pas devenir la cible principale du maillage interne si l'article est diffuse sous une page parent

Priorite des liens:
- `1` lien fort vers la page parent ou vers une ancre pertinente de cette page parent
- liens vers articles freres de la meme page parent quand ils prolongent vraiment le sujet
- liens vers article parent/enfant seulement si la relation editoriale est reelle
- liens par taxonomie en soutien: meme sous-categorie, meme categorie, puis au moins `2` tags communs
- ne pas lier un article a lui-meme, ne pas creer de lien mort et ne pas multiplier les liens vers des pages tag `noindex`

Ancres et pages piliers:
- ajouter une ancre stable uniquement sur une section durable d'une page pilier ou d'une page parent, jamais sur un paragraphe fragile
- convention recommandee: identifiant HTML court, descriptif, en `kebab-case`, par exemple `#histoire-longbridge`, `#restauration-pieces`, `#modeles-austin`
- si l'article vise une section precise de la page parent, preferer le lien vers cette ancre plutot qu'un lien vague vers le haut de page
- ne pas ajouter des ancres en masse; une ancre est utile seulement si plusieurs contenus ou un article important doivent pointer vers ce repere

Forme editoriale:
- integrer les liens dans une phrase utile du corps ou dans une courte fermeture de maillage, sans bloc standardise intitule `A lire`, `À lire` ou `À lire aussi`
- viser `2` a `4` liens internes utiles pour un article standard, moins pour un article court
- privilegier les liens qui clarifient le parcours de lecture plutot que les liens poses pour remplir une contrainte SEO

### 9.14 Series d'articles de blog rattachees a une page pilier

Principe:
- une serie d'articles de blog rattachee a une page parent doit former un dossier coherent autour de cette page pilier, pas une collection d'articles generiques
- definir les themes, les slugs et le mot-cle principal de chaque article avant la production du contenu
- ne pas creer de theme supplementaire si le dossier a deja une structure validee; completer ou resserrer la structure existante plutot que l'etendre par reflexe
- chaque article doit couvrir un angle unique, une periode ou une question precise; ne pas melanger plusieurs periodes, familles de modeles ou intentions SEO dans le meme article
- ne pas parler des autres pages parents deja creees quand elles sont hors sujet du dossier courant; les liens vers une autre page parent doivent rester exceptionnels, utiles et editorialement justifies

Regles SEO et editorial:
- `1` mot-cle principal par article; il doit guider le titre, le slug, l'extrait et l'ouverture, sans bourrage ni repetition artificielle
- titre precis et non generique; eviter les titres du type `Histoire de la Mini`, `Tout savoir sur`, `Guide complet` quand l'article vise un angle plus etroit
- dans le blog, le titre de l'article est le `h1` rendu par le template; le contenu stocke ne doit pas ajouter un second `h1` et doit structurer le propos en `h2` puis `h3` seulement si necessaire
- utiliser la taxonomie autorisee existante: categorie logique, sous-categorie coherente si utile, `3` a `5` tags autorises; ne pas creer de tag ou categorie pour un seul article
- rattacher chaque article a la page parent par `page_slug` et verifier que cette page est bien la page pilier attendue

Maillage obligatoire d'un article de serie:
- `1` lien vers la page principale ou vers une ancre stable de cette page
- `1` lien vers un autre article du meme theme, rattache a la meme page parent
- ne jamais lier l'article a lui-meme
- ne pas utiliser un article d'un autre theme pour remplir artificiellement la contrainte de lien interne

Controle avant sauvegarde ou publication:
- verifier unicite des slugs, statut attendu, rattachement `page_slug`, taxonomie, liens internes, absence de lien mort et absence de derive vers une autre page parent
- si une incoherence est detectee, corriger la structure, le titre, le slug, la taxonomie ou le maillage avant de produire ou publier l'article

### 9.15 Planification automatique des articles de blog

Le workflow de planification automatique doit respecter la stratégie ci-dessous.

Principe:
- regrouper les brouillons par `page_slug` (cluster éditorial)
- à l’intérieur d’un cluster, raisonner par article logique (`slug`) et non par variante de langue
- le quota `published + scheduled` se calcule par `slug` distinct, jamais par entrée `fr` / `en` / `de`
- sélectionner un cluster actif (même page) dont la somme `published + scheduled` est inférieure à `5`
- choisir le prochain brouillon le plus ancien de ce cluster, puis planifier ensemble toutes ses variantes brouillon disponibles à la même date
- si un `slug` a déjà une variante `scheduled` ou `published`, aligner les brouillons restants sur cette date existante au lieu de créer une seconde date
- si un ou plusieurs clusters actifs existent, privilégier celui avec le plus petit total `published + scheduled`
- si tous les clusters ont `>=5` articles publiés/planifiés, choisir le brouillon le plus ancien toutes langues confondues
- calculer la date planifiée:
  - si aucune date `scheduled` n’existe: aujourd’hui + `11` jours
  - sinon: dernière date `scheduled` + `11` jours
- passer le brouillon en `scheduled` (jamais en `published`)
- ne pas modifier le statut des autres valeurs

Après chaque sélection qui modifie une date de planification, relancer le maillage interne des articles publiés ou planifiés :
- reconstruire les liens internes pour chaque article avec statut `published` ou `scheduled`
- utiliser la route du parent (`/fr|en|de/<route-parent>?open_article=<slug>#attached-article-<slug>`) quand :
  - la cible est publiée
  - ou la cible est `scheduled` avec date atteinte
  - ou une page parent publiée est disponible pour encadrer la navigation
- en l’absence de page parent publiée et si la cible n’est pas visible, utiliser `/fr|en|de/blog` comme route de repli (jamais une URL 404)
- ne pas cibler de route qui provoquerait un 404 (`/blog/article/<slug>` n’est qu’un fallback technique)
- conserver la règle de visibilité `published` / `scheduled` au moment du recalcul des liens

Commande d’exécution :
- `php backend/core/tools/plan_next_blog_article.php [--dry-run] [--json] [--now=YYYY-MM-DD HH:MM:SS]`
- exécute la règle de sélection, met à jour l’article choisi, puis reconstruit le maillage interne si une planification a été créée.

## 10. Politique media du site

La politique media est commune a tout le site: pages editoriales, articles blog, auto-retro, territoire, pages partenaires et contenus annexes.
Un media public fait partie du contenu editorial et doit etre gere avec la meme rigueur que le texte: source claire, droit d'usage clair, stockage canonique, format maitrise, dimensions explicites et insertion utile.

### 10.1 Regle de stockage canonique

Pour les images publiques du site, la source de verite est:
- `frontend/src/assets/images/**`

Regles:
- toute nouvelle image publique durable doit etre versionnee dans `frontend/src/assets/images/**`
- `backend/public/assets/images/**` est une copie publiee, jamais une source editable
- `backend/public/uploads/editorial/**` est un espace runtime legacy ou d'exception; ne pas l'utiliser pour un nouveau media public sans demande explicite
- ne pas editer directement `backend/public/assets/images/**`
- si un media visible en production a ete modifie directement sur OVH, recopier d'abord l'etat distant en local avant tout nouveau push

Commande WSL de resynchronisation locale apres divergence prod:
- etat editorial SQL: `cd /home/surfacepro8/www/caramagnols && bash .ops-sync/bin/pull-caramagnols-db.sh --live`
- media public cible: `cd /home/surfacepro8/www/caramagnols && rsync -av -e "ssh -p 22" lescaramgl-ssh@ssh.cluster103.hosting.ovh.net:/home/lescaramgl-ssh/caramagnols/backend/public/assets/images/<sous-dossier>/ frontend/src/assets/images/<sous-dossier>/`

### 10.2 Formats et diffusion

Regles de format pour les images:
- format maitre: `jpg`
- variante moderne a produire: `webp`
- a la creation d'une page publique, les references editoriales stockees (`html`, images structurees, image d'intro, couverture, `meta.image.src`) doivent pointer vers le `jpg` maitre quand il existe; ne pas enregistrer un `webp` en dur comme source principale de contenu
- diffusion publique: servir le `webp` seulement lorsqu'une variante existe et que le navigateur l'accepte, via un mecanisme avec fallback `jpg` (`picture`/`source type="image/webp"`, `srcset`, `image-set()` CSS ou helper de rendu equivalent)
- si le bloc ou le rendu ne dispose pas de ce mecanisme de fallback, garder le `jpg` en dur
- ne pas upscaler un original plus petit pour atteindre une cible arbitraire
- conserver le ratio reel du fichier; ne jamais etirer une image

Regles de dimension cible:
- image d'intro: largeur cible `1280 px`
- image dans le texte: largeur cible `400 px` par defaut
- image dans le texte plus structurante ou documentaire: largeur cible `700 px` maximum
- n'utiliser `700 px` que si le detail apporte reellement quelque chose a la lecture; sinon servir `400 px`
- si la source historique est plus petite mais propre, la conserver telle quelle
- renseigner `width` et `height` dans le HTML quand l'information est disponible

Regle specifique pour les tuiles Windows 10:
- la source canonique des visuels de tuiles est `frontend/src/assets/images/structure/menu/**`
- une image de tuile `ui*` doit etre preparee en `222x90 px`
- produire `jpg` et `webp`
- recadrer pour ne garder que le sujet utile, sans ciel, sol ou decor excessif
- ne jamais dessiner la bordure coloree dans la photo elle-meme
- la bordure W10 provient du fond `boutonrectangle/*` en `248x120 px`
- garder le rapport visuel historique: image `222x90` posee dans un fond W10 `248x120`, avec cadre colore lateral fin et bandeau titre reserve en haut
- si une nouvelle tuile doit coller au rendu legacy, respecter aussi le gabarit CSS existant: bouton `14rem x 7rem`, `padding` `0.25rem`, `padding-bottom` `0.625rem`

Regles de poids et de rendu:
- preferer un fichier optimise plutot qu'un original inutilement lourd
- verifier apres insertion que le rendu reste stable sur mobile comme sur desktop
- une illustration documentaire ne doit pas prendre toute la largeur par accident
- lors d'un redimensionnement responsive d'image, conserver le ratio naturel: fixer au plus une largeur ou un `max-width`, et laisser `height: auto`
- ne pas combiner une largeur fluide avec une hauteur fixe sur une image editoriale; si un cadrage visuel est necessaire, utiliser un conteneur dimensionne avec `aspect-ratio` et `object-fit`, pas une image deformee

### 10.3 Droits, sources et provenance

Regles de fond:
- ne pas supprimer, remplacer ou deplacer un media existant par defaut lors d'une reecriture sauf demande explicite, erreur factuelle evidente ou violation de droits
- lors d'une creation ou d'une reecriture editoriale, rechercher activement des images libre de droit quand elles eclairent le texte, la chronologie, la technique ou l'ambiance
- toute nouvelle image externe doit avoir un statut d'usage clair: image du projet, domaine public, `CC0` ou licence libre clairement compatible
- ne jamais utiliser une image dont le statut est flou, absent ou seulement suppose
- ne jamais faire de hotlinking depuis un site tiers
- pour une image, la section `Sources` publique doit rester minimale: garder le lien vers la source ou le fichier, sans ajouter auteur, licence ni autres details

Documentation minimale de travail attendue pour une image externe:
- URL source
- titre ou description courte du fichier
- si necessaire pour verification interne, auteur ou statut de licence
- si utile, une mention courte de contexte editorial ou de provenance hors rendu public

Regle de redaction pour la section `Sources` publique:
- rester sobre et utile: source ou fichier seulement pour une image
- ne pas transformer la section `Sources` en journal interne
- ne pas afficher dans la page publique des mentions comme `Photo`, `Auteur`, `Licence`, `License`, `Lizenz`, `Ajout local`, `date d'ajout`, `chemin local`, `chemin du site`, `Added locally`, `Site path` ou equivalentes

### 10.4 Types de media et usage editorial

Types a distinguer pour tout le site:
- image d'intro
- image dans le corps du texte
- image de region structuree laterale ou basse
- image de couverture ou de tete de page
- galerie finale
- video

Regles d'usage:
- une image doit illustrer un passage precis, pas seulement habiller la page
- preferer placer l'image au plus pres du paragraphe qu'elle eclaire
- par defaut, les images sont centrees dans leur bloc
- ne recourir a un alignement gauche ou droite que si une contrainte editoriale explicite le justifie vraiment
- interdire les doublons media entre intro, corps, regions structurees et galerie finale; un meme visuel ou un visuel quasi identique ne doit pas apparaitre a plusieurs endroits d'une meme page
- a l'echelle du site, une image editoriale ne doit etre utilisee qu'une seule fois dans le texte, quelle que soit la page, sauf contre-indication explicite prealablement validee
- si une image est deja presente dans une region structuree, ne pas la reposer dans le corps du texte sauf demande explicite

Regle de densite image selon la longueur:
- article court: `0` a `1` image dans le corps
- article standard: `1` a `2` images dans le corps
- dossier long: `2` a `3` images dans le corps maximum hors galerie finale, sauf besoin documentaire explicite
- si le sujet est tres visuel, preferer une galerie finale ou des regions structurees plutot qu'une accumulation d'images dans le texte

### 10.5 Alt, legende et traduction

Regles:
- l'attribut `alt` est toujours factuel
- l'attribut `alt` est obligatoire dans `fr`, `en` et `de`
- la traduction des `alt` est obligatoire
- la legende est optionnelle
- si une legende est utilisee, elle doit rester factuelle et sobre
- ne pas mettre dans la legende des mentions de type `Source`, `Wikimedia Commons`, `domaine public`, `CC0` ou equivalent
- si l'article s'appuie sur des sources externes pour les faits ou les images, ajouter une simple mention dans la section `Sources` de fin d'article

### 10.6 Videos

Politique video provisoire:
- versionner les fichiers video du site dans `frontend/src/assets/videos/**` si un besoin video apparait
- versionner l'image poster dans `frontend/src/assets/images/**`
- formats de diffusion a privilegier: `mp4` et `webm` si possible
- fournir un `poster`, des dimensions explicites et des controles natifs
- pas d'autoplay avec son
- pas d'embed tiers ni de hotlinking video sans demande explicite
- utiliser la video seulement si elle apporte un contenu documentaire reel qu'une image fixe ne couvre pas

### 10.7 Ce qu'il faut eviter

- galerie plaquee en fin de page sans lien avec le texte
- image decorative redondante qui n'apporte rien
- doublon entre image d'intro, image dans le texte et region structuree
- legende publicitaire ou lyrique
- suppression silencieuse d'une image ancienne sans trace ni motif
- ajout d'une image externe sans conservation de sa provenance
- push d'une page complete en prod quand seul un diff media cible est necessaire

### 10.8 SEO, reseaux sociaux et image de partage

Les champs SEO et reseaux sociaux font partie de la completude editoriale d'une page publique. Ils doivent etre renseignes lors d'une creation de page, d'une reecriture significative ou d'un changement d'image principal.

Champs a renseigner pour `fr`, `en` et `de`:
- `title`
- `meta.description`
- `meta.image.src`
- `meta.image.alt`
- `meta.image.title`
- `meta.image.width`
- `meta.image.height`

Regles:
- ne pas laisser une page publique importante sans `meta.description`
- ne pas laisser vide l'image SEO Open Graph / Twitter si une image representative du sujet existe deja
- si aucune image dediee n'est prevue, reutiliser l'image d'intro ou le visuel editorial le plus representatif de la page
- preferer une image versionnee dans `frontend/src/assets/images/**`
- pour l'image de partage reseaux sociaux, privilegier un fichier largement compatible; utiliser un `jpg` sauf besoin explicite documente, meme si le rendu HTML peut proposer un `webp` en fallback moderne
- l'image choisie doit etre descriptive du sujet reel, pas un decor generique ni un montage faible
- l'attribut `alt` de l'image SEO reste factuel et traduit dans `fr`, `en` et `de`
- le champ `title` de l'image SEO reste court, descriptif et sobre
- renseigner `width` et `height` avec les dimensions reelles du fichier utilise
- la `meta.description` ne recopie ni le `h1` ni le chapo mot pour mot; elle resume l'angle de lecture de facon concrete
- lors d'une modification editoriale importante, verifier apres publication la presence de `og:image`, `twitter:image` et `twitter:image:alt` dans le HTML rendu

Ce qu'il faut eviter:
- reprendre une image sans rapport direct avec le contenu reel de la page
- laisser une image SEO pointer vers un upload runtime alors qu'un asset versionne equivalent existe deja
- renseigner des dimensions approximatives
- laisser `fr` rempli et `en` ou `de` vides apres une mise a jour significative
- pousser une reecriture complete de page en prod uniquement pour corriger ces champs si un diff meta cible suffit

## 11. Stockage editorial et donnees

### 11.1 Modes de persistence

Le projet supporte plusieurs modes de persistence editoriale:
- `EDITORIAL_STORAGE=json|dual-write|sql`
- `BLOG_STORAGE=json|dual-write|sql`

### 11.2 Source active JSON et SQL

- le JSON reste utile comme format de travail, versionnement Git, export, backup cible et payload de migration
- quand `EDITORIAL_STORAGE=sql`, la base SQL est la source active du rendu local/prod; une page presente seulement dans `backend/data/pages.json` ne doit pas etre consideree comme publiee dans l'environnement actif
- apres toute creation ou modification editoriale faite dans le registre JSON alors que le stockage actif est `sql`, importer la correction en SQL via un workflow adapte avant de conclure que la page est disponible
- privilegier un import SQL cible quand seules quelques pages ou entrees de navigation sont concernees; reserver l'import complet `pages.json`/navigation vers SQL aux synchronisations assumees
- apres import SQL, regenerer l'index de recherche depuis le stockage actif et verifier le rendu public cible
- si JSON et SQL restent volontairement divergents en fin de tache, le signaler explicitement avec les slugs/routes concernes

### 11.2 bis Coherence pages/navigation entre SQL, JSON, admin et front

- avant d'affirmer qu'une page, un menu ou un article "existe", "n'existe pas", "est publie" ou "est visible", identifier d'abord le stockage actif reel puis verifier separement:
  - le repository actif (`page_repository()`, `navigation_repository()`, `blog_repository()`)
  - le fallback JSON versionne (`backend/data/pages.json`, `backend/data/menus.json`, blog JSON si concerne)
  - le rendu admin cible (`admin/pages`, `admin/menus`, `admin/articles`) quand la demande parle de l'interface d'administration
  - le rendu front cible ou le view model/runtime qui l'alimente
- ne jamais conclure depuis un seul helper dont le contrat de stockage n'a pas ete relu; en cas de doute, instancier explicitement un repository en mode `json` ou `sql` pour comparer les deux et ne pas deviner le comportement
- pour `pages` et `navigation`, une correction n'est pas consideree complete si elle laisse un decrochage silencieux entre base SQL active, fichier JSON de fallback/versionnement, admin et front; si un ecart reste volontairement present, le documenter explicitement
- quand une page publique depend d'une entree de menu, ou qu'un menu depend d'une page publique, corriger les deux dans le meme passage de travail pour garder `admin/pages`, `admin/menus` et le front coherents
- apres une ecriture SQL sur `pages` ou `navigation`, verifier au minimum:
  - la presence de la page ou de l'entree via le repository actif
  - la resolution du lien public via le runtime de navigation ou `NavigationViewModelBuilder`
  - la presence cote admin si la demande mentionne `admin/pages` ou `admin/menus`
  - l'index de recherche si une page publique est ajoutee, retiree ou rendue visible
- avant de dire qu'un menu "existait deja", controler la source active et non le seul snapshot JSON; inversement, avant de dire qu'une page "n'existe pas", verifier aussi si elle est presente dans le fallback JSON alors qu'elle manque en SQL

### 11.3 Regles generales de persistence

- ne pas ecrire directement dans plusieurs stockages sans passer par les facades/repositories prevus
- toute nouvelle logique de persistence doit respecter les interfaces existantes et les modes de stockage configures
- si une evolution touche pages, navigation, blog ou discussions, considerer l'impact sur `json`, `dual-write` et `sql`
- avant une operation destructive, privilegier les outils et scripts deja prevus (`editorial_backup_restore`, imports SQL, etc.)
- ne jamais modifier des donnees SQL, locales ou distantes, sans directive specifique et explicite pour cette operation; si une correction SQL est demandee, faire d'abord un backup adapte, limiter l'ecriture au perimetre vise et documenter la verification effectuee

### 11.4 Envoi cible en production OVH MySQL

Procedure recommandee:
- en cas de divergence entre le local et la prod sur l'edito public, la prod OVH est la source de verite tant qu'un pull de resynchronisation n'a pas ete rejoue en local
- pour une mise a jour editoriale ciblee en production OVH, ne pas faire de `restore` complet si seules une ou quelques pages doivent etre envoyees
- avant toute nouvelle ecriture sur OVH, relire l'etat prod reel de la page ou des pages visees; ne pas supposer que le local est encore a jour
- avant toute ecriture sur OVH, faire un backup SQL prod via `php core/tools/editorial_backup_restore.php backup --storage=sql --output=...`
- apres une ecriture en prod, repuller la base OVH vers le local si le local doit rester exploitable comme environnement de travail
- preparer en local un payload JSON ne contenant que les pages a pousser, relu et verifie avant copie distante
- pour l'execution distante, preferer copier un script PHP temporaire et un payload JSON temporaire sur OVH puis lancer `php script.php payload.json`; eviter les grosses commandes `ssh` inline avec quoting complexe ou heredoc imbriques
- l'import distant doit passer par `page_repository()->savePage(...)` pour chaque page, jamais par une ecriture SQL manuelle ad hoc
- apres copie, verifier la taille du payload local et distant avant import pour eviter un fichier vide ou tronque
- apres import, regenerer l'index de recherche et vider le cache runtime
- finir par une verification HTTP publique ciblee sur les URLs modifiees, au minimum en `fr`, et aussi en `en` et `de` si la page a ete traduite dans le meme passage
- conserver le chemin exact du backup prod cree avant ecriture pour pouvoir revenir en arriere rapidement
- si l'operation porte sur des pages seulement, ne pas embarquer navigation, blog ou autres registres dans le meme passage sans besoin explicite

### 11.5 Rapatriement des backups prod sur PC

- lorsqu'un backup prod SQL ou autre doit etre conserve localement, le creer d'abord sur OVH dans un emplacement temporaire maitrise
- compresser le backup avant transfert, par exemple en `.sql.gz`, `.json.gz` ou archive equivalente selon le contenu
- rapatrier ensuite le backup compresse sur le PC, de preference hors depot Git dans `/home/surfacepro8/backups/caramagnols/prod/`
- verifier explicitement la taille du fichier distant et du fichier local apres transfert; ne jamais considerer un backup vide ou tronque comme valide
- appliquer des permissions restrictives au fichier local quand il contient des donnees sensibles, par exemple `chmod 600`
- supprimer la copie temporaire OVH des qu'elle n'est plus necessaire au rollback immediat
- ne jamais placer un backup prod dans `backend/`, `frontend/`, `public/`, `backend/data/`, `backend/var/` ou tout chemin deployable/versionnable du projet

### 11.6 Attention sur `backend/data/`

- `backend/data/` melange donnees versionnees, donnees derivees et runtime
- ne pas traiter tout `backend/data/` comme un espace libre de modifications opportunistes
- ne pas ecraser logs, caches et donnees generees pour "corriger" un bug produit

## 12. Securite applicative

Le projet centralise deja:
- CSP et securite HTTP
- cookies/session
- CSRF
- rate limiting
- controle session admin

Regles:
- toute action sensible doit reutiliser les mecanismes existants (`Csrf`, helpers securite, ping session admin, etc.)
- ne jamais securiser uniquement cote frontend
- ne jamais exposer de secrets, tokens, mots de passe, stack traces ou payloads complets dans les logs ou dans le HTML
- tout nouvel endpoint admin ou POST sensible doit verifier auth, droits et CSRF

Regle de connexion admin locale sans mot de passe:
- un bypass de mot de passe admin n'est autorise que pour l'ergonomie locale et jamais comme mecanisme de production
- le garde-fou doit etre code en dur: environnement non-production explicite et adresse distante reellement loopback (`127.0.0.1`, `::1` ou equivalent IPv4 mappe)
- ne jamais accepter ce bypass en se basant seulement sur `HTTP_HOST`, `SERVER_NAME`, un proxy, un header forwarde ou une option de configuration
- meme si `local_passwordless_localhost` est active par erreur sur OVH, le code doit refuser le bypass quand `APP_ENV=production`, `prod` ou `live`
- quand ce bypass local est autorise, ne pas demander de mot de passe ni de code TOTP sur le formulaire local
- la connexion locale sans mot de passe doit creer une session admin normale, conserver CSRF, timeout, re-auth et logs de securite
- l'activation doit rester dans une configuration locale non versionnee, par exemple `backend/config/admin.override.php`, jamais dans un fichier public, versionne ou deployable

Sources de verite a respecter:
- `backend/src/Security/*`
- `backend/core/security.php`
- `backend/core/rate_limiter.php`
- `backend/core/auth/admin.php`

## 13. Logging et observabilite

Le projet a une couche dediee:
- `backend/src/Logging/LoggerFactory.php`
- `backend/src/Logging/AppEventLogger.php`
- `backend/core/tools/check_log_alerts.php`

Regles:
- journaliser peu mais utile
- evenements structures, courts, exploitables
- pas de secrets, pas de tokens, pas de donnees personnelles inutiles
- toute nouvelle ecriture sensible ou erreur metier importante doit passer par `AppEventLogger`

Ne pas faire:
- logguer du debug bruyant sur des parcours frequents
- laisser `var_dump`, `dump`, `die`, `console.log` de debug dans le code final

## 14. Regles frontend

Sources de verite frontend:
- entree JS: `frontend/src/js/main.ts`
- styles: `frontend/src/scss/style.scss`
- modules JS/TS: `frontend/src/js/*`
- build: `frontend/vite.config.mjs`
- publication: `frontend/tools/publish-build.mjs`
- lecture du manifest cote PHP: `backend/src/Assets/ViteAssetManager.php`

Regles:
- toute modification frontend doit partir de `frontend/src/`
- ne jamais modifier directement `frontend/dist/`, `backend/public/.vite/` ou les bundles publies sous `backend/public/assets/`
- la source canonique des images publiques est `frontend/src/assets/images/**`
- `backend/public/assets/images/**` est une copie publiee, pas une source editable
- si un nouveau module frontend est ajoute, preferer TypeScript quand cela reste coherent avec le dossier touche
- conserver les contrats DOM deja relies au CSS, aux scripts et aux tests
- pour le menu haut desktop, ne pas laisser plus de `5` liens consecutifs sous une meme section de mega menu; au dela, redistribuer automatiquement la suite dans une colonne voisine du meme bloc via la projection backend, avec un titre unique de section et sans imposer de faux groupes editoriaux dans les donnees
- pour le menu haut desktop, l ouverture des sous-menus doit fonctionner au survol par defaut; le clic reste un complement pour les cas tactiles, mais ne remplace pas le survol sur desktop

Contrainte importante:
- le site n'est pas une SPA
- ne pas introduire une logique frontend qui contourne le rendu serveur pour des parcours structurants

## 15. Build, publication et artefacts

Pipeline de reference:
- build Vite dans `frontend/dist`
- publication vers `backend/public/assets` et `backend/public/.vite/manifest.json`
- consommation cote PHP via `ViteAssetManager`

Scripts de reference:
- dev complet: `./dev.sh`
- backend dev seul: `cd backend && php -S 127.0.0.1:8000 -t public public/dev-router.php`
- frontend dev seul: `cd frontend && npm run dev`
- build frontend + publication: `cd frontend && npm run build`
- republication d'un dist existant: `cd frontend && npm run postbuild`

Regles:
- ne pas commit les artefacts generes ignores par Git
- ne pas corriger un probleme produit en editant un fichier publie si la source existe dans `frontend/src/` ou un outil de publication
- ne pas oublier que `npm run build` applique aussi les budgets frontend et le `postbuild`

Regle de deploiement propre:
- tout deploiement vers OVH doit produire un backend de production minimal et nettoye automatiquement
- les scripts de deploiement doivent exclure et nettoyer les fichiers de developpement, test, documentation, sauvegarde et temporaires qui ne sont pas necessaires au runtime production
- fichiers et dossiers non-prod a exclure/nettoyer au minimum: `tests/`, `docs/`, `README*`, `phpunit.xml`, `phpstan*`, `phpcs.xml`, `package*.json`, `replace_image_paths.php`, `public/dev-router.php`, `.env.example`, `.env.production`, `.env.bak.*`, `*.bak`, `*.old`, `*.orig`, `*.tmp`, `*~`, `.DS_Store`, `Thumbs.db`
- la prod doit conserver uniquement les secrets et donnees runtime attendus: `.env`, `config/*.override.php`, `public/uploads/**`, `var/**`, `data/logs/**`, `data/snapshots/**`
- apres synchronisation, executer un controle automatique de proprete de l'arborescence prod et faire echouer le deploiement si un residu non-prod reste present
- apres synchronisation, executer aussi le controle du manifest Vite pour verifier que chaque asset reference existe reellement sous `backend/public/assets/`
- ne pas creer automatiquement de copies `.env.bak.*` dans le backend prod a chaque deploiement; si un rollback de secret est necessaire, faire une sauvegarde explicite, ciblee et hors webroot

## 16. Tests et verification minimale

Les commandes de reference du depot sont:

Backend:
- `composer install --working-dir=backend`
- `composer phpstan --working-dir=backend`
- `composer phpcs --working-dir=backend`
- `composer test --working-dir=backend`

Frontend:
- `cd frontend && npm ci`
- `cd frontend && npm run lint`
- `cd frontend && npm run test:run`
- `cd frontend && npm run build`
- `cd frontend && npm run hygiene:repo`

Verification manuelle/smoke:
- `./dev.sh`
- ou `cd backend && php -S 127.0.0.1:8000 -t public public/dev-router.php`
- verifier au minimum la page publique impactee et, si pertinent, les routes admin concernees

Regles:
- tout changement backend non trivial doit au minimum passer lint/analyse/test adaptes
- tout changement frontend non trivial doit au minimum passer lint/tests/build adaptes
- tout changement HTTP, bootstrap, securite, routage, template ou admin demande une verification manuelle ciblee
- si une verification n'a pas pu etre faite, le signaler explicitement

## 17. CI et exigences implicites

La CI du depot verifie deja notamment:
- install backend et frontend
- build frontend publie
- `npm run hygiene:repo`
- `composer phpstan`
- `composer phpcs`
- `composer test`
- `npm run lint`
- `npm run test:run`
- `composer audit`
- `npm audit --audit-level=high`
- smoke HTTP sur home et routes admin

Implication:
- ne pas introduire un changement local qui contredit ces attentes CI
- si un changement modifie une commande, un contrat de build ou une route smoke-testee, mettre a jour la CI et la documentation associee

## 18. Documentation

La documentation fait partie du travail.

Obligatoire quand c'est pertinent:
- mettre a jour le README ou le document de domaine concerne
- ajouter une note datee quand le depot suit deja cette convention
- documenter prerequis, impacts de cache/build/deploiement, verification manuelle et limites connues

Regles de style:
- docs en francais
- rester concret
- documenter le comportement reel, pas une intention abstraite

## 19. Git et hygiene du depot

Ne pas versionner ni restaurer par erreur:
- `frontend/dist/**`
- `backend/public/.vite/**`
- bundles generes de `backend/public/assets/**`
- `backend/public/uploads/**` runtime
- `backend/data/logs/**`
- `backend/var/**`
- `vendor/`
- `frontend/node_modules/`

Regles:
- ne jamais melanger changement produit et artefacts locaux
- ne jamais utiliser une purge destructive ou un reset large pour masquer un probleme de comprehension
- si un nettoyage du depot est en jeu, suivre les notes de hygiene deja documentees dans le depot

## 20. Synthese des obligations

Toujours:
- respecter le front-controller, le bootstrap commun et les wrappers de compatibilite existants
- preferer `backend/src/` pour la logique moderne
- garder `frontend/src/` comme source canonique des assets et interactions
- identifier le stockage editorial actif avant de conclure qu'une page est publiee
- synchroniser SQL apres modification JSON quand `EDITORIAL_STORAGE=sql`
- maitriser les droits, les sources et la taille des images ajoutees
- utiliser i18n pour les textes visibles
- verifier avant de conclure
- documenter les changements significatifs

Jamais:
- coder la logique metier dans `backend/public/` ou dans les templates
- modifier un artefact genere a la place de sa source
- considerer un contenu uniquement present en JSON comme disponible si le rendu actif lit SQL
- contourner la securite existante
- laisser du debug temporaire
- exposer des secrets
- casser le contrat entre PHP, Vite, manifest et assets publies
