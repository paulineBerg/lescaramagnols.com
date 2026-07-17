# Règles des modules PrivateApps

- Toute logique métier privée vit ici, jamais dans `backend/src/PrivatePortal/` (socle réservé à : HTTP/routage, authentification, sessions, permissions, opérations transverses).
- Chaque module est autosuffisant (`Http/`, `Domain/`, `Repository/`, `Service/`) et expose un manifeste implémentant `Caramagnols\PrivatePortal\PrivateAppManifest`.
- Registre statique explicite côté socle uniquement : pas d'auto-découverte, pas de chargement dynamique, pas de système de hooks.
- Toute écriture sensible vérifie authentification, permission de module, validation stricte, CSRF et journalisation via le socle.
- Documents, exports et données runtime restent hors webroot ; aucun secret ni donnée réelle dans le dépôt.
- Code PHP en `strict_types`, types explicites, namespace `Caramagnols\PrivateApps\<Module>`.
- Tests sous `backend/tests/PrivateApps/<Module>/`, exécutés depuis le dépôt hôte.
- Règles spécifiques par module dans `<Module>/AGENTS.md` le cas échéant (exemple : `RealEstateRental/AGENTS.md`).
