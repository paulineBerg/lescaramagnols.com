# Architecture

## Vue d'ensemble

Le projet suit un modele hybride:
- rendu serveur PHP comme source de verite fonctionnelle
- pipeline frontend Vite pour JS/CSS/images
- publication des assets frontend vers `backend/public/`

## Flux HTTP principal

- entree publique: `backend/public/index.php`
- bootstrap: `backend/core/bootstrap.php`
- front controller: `backend/src/Http/FrontController.php`
- resolution routes legacy: `backend/src/Http/LegacyRouteResolver.php`
- admin: `backend/src/Admin/AdminRouteResolver.php`
- templates serveur: `backend/templates/`

## Stockage et contenu

- source editoriale maitre: SQL
- JSON de `backend/data/` utilise comme miroir/outillage, pas comme etat final
- blog: taxonomie controlee + maillage interne + coherence multilingue

## Frontend

- source assets: `frontend/src/`
- build: Vite
- publication: scripts `frontend/tools/` vers `backend/public/`
- details: `frontend/README.md`

## Invariants critiques

- ne pas deplacer la logique metier dans `backend/public/`
- conserver la centralisation i18n backend/frontend
- maintenir la separation code produit / artefacts generes / donnees sensibles
- garantir les regles SEO (canonical sans `#`, JSON-LD centralise)

## Domaine Identity Et Sessions Persistantes

Le domaine `backend/src/Identity/` porte l'authentification persistante ajoutee le 2026-08-06.

- `Device/` : enregistrement, renommage, revocation des appareils.
- `PersistentSession/` : cookies, rotation, consommation et garde de restauration.
- `Repository/` : acces SQL `trusted_devices` et `persistent_session_tokens`.
- `Audit/` : journalisation securisee et hachage des identifiants techniques.
- `SessionScope` definit `identity`, `private`, `admin`.

Les controllers Admin et Private appellent le garde de restauration puis continuent avec les sessions existantes. Aucune logique metier n'est ajoutee dans `backend/public`.
