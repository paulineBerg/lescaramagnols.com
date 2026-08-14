# Blog V1 (SQL maitre, JSON compatible, Dual-Write)

Date de mise à jour : 2026-08-14

Ce document fixe la gouvernance technique du module blog V1, avec persistance pilotable par mode.

Reference complementaire :
- `docs/consolidation-lot-d.md`

## Statut

Le blog reste en mode produit `experimental`, mais la persistance est désormais alignée sur la stratégie éditoriale globale :
- `sql` : mode maitre attendu pour ce depot, en lecture/ecriture
- `json` : source historique, miroir volontaire ou preparation hors SQL
- `dual-write` : mode transitoire outille pour synchroniser JSON et SQL

Mise a jour 2026-04-16 :
- dans le cadre du Lot D, le blog/discussions/persistance SQL forme un domaine de consolidation autonome (`backend/src/Blog/*`, `backend/sql/editorial/005_blog.sql`, tests `backend/tests/Blog*` et endpoints associes)
- la note de consolidation recommande de sortir ce bloc en commit dedie, distinct du socle HTTP/admin et distinct de l'observabilite

Configuration :
- `BLOG_STORAGE=json|dual-write|sql`
- fallback automatique sur `EDITORIAL_STORAGE` si `BLOG_STORAGE` absent
- pour ce depot, la cible de travail et de verification est `BLOG_STORAGE=sql` ou, a defaut, `EDITORIAL_STORAGE=sql`

## Périmètre fonctionnel

Actif :
- sauvegarde d’article admin
- workflow de statut admin `draft` / `scheduled` / `published`
- taxonomie blog canonique : catégorie obligatoire, sous-catégorie optionnelle et tags autorisés
- image de couverture article (URL ou upload admin) avec métadonnées (`alt`, `title`, `caption`, `width`, `height`)
- lecture publique (`/blog`, `/blog/article/{slug}`)
- hub `/blog` piloté par la route publique blog, avec titre et intro éditoriaux stockés dans `admin/pages` via la page publiée de route `/blog`
- rendu des discussions publiques sous l’article blog et sous les chroniques blog rattachées aux pages dynamiques
- flux RSS et sitemap basés sur les articles publiés
- discussions publiques modérées (`pending`, `approved`, `rejected`)
- modération admin (`Discussions`)

Hors périmètre :
- pas de création d’article publique
- pas de compte front-office obligatoire

## Couche de référence

- contrats : `backend/src/Blog/BlogRepositoryInterface.php`, `backend/src/Blog/BlogDiscussionRepositoryInterface.php`
- impl JSON : `JsonBlogRepository`, `JsonBlogDiscussionRepository`
- impl SQL : `SqlBlogRepository`, `SqlBlogDiscussionRepository`
- bridge dual-write : `DualWriteBlogRepository`, `DualWriteBlogDiscussionRepository`
- taxonomie : `backend/config/blog_taxonomy.php`, lue par `BlogTaxonomy`
- câblage runtime : `backend/core/helpers.php` (`blog_storage_mode`, `blog_repository`, `blog_discussion_repository`)

Tables SQL :
- `{{prefix}}blog_articles`
- `{{prefix}}blog_discussions` (FK avec suppression en cascade sur article)

Migration :
- `backend/sql/editorial/005_blog.sql`
- `backend/sql/editorial/011_blog_taxonomy_subcategory.sql`

## Taxonomie blog

La source de vérité unique est `backend/config/blog_taxonomy.php`. Elle contient :
- catégories principales
- sous-catégories dépendantes des catégories
- tags autorisés
- traductions `fr`, `en`, `de`
- statut SEO `index` ou `noindex`

Structure cible :
- 4 catégories principales maximum : `auto-retro`, `territoire`, `vie-locale`, `patrimoine`
- 8 sous-catégories maximum au total : `histoire-automobile`, `modeles-et-versions`, `restauration-et-entretien`, `conduite-et-collection`, `golfe-saint-tropez`, `villages-et-balades`, `evenements-et-animations`, `lieux-et-memoire`
- 30 tags maximum dans le référentiel, tous réutilisables sur plusieurs contenus
- 1 catégorie obligatoire par article
- 0 ou 1 sous-catégorie
- 3 à 5 tags maximum, tous issus du référentiel

Normalisation :
- valeurs stockées en minuscules, sans accents, au format `kebab-case`
- pas de tag libre
- pas de catégorie libre
- pas de création automatique depuis une saisie
- pas de synonymes stockés : `mini`, `Austin Mini` et `mini austin` sont normalisés vers `mini-austin`
- pas de tags trop spécifiques pour une seule variante quand un tag générique suffit (`modele`, `version`, `histoire`, `mecanique`, `collection`)
- les libellés affichés viennent des traductions du référentiel

SEO :
- les catégories principales restent indexables quand elles portent un volume éditorial réel
- les tags sont `noindex` par défaut
- les pages filtrées par tag émettent `meta name="robots" content="noindex,follow"`

Maillage :
- les suggestions d’articles utilisent la taxonomie
- priorité : même sous-catégorie, même catégorie, puis au moins 2 tags communs
- limite : 3 articles suggérés maximum

Diagnostic :
- commande : `composer blog-taxonomy-diagnose`
- équivalent direct : `php core/tools/diagnose_blog_taxonomy.php`
- sortie JSON : `php core/tools/diagnose_blog_taxonomy.php --json`
- le diagnostic détecte les anomalies de référentiel, catégories anciennes, tags inconnus, doublons, accents, variantes et mappings nécessaires
- la sortie doit conserver `taxonomy_config_issues: []` et `issues: []` avant commit
- aucun nettoyage destructif ne doit être fait sans backup du stockage actif

## Maillage interne blog

Le blog ne doit pas fonctionner comme une collection isolée. Chaque article publié est rattaché à une page parent (`page_slug`) et cette page parent sert de page pilier, de page de contexte ou de page d’entrée éditoriale.

## Qualité éditoriale attendue

Un article de blog doit être publiable tel quel dès sa création. Il ne doit pas ressembler à une note de préparation, à une consigne SEO ou à un plan de rédaction.

Règles obligatoires :
- écrire un article fini, lisible, concret et vrai, sans promesse de réécriture ultérieure
- parler du sujet au lecteur, jamais de l’article lui-même
- supprimer les tournures méta : `ce brouillon`, `cet article`, `l'article doit`, `le but est`, `version publiée`, `utile pour`, `pour le lecteur`, `le premier réflexe utile consiste à`, `segmenter le sujet`
- remplacer les généralités par des faits observables : dates, modèles, pièces, gestes d’entretien, symptômes, contraintes de restauration, état de marché, contexte industriel, limites connues
- relire chaque phrase pour supprimer les formulations abstraites ou flottantes qui restent au niveau du concept (`logique`, `dynamique`, `aura`, `approche`, `cadre`, `esprit`) quand elles ne sont pas immédiatement reliées à un fait
- exiger dans chaque paragraphe au moins un ancrage concret : lieu, moment, action, pièce, usage, symptôme, étape d’atelier, version, document, prix, distance, état ou conséquence visible
- préférer systématiquement l’observation, le contrôle, la chronologie ou le geste réel à l’idée générale; si un passage ne peut pas être montré, daté, situé ou vérifié, il doit être réécrit ou supprimé
- dire clairement quand une réponse dépend du modèle exact, de l’état de la voiture, du pays, de la disponibilité des pièces ou du niveau de restauration
- refuser le remplissage, les certitudes de façade et les conclusions abstraites
- garder un ton sobre et explicatif; ne pas remplacer une abstraction par une prose touristique, promotionnelle ou décorative
- conserver un maillage interne sobre et utile, intégré dans une phrase normale
- appliquer les fourchettes de profondeur, le contrôle des formulations en série et la vérification des sources définis dans `docs/blog/article-writing-guide.md`
- comparer le corpus avant validation : ouvertures, plans, transitions et conclusions répétées rendent le lot non publiable même si chaque texte paraît correct isolément

En stockage SQL maitre, chaque slug de blog public doit disposer de trois entrees alignees :
- `fr`
- `en`
- `de`

Si un miroir JSON est volontairement maintenu pour versionnement ou export, il doit rester aligne :
- `slug.fr.json`
- `slug.en.json`
- `slug.de.json`

Le français est la version maître. Les versions anglaise et allemande doivent refléter le fond français sans omission, ajout non justifié ni changement de sens : mêmes faits, mêmes limites, mêmes liens utiles, même rattachement, même taxonomie et informations pratiques équivalentes. L’adaptation naturelle du ton et de la formulation est admise, mais elle ne doit pas modifier le contenu éditorial.

Les versions anglaise et allemande doivent donc rester complètes et relues; jamais coquilles vides, jamais résumés approximatifs de la version française.

### Cible canonique des liens internes

Quand l’article possède une page parent publiée, les liens internes vers cet article doivent viser la lecture attachée sous la page parent :
- format : `/<lang>/<route-parent>?open_article=<slug>#attached-article-<slug>`
- exemple : `/fr/auto-retro/austin?open_article=austin-seven-voiture-populaire-anglaise#attached-article-austin-seven-voiture-populaire-anglaise`

La route `/blog/article/<slug>` reste disponible comme fallback technique, mais elle ne doit pas être utilisée comme cible principale du maillage interne si l’article est diffusé sous une page parent.

### Priorité éditoriale

Ordre recommandé pour un article standard :
- lien principal vers la page parent ou vers une ancre précise de cette page
- liens vers articles frères rattachés à la même page parent
- liens vers article parent ou enfant quand cette relation est définie dans le blog
- liens vers articles proches par taxonomie : même sous-catégorie, même catégorie, puis au moins 2 tags communs
- lien vers catégorie seulement si cela aide la navigation; éviter de pousser les pages tag dans le corps car elles sont `noindex`

Limites :
- 2 à 4 liens internes utiles pour un article standard
- moins pour un article court
- 3 articles suggérés maximum dans les blocs automatiques
- aucun lien vers la page ou l’article actuellement affiché

### Ancres sur pages piliers

Ajouter une ancre seulement lorsqu’elle sert un vrai repère durable :
- section d’histoire d’une marque
- section technique ou restauration
- bloc modèles
- accès, parcours, visite ou contexte local
- section destinée à recevoir plusieurs liens depuis le blog

Convention :
- utiliser un `id` HTML court, stable, descriptif et en `kebab-case`
- exemples : `#histoire-longbridge`, `#restauration-pieces`, `#modeles-austin`, `#conduite-ancienne`
- ne pas créer d’ancre sur un paragraphe ponctuel ou une phrase susceptible d’être réécrite
- ne pas créer une ancre différente pour chaque article si une ancre pilier commune suffit

Quand une ancre existe, le lien depuis l’article doit pointer vers la section utile de la page parent plutôt que vers le haut de page. Quand aucune section stable n’existe, il vaut mieux ajouter une courte section pilier claire que multiplier des liens vagues.

### Forme dans les contenus

Les liens doivent être intégrés dans des phrases éditoriales naturelles. Ne pas créer de section standardisée intitulée `A lire`, `À lire` ou `À lire aussi`. Une courte phrase de fermeture suffit souvent, par exemple pour relier une expérience de conduite à la page parent Austin, ou un article technique à une section restauration.

## Séries d’articles rattachées à une page pilier

Quand plusieurs articles sont créés autour d’une même page parent, ils doivent former un dossier clair et contrôlé. L’objectif est de renforcer la page pilier, pas de produire des articles généraux qui se concurrencent entre eux.

### Préparation obligatoire

Avant d’écrire les articles :
- définir la page parent (`page_slug`) qui servira de page pilier
- définir les thèmes du dossier sans en ajouter d’autres si la structure est déjà validée
- définir les slugs publics avant rédaction
- attribuer 1 mot-clé principal par article
- vérifier qu’aucun article existant ne couvre déjà le même angle

### Règles de contenu

Chaque article doit avoir :
- un titre précis, non générique
- un angle unique
- une période, un modèle ou une question bien délimitée
- une catégorie logique, une sous-catégorie cohérente si utile, et 3 à 5 tags autorisés
- une structure lisible : le titre de l’article est le H1 rendu par le template, le corps utilise des H2 et des H3 seulement si le contenu le justifie

À éviter :
- refaire un article général déjà couvert par la page pilier
- mélanger plusieurs périodes dans un même article
- dériver vers d’autres pages parents déjà créées quand elles ne font pas partie du dossier courant
- créer une catégorie ou un tag pour un seul article

### Maillage minimum

Chaque article d’une série doit contenir :
- 1 lien vers la page principale ou vers une ancre stable de cette page
- 1 lien vers un autre article du même thème, rattaché à la même page parent

Les liens doivent rester naturels. Si aucun article frère pertinent n’existe encore, créer les brouillons du thème dans un ordre cohérent puis relier les articles entre eux avant publication.

### Contrôle de cohérence

Avant sauvegarde ou publication, vérifier :
- unicité des slugs
- cohérence entre titre, slug, mot-clé principal et extrait
- rattachement à la bonne page parent
- taxonomie autorisée
- absence de lien mort ou de lien vers l’article lui-même
- absence de concurrence inutile avec la page pilier ou avec un autre article du même dossier

## Publication planifiée automatique

La planification ne dépend pas d’un cron :
- statut `scheduled` côté admin + date planifiée (champ "Publication programmée"),
- publication automatique à la lecture publique dès que la date est atteinte,
- comportement appliqué de manière homogène sur les repositories JSON et SQL.

Commande d’exécution automatique (CLI) :
- `php backend/core/tools/plan_next_blog_article.php`
- `php backend/core/tools/plan_all_blog_drafts.php`
- options :
  - `--dry-run` : simule la sélection et affiche le résultat sans persister
  - `--json` : sortie JSON pour intégration CI/exploitation
  - `--now=YYYY-MM-DD HH:MM:SS` : référence temporelle pour calcul de date (facultatif)
- en cas de succès, la commande met à jour l’article en statut `scheduled` et relance le maillage interne sur les articles `published`/`scheduled`.
- `plan_all_blog_drafts.php` applique la même règle en boucle sur tous les brouillons restants, puis reconstruit le maillage une seule fois en fin de lot; `--limit=N` permet de s’arrêter après `N` slugs logiques.

Règle de sélection 11 jours / rotation de séries :
- regrouper les brouillons par `page_slug` (cluster)
- à l’intérieur d’un cluster, raisonner par article logique (`slug`) et non par variante de langue
- compter le quota `published + scheduled` par `slug` distinct, pas par entrée `fr` / `en` / `de`
- ne pas dépasser `5` `published + scheduled` par cluster
- prioriser le cluster actif ayant le moins d’articles publiés/planifiés
- si plusieurs clusters actifs ont le même minimum, départager ce groupe ex aequo par une rotation pseudo-aléatoire déterministe qui change toutes les `5` planifications logiques globales; à fenêtre égale, le même état doit redonner le même cluster
- choisir dans ce cluster le brouillon le plus ancien, puis planifier ensemble toutes ses variantes brouillon disponibles à la même date
- si un `slug` a déjà une variante `scheduled` ou `published`, aligner les brouillons restants sur cette date existante au lieu de créer une seconde date
- si tous les clusters sont pleins, utiliser le brouillon le plus ancien global
- date de planification = `+11 jours` après la date `scheduled` la plus récente (ou après « aujourd’hui » s’il n’y en a aucune)
- conserver strictement les statuts `draft` / `scheduled` / `published` existants

Commande cron optionnelle :
- `php backend/core/tools/publish_scheduled_blog_articles.php`
- cette commande promeut réellement les articles `scheduled` arrivés à échéance en statut `published`
- elle reste utile pour garder l’admin, les exports et les vérifications d’exploitation alignés avec le front
- l’admin `Paramètres > Observabilité ops` affiche le binaire PHP détecté, le chemin du script et une ligne cron prête à copier

Règles :
- `published` : visible immédiatement en front, RSS et sitemap.
- `scheduled` avec date future : non visible publiquement.
- `scheduled` avec date atteinte : visible comme un article publié (front, RSS, sitemap).
- `draft` : non visible publiquement.

## Images article (V1)

La création d'un article de blog doit respecter les règles médias suivantes (V1) :
- image de couverture obligatoire par article
- minimum une image dans le contenu (hors intro), utile et lisible, placée selon la structure éditoriale
- une seconde image dans le contenu est possible si elle apporte une information réelle (comparatif, repère technique, preuve visuelle utile)
- pas d'images décoratives, d'empilement visuel ni de doublons entre couverture et corps
- si le sujet le justifie, privilégier une courte galerie finale plutôt qu'une accumulation de visuels en plein texte
- image de couverture doit porter `alt`, `title`, `caption`, `width` et `height` quand l'information existe
- saisie manuelle via chemin public (`/assets/images/...`) ou URL `https://...`
- si un nouvel asset versionne sous `/assets/images/...` vient d'etre ajoute dans `frontend/src/assets/images/**`, lancer d'abord `npm run build` ou `npm run postbuild` pour le publier dans `backend/public/assets/images/**` avant de sauvegarder l'article
- upload admin sécurisé (JPG/PNG/WebP/GIF/AVIF) vers `backend/public/uploads/editorial/article/YYYY/MM`
- dimensions explicitables pour limiter les CLS
- rendu front sur :
  - liste blog (`/blog`)
  - détail article (`/blog/article/{slug}`)
  - chroniques attachées aux pages dynamiques
- SEO : `og:image` + `twitter:image` injectés quand une image de couverture est définie.

Contrainte déploiement :
- le dossier `backend/public/uploads/editorial/**` doit être préservé (pas de suppression en release).

## Import / exploitation

Commandes d’exploitation :
- `composer blog-import-sql` : import JSON -> SQL (articles + discussions)
- `composer blog-import-sql -- --articles-only`
- `composer blog-import-sql -- --discussions-only`
- `composer blog-import-sql -- --no-prune`

Backup/restore éditorial (pages/navigation/blog/discussions) :
- `php backend/core/tools/editorial_backup_restore.php backup [--storage=json|sql|dual-write]`
- `php backend/core/tools/editorial_backup_restore.php restore <backup.json> --force [--storage=json|sql|dual-write]`

### Migration legacy JSON -> SQL recommandee

1. `composer blog-import-sql`  
2. `.env` preprod -> `BLOG_STORAGE=dual-write`  
3. recette front/admin + checks securite  
4. `.env` preprod -> `BLOG_STORAGE=sql`  
5. clear cache runtime + re-validation

Preuves :
- archiver sorties CLI et captures dans `docs/private/recette-preprod-v1-YYYY-MM-DD/`

## Sécurité et intégrité

- suppression article -> suppression des discussions associées :
  - SQL : FK `blog_discussions(article_slug, article_lang)` -> `blog_articles(slug, lang)` en `ON DELETE CASCADE`
  - JSON : suppression explicite du thread dans `AdminBlogService::delete()`
- soumission discussion publique durcie : CSRF, nonce de formulaire, honeypot, rate limiting, reCAPTCHA optionnel
- télémétrie discussion (`/core/blog/log_discussion_client.php`) :
  - limitation par IP et endpoint via `FileRateLimiter`
  - seuils configurables : `site.discussions.telemetry_rate_limit_per_ip`, `site.discussions.telemetry_rate_limit_window`, `site.discussions.telemetry_sample_divisor`
  - réponse minimale (`204`) en cas de dépassement de quota
  - payload évènement limité (longueurs de chaînes/longueur de tableaux bornées) avant écriture

## Tests couverts

- `backend/tests/Blog/JsonBlogRepositoryTest.php`
- `backend/tests/Blog/JsonBlogDiscussionRepositoryTest.php`
- `backend/tests/Blog/SqlBlogRepositoryTest.php`
- `backend/tests/Blog/SqlBlogDiscussionRepositoryTest.php`
- `backend/tests/Blog/DualWriteBlogRepositoryTest.php`
- `backend/tests/Blog/BlogSchedulePlannerTest.php`
- `backend/tests/Blog/BlogInternalLinksRebuilderTest.php`
- `backend/tests/BlogDiscussionApiControllerTest.php`
- `backend/tests/RssFeedServiceTest.php`
- `backend/tests/SitemapServiceTest.php`
