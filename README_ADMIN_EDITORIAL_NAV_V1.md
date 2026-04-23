# Modernisation Admin Editorial Et Navigation

Date de mise a jour : 2026-04-23

Ce document decrit l etat courant et les regles durables du domaine admin editorial, pages, navigation et header public.
Il remplace le journal de conception detaille par une reference plus courte et orientee exploitation.

References :
- `README.md`
- `README_MODERNISATION_V1.md`
- `README_BLOG.md`
- `backend/README_PUBLIC_ENTRYPOINTS.md`
- `docs/pages-dynamiques.md`
- `docs/README_REFONTE_LOT_C.md` et `docs/README_CONSOLIDATION_LOT_D.md` uniquement pour les audits ponctuels

## Objectif

Obtenir un socle admin coherent pour :
- creer et modifier les pages sans templates legacy paralleles
- editer les menus sans JSON brut comme parcours normal
- gerer proprement les traductions editoriales
- moderniser le header public sans casser le rendu serveur
- garder un lien propre entre contenu, navigation, SEO et stockage editorial

## Etat Courant A Retenir

### Pages

- le registre editorial public ne porte plus que des `structured_page`
- le rendu public passe par `backend/templates/pages/dynamic.php`
- l edition admin de page passe par `page_state_json` pour eviter les troncatures `max_input_vars`
- l ecran `pages_edit` groupe maintenant `fr`, `en` et `de` en onglets
- les pages supportent une image SEO par langue (`meta.image`)
- les pages supportent aussi des medias partages (`meta.shared_media`) hors traductions
- le module `Tuiles` gere les groupes `after_body` avec formats `small`, `medium`, `large` et `rectangle`

### Menus Et Navigation

- l edition normale des menus se fait via le builder visuel serveur
- `backend/config/menu_data.php` n est plus la source canonique
- la persistance passe par `backend/src/Navigation/NavigationRepository.php`
- les stockages supportes restent `json`, `dual-write` et `sql`
- les labels de navigation peuvent etre multilingues avec fallback explicite
- un item de menu peut pointer vers une page via `page_slug`

### Header Public

- le header est alimente par un view model backend unique
- le desktop garde l ouverture au survol et ajoute le tap/clic pour les ecrans tactiles
- les sous-menus desktop imbriques s ouvrent sous leur parent
- le mobile applique la fusion homonyme groupe / premier enfant pour eviter les doublons inutiles
- dans le mega menu desktop, une meme section est coupee apres `5` liens consecutifs par colonne
- le panneau desktop utilise maintenant toute la largeur utile au lieu de rester artificiellement borne

### Admin Et Services

- `AdminController` reste mince et delegue vers des services et normalizers dedies
- `AdminNavigationService` et `AdminSettingsService` ont deja ete decoupes en sous-composants
- un script de backup / restore editorial existe : `backend/core/tools/editorial_backup_restore.php`
- le tableau de bord admin met en avant les elements d exploitation critiques, en particulier les discussions en attente

## Regles D Architecture

### 1. Rendu Serveur Conserve

L admin et le front restent rendus cote PHP.
Le JavaScript sert l ergonomie, pas la gouvernance metier principale.

### 2. UI Technique Et Contenu Editorial Separent

- UI technique : `backend/lang/*.php`
- contenu editorial : pages, blog, navigation et metadonnees via les repositories dedies

Il ne faut pas melanger ces deux familles de traductions.

### 3. Une Page Canonique, Pas Une URL Brute Comme Contrat Metier

Les liens de navigation doivent preferer un rattachement par `page_slug` ou par structure metier equivalente.
Cela permet de garder les routes, les brouillons, le SEO et les traductions coherents.

### 4. Le Builder Menus Est La Voie Normale

Le JSON brut n est plus un ecran principal.
S il reste un mode expert, il doit servir au depannage ou a l inspection, pas au workflow quotidien.

### 5. Les Repositories Sont Les Sources De Verite

Pour les pages et la navigation :
- le stockage passe par les facades metier
- `json`, `dual-write` et `sql` doivent rester compatibles tant que la transition n est pas close
- les wrappers legacy ne doivent plus embarquer la logique metier centrale

### 6. Le Header Public Part D Un Seul View Model

Le desktop et le mobile doivent rester alimentes par la meme source backend.
Les differences de rendu ne doivent pas dupliquer les regles metier de navigation.

## Cibles De Domaine

### Pages

Le contrat cible d une page editoriale est :
- une page `structured_page`
- des regions semantiques stables
- des metadonnees SEO par langue
- des medias partages clairement separes du texte traduit
- des rattachements optionnels comme les groupes de tuiles `after_body`

### Menus

Le contrat cible d un item de navigation est :
- un type clair
- un libelle principal et, si besoin, des traductions
- une cible metier stable (`page_slug`, route ou URL selon le cas)
- un stockage independant du mode `json` ou `sql`

### Tuiles

Le module `Tuiles` est la source de verite pour :
- les groupes
- les items
- leurs formats et traductions
- leurs placements `after_body`
- leur reordonnancement direct dans l ecran d edition via des actions `Monter` et `Descendre`

L ecran `Pages` ne doit gerer que le rattachement local et les surcharges strictement utiles.

## Priorites Encore Ouvertes

Les sujets encore utiles dans ce domaine sont :
- poursuivre la stabilisation du stockage SQL editorial et de ses migrations
- continuer la reduction des wrappers legacy cote pages et navigation
- garder la couverture de tests sur l admin, le header et les flux de sauvegarde
- affiner encore l ergonomie d edition quand cela apporte un vrai gain editorial
- etudier plus tard un vrai module multimedia unifie si le besoin images / videos devient recurrent

## Ce Qu Il Ne Faut Pas Faire

- reintroduire des pages `legacy_template` comme source active
- remettre le JSON brut au centre de l admin menus
- dupliquer les regles de navigation entre desktop, mobile et admin
- faire porter de la logique metier au partial de header ou a `backend/public/*`
- basculer brutalement tout l editorial vers SQL sans import, verification et rollback clairs

## Definition De Reussite

Le domaine admin editorial / navigation est considere sain si :
- une page se cree, se traduit et se publie via un flux admin unique
- la navigation est modifiable sans JSON manuel
- le header public reste coherent entre desktop et mobile
- les stockages editoriaux restent gouvernes par les repositories, pas par des fichiers legacy isoles
- les changements de ce domaine restent couverts par tests et documentes dans les README de reference
