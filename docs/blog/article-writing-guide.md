# Guide de rédaction des articles de blog

Ce document complète `AGENTS.md` et `docs/blog/README.md` pour la rédaction des articles de blog. Il fixe la forme attendue avant sauvegarde en SQL, qui est le stockage maître de ce dépôt pour le blog. Un miroir `backend/data/blog/*.json` peut exister, mais il ne remplace pas l’écriture SQL active.

## Principe

Un article de blog n'est pas une note de travail. Il doit être lisible et terminé dès le premier jet.

Le texte parle au lecteur à travers le sujet traité : une voiture, une marque, une pièce, un geste d'entretien, une période industrielle, une balade ou une expérience réelle. Il ne parle jamais de sa propre fonction, de son intérêt SEO, de sa version future ni du travail de rédaction.

## Style attendu

Règles de fond :
- commencer par un fait concret : date, modèle, usage, lieu, panne, pièce, décision d'achat, état d'une voiture ou repère historique
- donner au sujet la profondeur nécessaire, sans emphase et sans phrases de remplissage
- donner des informations vérifiables plutôt que des intentions générales
- relire chaque phrase pour retirer les mots abstraits qui commentent le sujet sans le montrer
- exiger dans chaque paragraphe au moins un élément concret : lieu, moment, action, pièce, symptôme, opération, contrôle, usage ou conséquence visible
- remplacer les formulations de type concept, idée, logique, approche, cadre, aura ou esprit par une observation, un fait daté, une action menée ou un résultat visible
- dire quand une réponse dépend du modèle exact, de l'état de la voiture, du pays, de la disponibilité des pièces ou de la qualité d'une restauration
- conserver une voix claire, directe et sobre
- garder cette sobriété sans glisser vers la description touristique, la formule d'ambiance ou l'effet de brochure
- intégrer les liens internes dans des phrases utiles, sans bloc standardisé
- décrire directement les indices visibles au lieu de les présenter comme une « lecture » : nommer la calandre, le pare-brise, le pavillon, la baguette, le badge, la sellerie, la pièce ou le montage concernés
- contrôler le vocabulaire à l'échelle de la série : les mots `lire`, `lecture`, `lisible`, `cohérence`, `ensemble` et `repère` ne sont pas interdits, mais leur répétition doit conduire à une reformulation concrète

## Profondeur attendue

Le volume sert la promesse du titre. Les fourchettes ci-dessous sont des seuils de contrôle pour la version française, pas des objectifs à remplir avec des reformulations :

- identification, achat, entretien ou restauration : 900 à 1 400 mots utiles
- histoire d'un modèle, d'une marque ou d'une usine : 900 à 1 500 mots utiles
- patrimoine, village, route ou événement : 700 à 1 200 mots utiles
- sujet volontairement étroit : 600 mots au minimum, avec justification éditoriale si une question annoncée ne demande réellement pas davantage

Un article plus court est à enrichir ou à resserrer avant validation. S'il manque des informations vérifiables, il reste hors publication : aucune date, caractéristique, anecdote, citation ou expérience ne doit être inventée pour atteindre une longueur.

Selon son angle, le développement couvre les éléments réellement utiles parmi les suivants :

- contexte daté et périmètre exact : modèle, version, millésime, lieu ou événement
- différences entre variantes et risques de confusion
- indices observables, méthode de contrôle ou déroulé de l'action
- conséquence pratique pour l'achat, la conduite, l'entretien, la restauration ou la visite
- exceptions, incertitudes et cas où la documentation d'atelier, un club, une archive ou un spécialiste devient nécessaire
- sources qui permettent de vérifier les faits historiques, techniques ou pratiques

Chaque section doit apporter une information nouvelle. Une seconde formulation de la même idée, une transition générale ou une conclusion qui résume tous les intertitres ne compte pas comme approfondissement.

## Tournures interdites

Ces formulations doivent être supprimées ou réécrites avant validation :
- `ce brouillon`
- `cet article`
- `l'article doit`
- `le but est`
- `le bon brouillon`
- `version publiée`
- `utile pour le lecteur`
- `pour le lecteur`
- `le premier réflexe utile consiste à`
- `segmenter le sujet`
- `un bon article`
- `un article pratique utile`
- `page de référence`
- `le sujet gagne en clarté`
- `donne au sujet une fonction`
- `certitudes de façade`
- `pour la lire correctement`
- `la lecture visuelle`
- `la lecture de profil`
- `il faut donc lire ensemble`
- `la bonne identification ne repose pas sur un seul signe`
- `replacer la voiture dans la dernière grande phase`

Remplacement attendu : écrire directement le fait, le contrôle, la limite ou le conseil pratique.

Exemple :
- a éviter : `L'article doit insister sur la corrosion.`
- attendu : `La corrosion se vérifie d'abord sur les bas de caisse, les planchers, les passages de roue et les points d'ancrage. Une mécanique saine ne compense pas une caisse affaiblie.`

## Média (obligatoire)

Pour chaque article de blog :

- image de couverture obligatoire
- minimum une image dans le corps de texte (ou une région équivalente), pour clarifier le sujet
- seconde image de corps autorisée uniquement si elle apporte une information précise (comparatif, pièce, mécanique, usage, repère terrain)
- pas d'images décoratives, pas de doublons visuels entre couverture et corps
- images limitées et utiles, structurées autour du récit éditorial
- champs `alt`, `title`, `caption`, `width`, `height` obligatoires; ne pas publier une image sans dimensions explicites dans le HTML final

## Articles pratiques

Un article pratique doit servir une décision ou un geste réel.

Il précise :
- le modèle ou la famille de modèles concernée
- les points à contrôler
- les signes d'alerte
- les limites du conseil donné
- les cas où un spécialiste, une documentation d'atelier ou un club devient nécessaire

Il ne promet pas de solution universelle. Si le sujet dépend du modèle, le texte le dit et oriente le lecteur vers les vérifications concrètes.

## Articles historiques

Un article historique garde une chronologie lisible.

Il précise :
- les dates utiles
- les personnes ou groupes industriels concernés
- le rôle de l'usine, de la gamme ou du contexte économique quand ils expliquent vraiment le sujet
- ce que la période change pour les modèles visibles aujourd'hui

Il évite les grands résumés abstraits. Une fusion, une usine ou une crise industrielle doit toujours être reliée à une conséquence concrète : badge, gamme, moteur, plateforme, production, pièces, image publique ou usage en collection.

Les faits historiques et techniques qui ne relèvent pas d'une observation directe sont accompagnés de sources identifiables. Une source constructeur, une notice, un catalogue, une archive, un musée, un club de référence ou un ouvrage spécialisé est préférable à une reprise anonyme. Deux sources indépendantes sont recherchées lorsqu'une date, une attribution ou une interprétation est discutée. Les liens sont vérifiés avant publication et la bibliographie ne doit jamais être fabriquée.

## Articles en série

Une série rattachée à une page pilier doit rester cohérente.

Chaque article couvre un angle unique. Il ne répète pas la page parent et ne détourne pas le lecteur vers un autre dossier sans raison éditoriale solide.

La cohérence ne signifie pas reproduire le même plan. Comparer les ouvertures, les intertitres, les transitions et les dernières phrases de la série. Deux articles ne doivent pas se distinguer uniquement par le nom du modèle, du village, de la pièce ou du millésime. Alterner le format lorsque le sujet le justifie : guide de contrôle, chronologie commentée, comparaison de versions, récit documenté, fiche technique expliquée ou parcours de visite.

Contrôles avant sauvegarde :
- slug unique
- `lang` disponible en `fr`, `en` et `de`
- titre précis
- extrait distinct du titre
- catégorie et tags issus de `backend/config/blog_taxonomy.php`
- catégorie obligatoire parmi `auto-retro`, `territoire`, `vie-locale`, `patrimoine`
- 0 ou 1 sous-catégorie, obligatoirement rattachée à la catégorie choisie
- 3 à 5 tags autorisés, sans doublon ni variante libre
- tags normalisés en `kebab-case`, sans accents ni majuscules
- page parent cohérente
- aucun lien vers l'article lui-même
- aucun lien mort
- absence des tournures interdites listées plus haut
- absence d'amorces, transitions, conclusions et groupes de mots répétés mécaniquement dans le corpus
- longueur cohérente avec le type d'article et couverture complète de la promesse du titre
- faits, dates, caractéristiques et sources vérifiés ; incertitudes signalées sans les masquer par une formule vague

Contrôle automatisé du corpus :

```bash
composer --working-dir=backend blog-editorial-quality
php backend/core/tools/check_blog_editorial_quality.php --backup=/chemin/backup-editorial.json.gz --json
php backend/core/tools/check_editorial_media.php --backup=/chemin/backup-editorial.json.gz --check-published-assets --json
```

La commande bloque sur les variantes manquantes, les textes français sous le seuil de contrôle, les adaptations nettement abrégées, les tournures interdites, les sources insuffisantes et les phrases longues répétées sur au moins trois slugs. Elle signale aussi les articles dont le maillage demande une revue. Ce contrôle complète la relecture éditoriale et factuelle ; il ne la remplace pas.

Pour repartir d'une sauvegarde SQL de production sans toucher aux pages, à la navigation, aux tuiles ni aux discussions, synchroniser le miroir JSON en deux temps :

```bash
php backend/core/tools/sync_blog_backup_to_json.php /chemin/backup-editorial.json.gz
php backend/core/tools/sync_blog_backup_to_json.php /chemin/backup-editorial.json.gz --apply
```

Le mode par défaut est une simulation. Toute suppression détectée est refusée sans `--allow-delete` explicite.

La taxonomie ne doit jamais être étendue depuis un article isolé. Si un besoin apparaît, vérifier d'abord que les tags génériques existants ne suffisent pas : `histoire`, `modele`, `version`, `restauration`, `entretien`, `mecanique`, `carrosserie`, `collection`, `route`, `experience`, `patrimoine`, `evenement`.

## Langues

Pour chaque slug publié ou préparé à la publication, trois variantes linguistiques doivent exister en SQL :
- `fr`
- `en`
- `de`

Si un miroir JSON est maintenu pour versionnement ou export, il doit rester aligné :
- `backend/data/blog/<slug>.fr.json`
- `backend/data/blog/<slug>.en.json`
- `backend/data/blog/<slug>.de.json`

Le français est la version maître. Les versions anglaise et allemande adaptent le texte pour rester naturelles dans chaque langue, mais elles doivent refléter le fond français sans omission, ajout non justifié ni changement de sens. Elles conservent les mêmes faits, les mêmes limites, le même rattachement, la même taxonomie, les mêmes informations pratiques et un maillage interne équivalent.
