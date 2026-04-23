# Caramagnols Workspace AGENTS

Version de reference: 2026-04-22

Ce fichier est la source de verite pour le depot `/home/surfacepro8/www/caramagnols`.
Son but est de fixer des regles communes de developpement, d'architecture, de langage, de verification et de documentation, a partir des conventions reelles du projet.

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
- `fr` reste le texte maitre pour les pages editoriales, mais toute creation, modification significative ou suppression d'une page doit etre repercutee sur `en` et `de` dans le meme passage de travail si possible
- toute nouvelle creation editoriale publique doit etre preparee nativement en `fr`, `en` et `de`; ne pas creer seulement `fr` avec l'idee de revenir plus tard, sauf blocage explicite documente
- apres creation d'une page publique en `fr`, preparer aussi ses versions `en` et `de` avant cloture ou signaler explicitement si elles restent a faire
- apres modification d'une page publique en `fr`, mettre a jour `en` et `de` pour conserver un registre editorial aligne
- apres suppression d'une page, verifier que `fr`, `en` et `de` sont supprimes ou retires ensemble pour ne pas laisser de traduction orpheline ni de route incoherente

## 9. Ligne editoriale, style litteraire et structure des articles

Le site doit garder une voix editoriale stable sur l'ensemble de ses pages publiques, qu'il s'agisse d'auto-retro, de territoire, de vie du club ou de pages partenaires.

Positionnement editorial:
- le style recherche est litteraire, mais maitrise
- il ne doit etre ni platement administratif, ni lyrique, ni publicitaire
- l'effet litteraire doit venir de la precision, du rythme, du regard et du detail juste, pas d'une accumulation d'adjectifs
- un article doit donner au lecteur la sensation d'un sujet reellement observe, situe et compris

Ligne editoriale commune:
- pas d'invention
- partir d'un fait concret avant tout effet de style: date, lieu, situation, etat, usage, observation ou repere technique
- garder une voix claire, calme, vivante et incarnee
- faire sentir la matiere, le paysage, la mecanique ou l'ambiance par des details justes et non par des formules convenues
- conserver les informations utiles: chronologie, contexte, saisonnalite, contraintes, repere technique, etat, travaux, usage, acces, singularite
- conclure court, sans morale generale ni formule vide
- sur les sujets personnels, assumer `nous`, `notre`, `nos` quand cela correspond a une experience reelle
- sur les sujets historiques, patrimoniaux ou pratiques, preferer une voix informative stable plutot qu'un recit artificiellement intime
- `fr` reste le texte maitre; `en` et `de` doivent adapter le ton sans traduction mot a mot rigide

Ce qu'il faut viser:
- phrases fluides, le plus souvent courtes a moyennes
- paragraphes de `4` a `10` phrases
- une idee principale par paragraphe
- une alternance lisible entre information, detail concret et respiration narrative
- un vocabulaire simple, precis, avec quelques mots plus expressifs quand ils apportent une image juste
- des intertitres qui annoncent un angle reel, pas un simple effet de manche
- une progression nette: ouverture concrete, developpement organise, fermeture breve

Ce qu'il faut eviter:
- surenchere adjectivale, superlatifs automatiques et emphase patrimoniale
- conclusion grandiloquente, morale abstraite ou phrase de remplissage
- ouverture vague sans date, lieu, contexte ni fait observable
- memes tournures recyclees d'un article a l'autre
- questions rhetoriques repetitives
- langage publicitaire ou promesses marketing
- mots et tournures uses quand ils remplacent l'information
- aucune recopie directe d'une source

Tournures a employer avec parcimonie:
- `icone`, `emblematique`, `mythique`, `legendaire`, `incontournable`
- `charme`, `ecrin`, `joyau`, `petit bijou`
- `role crucial`, `empreinte indelebile`, `tournant de l'histoire`
- `avant-garde`, `design audacieux`, `fait battre le coeur`

Regle de fond:
- ces mots ne sont pas interdits par principe, mais ils ne doivent apparaitre que s'ils sont historiquement justes, rares et soutenus par un fait

Architecture canonique d'un article/Page:
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

Longueur indicative:
- si pas de demande spécifique, faire le meilleur choix de longueur selon le sujet traité
- article court : environ `800` a `1000` mots quand le sujet est simple ou tres visuel
- article standard : environ `1200` a `1800` mots
- dossier long : au-dela de `2000` mots seulement si la matiere l'impose vraiment
- ne pas allonger un article pour atteindre un volume arbitraire

Variantes attendues selon le type d'article:
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

Regles de structuration HTML/editoriale:
- un seul `h1` visible par article
- respecter la hierarchie `h1` > `h2` > `h3` sans niveau saute
- `EditRegion8 - Intro` ne peut contenir qu'une petite image d'appel ou un texte court
- pour `INTRO`, privilegier d'abord une petite image; si aucune image n'est utile, limiter le texte a un bloc tres court
- ne pas utiliser `INTRO` pour loger un second corps d'article, un long developpement ou une grande image; si le contenu depasse ce role, le basculer dans `EditRegion3 - Corps` ou une autre region adaptee
- rechercher, quand le sujet s'y prete, des images libre de droit pour agrementer les sections et ne pas se limiter aux visuels deja presents
- ne pas laisser de `h2` ou `h3` vides
- pas de tiret cadratin ou autres sigles qu'un clavier français n'a pas directement 
- preferer des paragraphes reels a des successions de lignes separees par `<br>`
- reserver les listes `ul/li` aux reperes, etapes, caracteristiques ou points pratiques
- sur les pages tres illustrees, garder des blocs de texte plus courts entre les images
- ne pas cacher l'information cle uniquement dans une legende ou une image
- si une fiche technique est utile, la presenter comme un bloc lisible et structure, pas comme un pave compact
- en fin d'articles ajouter les sources si possible, dans la section : EditRegion11 - Post-scriptum
- les sources s'ouvrent dans un nouvel onglet
- le bloc de maillage interne doit se situer dans la section : EditRegion4 - Apres corps
- ne pas creer de section intitulee `A lire`, `À lire` ou `À lire aussi`; le maillage interne doit rester sobre, descriptif et integre au parcours editorial, sans bloc standardise de recommandation

Metadonnees et completude editoriale:
- renseigner un titre d'article distinct, lisible et utile hors contexte
- renseigner un extrait qui resume l'angle de lecture sans recopier le `h1`
- choisir une categorie stable et peu nombreuse; ne pas inventer des categories inutilement
- choisir des tags concrets: marque, lieu, theme, usage, restauration, technique
- si une image de couverture est utilisee, remplir `alt`, `title`, `caption`, `width` et `height` quand l'information existe
- l'attribut `alt` decrit l'image factuellement; la legende apporte le contexte editorial si necessaire
- rattacher l'article a sa page parent ou a son article parent seulement si cela a un sens editorial reel
- verifier que les liens internes servent la lecture et ne forcent pas un maillage artificiel

Uniformite editoriale a l'echelle du site:
- un article = un angle dominant; ne pas melanger sans controle dossier historique complet, recit personnel, guide de visite et argumentaire commercial
- chaque article doit repondre implicitement a `qu'est-ce que c'est`, `pourquoi ce sujet ici`, `qu'est-ce qui est concret`, `qu'est-ce que le lecteur retient`
- chaque article doit conserver une densite utile: pas de remplissage pour "faire long"
- le maillage interne doit rester naturel et utile: pointer vers la page parent, les pages soeurs ou un contenu complementaire quand cela aide vraiment la lecture
- si le sujet ne justifie pas un long texte, preferer un article plus court mais plus tenu

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
- diffusion publique: preferer `webp` si le fichier existe, sinon `jpg`
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
- pour l'image de partage reseaux sociaux, privilegier un fichier largement compatible; un `jpg` est acceptable meme si le corps de page diffuse un `webp`
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

Le projet supporte plusieurs modes de persistence editoriale:
- `EDITORIAL_STORAGE=json|dual-write|sql`
- `BLOG_STORAGE=json|dual-write|sql`

Regles:
- ne pas ecrire directement dans plusieurs stockages sans passer par les facades/repositories prevus
- toute nouvelle logique de persistence doit respecter les interfaces existantes et les modes de stockage configures
- si une evolution touche pages, navigation, blog ou discussions, considerer l'impact sur `json`, `dual-write` et `sql`
- avant une operation destructive, privilegier les outils et scripts deja prevus (`editorial_backup_restore`, imports SQL, etc.)

Procedure recommandee pour un envoi cible en prod OVH MySQL:
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

Attention:
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
- maitriser les droits, les sources et la taille des images ajoutees
- utiliser i18n pour les textes visibles
- verifier avant de conclure
- documenter les changements significatifs

Jamais:
- coder la logique metier dans `backend/public/` ou dans les templates
- modifier un artefact genere a la place de sa source
- contourner la securite existante
- laisser du debug temporaire
- exposer des secrets
- casser le contrat entre PHP, Vite, manifest et assets publies
