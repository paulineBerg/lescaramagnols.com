# Règles des modules PrivateApps

- Toute logique métier privée vit ici, jamais dans `backend/src/PrivatePortal/` (socle réservé à : HTTP/routage, authentification, sessions, permissions, opérations transverses).
- Chaque module est autosuffisant (`Http/`, `Domain/`, `Repository/`, `Service/`) et expose un manifeste implémentant `Caramagnols\PrivatePortal\PrivateAppManifest`.
- Registre statique explicite côté socle uniquement : pas d'auto-découverte, pas de chargement dynamique, pas de système de hooks.
- Toute écriture sensible vérifie authentification, permission de module, validation stricte, CSRF et journalisation via le socle.
- Documents, exports et données runtime restent hors webroot ; aucun secret ni donnée réelle dans le dépôt.
- En production, les modules privés qui écrivent des fichiers doivent utiliser `PRIVATE_STORAGE_ROOT` et le runtime séparé `/home/lescaramgl-ssh/caramagnols-runtime/private-storage/`, jamais un chemin local versionné ni un chemin sous webroot.
- Ne pas coder en dur `backend/private/storage` comme chemin de production : il n'est qu'un fallback/chemin local de transition depuis la migration du 2026-07-18.
- Code PHP en `strict_types`, types explicites, namespace `Caramagnols\PrivateApps\<Module>`.
- Tests sous `backend/tests/PrivateApps/<Module>/`, exécutés depuis le dépôt hôte.
- Règles spécifiques par module dans `<Module>/AGENTS.md` le cas échéant (exemple : `RealEstateRental/AGENTS.md`).
