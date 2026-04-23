# Index Documentation Projet

Date de mise a jour : 2026-04-23

Ce fichier sert de carte de reference pour les `README*.md` encore utiles au projet.
Objectif : reduire la dette documentaire, savoir quoi lire en premier, et eviter de conserver des plans dates comme sources de verite.

## Legende de statut

- `canonique` : source de verite active pour un domaine.
- `actif` : document maintenu pour un sous-domaine, sans etre le point d entree principal.
- `historique` : document date, conserve pour comprendre un chantier ponctuel.
- `archive` : trace ancienne, a ne pas utiliser pour l etat courant.

## Documentation a lire en premier

| Fichier | Domaine | Statut | Usage recommande |
| --- | --- | --- | --- |
| `README.md` | Vue globale, installation, architecture, verification et deploiement | canonique | Point d entree technique du depot |
| `README_DOCUMENTATION_INDEX.md` | Cartographie documentaire | canonique | Orientation vers la bonne doc |
| `README_SECURITE_ADMIN_V1.md` | Hardening admin (2FA, IP allowlist, re-auth, HTTPS) | canonique | Reference securite operationnelle |
| `README_BLOG.md` | Module blog/discussions JSON + SQL | canonique | Reference fonctionnelle blog |
| `README_MODERNISATION_V1.md` | Principes de modernisation progressive | actif | Cadre d architecture et de decisions |
| `README_ADMIN_EDITORIAL_NAV_V1.md` | Admin editorial, menus, tuiles, navigation | actif | Reference du domaine pages/navigation |
| `README_PRIVATE_FAMILLE_V1.md` | Vision du portail prive famille | actif | Cadrage produit et securite du futur module |
| `README_PRIVATE_FAMILLE_BACKLOG_V1.md` | Backlog technique PVT-01 | actif | Execution du futur portail prive |
| `backend/README_INSTALLATION_HORS_WEBROOT.md` | Installation et reinstallation securisees | canonique | Reference d installation |
| `backend/README_BOOTSTRAP_I18N.md` | Bootstrap backend + i18n serveur | canonique | Reference backend i18n/boot |
| `backend/README_PUBLIC_ENTRYPOINTS.md` | Gouvernance des routes publiques | canonique | Reference routes/admin/api/rss |
| `backend/README_LOGGING.md` | Logging applicatif et observabilite | canonique | Reference observabilite applicative |
| `frontend/README_BUILD_PIPELINE.md` | Build Vite, publication, hygiene repo, artefacts | canonique | Reference build/publish frontend |

## Documents dates conserves

| Fichier | Domaine | Statut | Quand le consulter |
| --- | --- | --- | --- |
| `docs/README_REFONTE_LOT_C.md` | Audit hygiene 2026-04-16, suppressions structurelles | historique | Seulement si le travail porte sur un vieux lot de refonte |
| `docs/README_CONSOLIDATION_LOT_D.md` | Audit hygiene 2026-04-16, decoupage de consolidation | historique | Seulement pour relire ce chantier ponctuel |
| `docs/archive/README_ARCHIVES_INDEX.md` | Porte d entree des archives README | archive | Pour retrouver les audits et plans archives |
| `docs/archive/README_AUDIT_COMPLET_V1.md` | Audit technique global date | archive | Contexte historique uniquement |
| `docs/archive/README_AUDIT_PLAN_ACTION_V1.md` | Plan d action issu de l audit | archive | Trace de pilotage uniquement |
| `docs/archive/README_BLOG_PLAN_V1.md` | Ancien plan blog -> CMS | archive | A ne pas utiliser pour l etat courant |

## Fichiers retires le 2026-04-23

| Fichier retire | Motif | Information regroupee dans |
| --- | --- | --- |
| `README_V1_PREPARATION_DEPLOIEMENT.md` | Checklist de release datee et devenue redondante | `README.md`, `README_SECURITE_ADMIN_V1.md`, `backend/README_LOGGING.md`, `frontend/README_BUILD_PIPELINE.md` |
| `README_TRANSITION_V1_S1_S8.md` | Journal de pilotage S1->S8 clos | `README.md`, `README_MODERNISATION_V1.md` |
| `README_RENDER_ARTEFACTS_V1.md` | Doublon de la politique artefacts build | `frontend/README_BUILD_PIPELINE.md` |
| `docs/README_BLOG_ARTICLE.md` | Note opportuniste sans valeur de reference et hors ligne editoriale documentaire du depot | aucun remplacement necessaire |

## Regles de maintenance documentaire

1. Toute modification d architecture, d installation, de build ou de verification met a jour `README.md` et le README de domaine concerne.
2. Toute evolution securite admin met a jour `README_SECURITE_ADMIN_V1.md`.
3. Toute evolution de routes publiques met a jour `backend/README_PUBLIC_ENTRYPOINTS.md`.
4. Toute evolution du pipeline assets, de la politique artefacts ou de l hygiene repo met a jour `frontend/README_BUILD_PIPELINE.md`.
5. Les documents dates ne redeviennent pas des sources de verite : si une information reste utile, la remonter dans `README.md` ou dans le README de domaine adequat.
6. Toute evolution du portail prive famille met a jour `README_PRIVATE_FAMILLE_V1.md` et, si besoin, `README_PRIVATE_FAMILLE_BACKLOG_V1.md`.
7. Toute mise a jour documentaire doit passer `cd frontend && npm run hygiene:docs`.
