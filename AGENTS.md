# Regles projet - Les Caramagnols

Ce fichier complete les regles communes de `~/www/AGENTS.md` pour ce depot.

## Source de verite

- La production `https://www.lescaramagnols.com/` est la version maitre du projet.
- En cas d'ecart entre le depot local, la documentation et la production, considerer la production comme reference fonctionnelle et editoriale.
- Avant toute evolution qui touche les routes, menus, contenus publics, assets visibles, securite HTTP ou espace admin, comparer avec la production et signaler les ecarts connus.
- Ne pas remplacer un comportement de production observe par une hypothese locale sans verification.

## Synchro

- workspace sous \\wsl.localhost\Ubuntu\home\surfacepro8\Workspace
- repos sous \\wsl.localhost\Ubuntu\home\surfacepro8\www\repos

## Espace admin

- La route canonique admin de production est `/espace-admin-7k9m2p`.
- Ne pas tenter de contourner l'authentification admin, le 2FA, les tokens CSRF ou les protections de session.
- Toute reconstruction locale de l'admin doit conserver au minimum les controles de securite observes en production : session securisee, CSRF, 2FA et en-tetes de securite.

## Espace prive

- La route canonique de production pour l'espace prive est `/espace-private-4h6F1c`.
- Ne pas exposer l'espace prive via `/private` en production ; ce chemin doit rester non fonctionnel publiquement.
- Toute modification de `PRIVATE_PORTAL_BASE_PATH` doit etre testee en local puis sur production avec au minimum :
  - `/espace-private-4h6F1c/login` retourne le formulaire prive ;
  - `/private/login` ne retourne pas le formulaire prive ;
  - `/espace-admin-7k9m2p` reste fonctionnel.

## Architecture privee

- `backend/src/PrivatePortal` est reserve au socle de l'espace prive : HTTP, routes, securite, sessions, utilisateurs, permissions et operations transverses.
- Les modules applicatifs prives doivent vivre sous `backend/src/PrivateApps`.
- Ne pas ajouter de nouvelle logique metier applicative dans `PrivatePortal` sans justification explicite ; preferer un module dedie dans `PrivateApps`.

## Deploiement

- La preprod est abandonnee depuis le 2026-07-17 : `prod` est la seule cible de deploiement maintenue.
- `backend/private/` est synchronise vers la production par `deploy-release.sh` en mode additif uniquement (rsync sans `--delete`) : un deploy ne doit jamais supprimer ni ecraser les fichiers runtime prives distants.

## Synchronisation

- Lors d'une remise en coherence, inventorier d'abord les routes et contenus visibles en production.
- Preferer des changements progressifs et verifies par comparaison locale/prod.
- Ne jamais inventer de secrets de production dans le depot ; utiliser des exemples ou variables d'environnement locales.
