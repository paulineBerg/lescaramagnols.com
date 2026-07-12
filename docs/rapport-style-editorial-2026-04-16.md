# Rapport style éditorial - 2026-04-16

## Objet

Nettoyage des formulations trop pompeuses ou trop génériques dans les contenus éditoriaux, avec `fr` comme texte maître puis adaptation `en` et `de`.

## Bilan

Travail effectué en trois passages successifs sur la base locale :

- premier lot : `33` sections réécrites
- deuxième lot : `54` sections réécrites
- troisième lot : `142` écritures complémentaires, incluant réécritures, créations de traductions `en/de` et finitions

Le volume ci-dessus correspond à des écritures en base. Certaines sections ont été reprises deux fois lors du passage de finition.

## Pages traitées

### Lot 1

- `auto-retro-austin-histoire-de-austin`
- `auto-retro-austin-aventure-mini-austin`
- `auto-retro-renault-la-twingo-une-voiture-a-succes`
- `auto-retro-simca-histoire-de-simca`
- `auto-retro-simca-histoire-simca-aronde-icone-francaise`
- `auto-retro-panhard-une-dyna-icone-automobile`

### Lot 2

- `auto-retro-renault-histoire-de-renault`
- `auto-retro-panhard-histoire-de-panhard`
- `auto-retro-mercedes-la-slk-une-voiture-compacte-sportive`
- `auto-retro-panhard-la-dyna-z-voiture-de-collection`
- `auto-retro-panhard-la-dyna-modele-z12`
- `auto-retro-simca-la-simca-9-aronde-voiture-de-collection`
- `auto-retro-simca-la-simca-aronde-1300-voiture-de-collection`
- `auto-retro-simca-la-simca-p60-voiture-de-collection`

### Lot 3

- `bouger-se-promener-a-sttropez`
- `twingo-helios-1999-notre-exemplaire`
- `bouger-les-animations-dans-le-golfe-de-sttropez`
- `auto-retro-mercedes-une-slk-dans-le-golfe-de-sttropez`
- `auto-retro-simca-une-aronde-dans-le-golfe-de-sttropez`
- `auto-retro-simca-une-simca-aronde-en-restauration-chez-sava-rioz`
- `gassin-village-perche-golfe-saint-tropez`
- `auto-retro-austin-une-mini-dans-le-golfe-de-sttropez`
- `auto-retro-panhard-une-dynaz12-dans-le-golfe-de-sttropez`
- `boulyetcailloux-des-bijoux-artisanaux`
- `bouger-se-promener-a-ramatuelle-golfe-de-sttropez`
- `bouger-se-promener-a-la-garde-freinet-golfe-de-sttropez`
- `bouger-se-promener-dans-le-golfe-de-sttropez`

## Langues

Langues révisées :

- `fr`
- `en`
- `de`

`fr` a servi de texte maître.

Deux pages qui n’existaient qu’en `fr` ont reçu des versions `en` et `de` :

- `bouger-les-animations-dans-le-golfe-de-sttropez`
- `boulyetcailloux-des-bijoux-artisanaux`

Après contrôle global du registre éditorial, deux autres pages sans sections SQL ont aussi été complétées au niveau des lignes de traduction :

- `bouger-animations-les-voiles-latines-a-sttropez`
- `bouger-les-brochures-du-golfe-de-sttropez`

## Ligne de réécriture

- partir d’un fait concret avant tout effet de style
- garder un ton vivant sans discours patrimonial automatique
- supprimer les conclusions grandiloquentes
- éviter de recycler la même voix d’un article à l’autre
- conserver les informations utiles, la chronologie et les repères techniques
- sur les pages très illustrées, préserver la structure visuelle tout en simplifiant le texte

## Tournures visées

Les formulations suivantes ont été réduites ou supprimées quand elles alourdissaient le texte :

- `empreinte indélébile`, `rôle crucial`, `tournant dans l'histoire`
- `design audacieux`, `esprit ludique`, `avant-garde`
- `icône`, `emblématique`, `légendaire`, `mythique`
- `fait battre le cœur`, `petit bijou`, `charme rétro`
- conclusions trop générales sans fait précis

## Sauvegardes locales

- `backups/db/editorial-style-backup-20260416-090106.json`
- `backups/db/editorial-style-backup-batch2-20260416-113516.json`
- `backups/db/editorial-style-backup-batch3-20260416-114433.json`

## Contrôle heuristique final en local

Après le dernier passage, le rescan global sur `fr/en/de` ne remonte plus que `4` faux positifs :

- `accueil-le-plan-du-site-des-caramagnols`
  présence du mot `icône` dans un titre de page listé par le plan du site
- `accueil-bienvenue-aux-caramagnols`
  présence du mot `bijoux` dans la présentation de Bouly&Cailloux
- `accueil-toutes-les-mentions-legales`
  présence du mot juridique `icônes`
- `boulyetcailloux-des-bijoux-artisanaux`
  présence du mot métier `bijoux`

En dehors de ces cas, le repérage résiduel est à `0`.

## État publié

Le lot a été recopié sur la base OVH.

Contrôles de clôture :

- alignement local/prod : `0` écart sur le périmètre synchronisé
- registre `fr/en/de` : `0` page manquante en local comme en prod
- vérification HTTP des 6 routes ajoutées au dernier contrôle de complétude : `200` en `fr`, `en` et `de`
