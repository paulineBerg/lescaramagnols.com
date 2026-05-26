# Aide Admin Menus: Presentation Desktop

Ce guide explique pourquoi les options `Mode d'ouverture`, `Colonnes` et `Template` peuvent sembler sans effet.

## 1) Quand ces options ont un effet

- Elles s'appliquent au `menu principal` sur un item de type `group`.
- L'item doit avoir au moins un enfant.
- Le changement est visible sur le header desktop (pas sur le menu mobile).

Si le groupe n'a pas d'enfant, le front affiche simplement un lien, sans panneau dropdown/mega.

## 2) Effet de chaque option

- `Mode d'ouverture`
  - `Dropdown compact`: sous-menu classique.
  - `Mega menu`: panneau large avec sections.
- `Colonnes`
  - Defini le nombre de colonnes cibles du mega menu (2 a 4).
  - Si vous avez peu de sections, les colonnes peuvent paraitre peu remplies.
- `Template`
  - `Standard`: rendu neutre.
  - `Editorial`: rendu plus aere, accent typographique.
  - `Marques / catalogue`: rendu plus compact, style "catalogue".

## 3) Verification rapide

1. Ouvrir `Admin > Menus > Menu principal`.
2. Editer un item `group` qui contient des enfants.
3. Mettre `Mode d'ouverture = Mega menu`.
4. Modifier `Colonnes` puis `Template`.
5. Sauvegarder.
6. Recharger une page front desktop (hard refresh).

## 4) Si rien ne change

- Verifier que vous editez bien un `group` avec enfants.
- Verifier que le front desktop est affiche (pas le menu mobile).
- Verifier que la sauvegarde est bien confirmee dans l'admin.
