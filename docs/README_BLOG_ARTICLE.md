# Guide de rédaction des articles de blog

Ce document complète `AGENTS.md` et `README_BLOG.md` pour la rédaction des articles de blog. Il fixe la forme attendue avant sauvegarde dans `backend/data/blog/*.json` ou import SQL.

## Principe

Un article de blog n'est pas une note de travail. Il doit être lisible et terminé dès le premier jet.

Le texte parle au lecteur à travers le sujet traité : une voiture, une marque, une pièce, un geste d'entretien, une période industrielle, une balade ou une expérience réelle. Il ne parle jamais de sa propre fonction, de son intérêt SEO, de sa version future ni du travail de rédaction.

## Style attendu

Règles de fond :
- commencer par un fait concret : date, modèle, usage, lieu, panne, pièce, décision d'achat, état d'une voiture ou repère historique
- écrire court à moyen, sans emphase et sans phrases de remplissage
- donner des informations vérifiables plutôt que des intentions générales
- dire quand une réponse dépend du modèle exact, de l'état de la voiture, du pays, de la disponibilité des pièces ou de la qualité d'une restauration
- conserver une voix claire, directe et sobre
- intégrer les liens internes dans des phrases utiles, sans bloc standardisé

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
- champs `alt`, `title`, `caption`, `width`, `height` renseignés quand possible

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

## Articles en série

Une série rattachée à une page pilier doit rester cohérente.

Chaque article couvre un angle unique. Il ne répète pas la page parent et ne détourne pas le lecteur vers un autre dossier sans raison éditoriale solide.

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

La taxonomie ne doit jamais être étendue depuis un article isolé. Si un besoin apparaît, vérifier d'abord que les tags génériques existants ne suffisent pas : `histoire`, `modele`, `version`, `restauration`, `entretien`, `mecanique`, `carrosserie`, `collection`, `route`, `experience`, `patrimoine`, `evenement`.

## Langues

Pour chaque slug publié ou préparé à la publication, trois fichiers doivent exister :
- `backend/data/blog/<slug>.fr.json`
- `backend/data/blog/<slug>.en.json`
- `backend/data/blog/<slug>.de.json`

Le français est la version maître. Les versions anglaise et allemande adaptent le texte pour rester naturelles dans chaque langue, mais elles doivent refléter le fond français sans omission, ajout non justifié ni changement de sens. Elles conservent les mêmes faits, les mêmes limites, le même rattachement, la même taxonomie, les mêmes informations pratiques et un maillage interne équivalent.
