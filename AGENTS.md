# Regles projet - Les Caramagnols

Ce fichier complete les regles communes de `~/www/AGENTS.md` pour ce depot.

## Source de verite

- La production `https://www.lescaramagnols.com/` est la version maitre du projet.
- En cas d'ecart entre le depot local, la documentation et la production, considerer la production comme reference fonctionnelle et editoriale.
- Avant toute evolution qui touche les routes, menus, contenus publics, assets visibles, securite HTTP ou espace admin, comparer avec la production et signaler les ecarts connus.
- Ne pas remplacer un comportement de production observe par une hypothese locale sans verification.

## Espace admin

- La route canonique admin de production est `/espace-admin-7k9m2p`.
- Ne pas tenter de contourner l'authentification admin, le 2FA, les tokens CSRF ou les protections de session.
- Toute reconstruction locale de l'admin doit conserver au minimum les controles de securite observes en production : session securisee, CSRF, 2FA et en-tetes de securite.

## Synchronisation

- Lors d'une remise en coherence, inventorier d'abord les routes et contenus visibles en production.
- Preferer des changements progressifs et verifies par comparaison locale/prod.
- Ne jamais inventer de secrets de production dans le depot ; utiliser des exemples ou variables d'environnement locales.
