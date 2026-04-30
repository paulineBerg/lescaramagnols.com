# Index Documentation Projet

Date de mise a jour : 2026-04-16

Ce fichier sert de carte de reference pour tous les `README*.md` du projet.
Objectif : eviter les contradictions, savoir quel document est canonique, et reduire la dette documentaire.

## Legende de statut

- `canonique` : source de verite active pour un sujet.
- `actif` : document utile et maintenu, mais pas unique source de verite.
- `historique` : utile pour contexte, mais contient des elements dates.
- `archive` : conserve uniquement pour trace.

## Cartographie des README

| Fichier | Domaine | Statut | Usage recommande |
| --- | --- | --- | --- |
| `README.md` | Vue globale (stack, run, build, securite, i18n) | canonique | Point d'entree technique du depot |
| `README_DOCUMENTATION_INDEX.md` | Cartographie documentaire | canonique | Orientation des lecteurs vers la bonne doc |
| `README_V1_PREPARATION_DEPLOIEMENT.md` | Plan de finalisation V1 | canonique | Pilotage release readiness |
| `README_SECURITE_ADMIN_V1.md` | Hardening admin (2FA, IP allowlist, re-auth, HTTPS) | canonique | Reference securite operationnelle |
| `README_BLOG.md` | Module blog/discussions JSON + moderation | canonique | Reference fonctionnelle blog |
| `docs/archive/README_AUDIT_COMPLET_V1.md` | Audit technique global date | archive | Conserver pour contexte, ne pas l'utiliser seul pour l'etat actuel |
| `docs/archive/README_AUDIT_PLAN_ACTION_V1.md` | Plan d'action issu de l'audit | archive | Conserver comme trace de pilotage |
| `README_MODERNISATION_V1.md` | Strategie de modernisation progressive | actif | Cadre de decisions techniques |
| `README_TRANSITION_V1_S1_S8.md` | Plan d'execution S1->S8 (tickets + validations) | actif | Pilotage operationnel hebdomadaire, cloture S1 (W1-07) et go/no-go |
| `README_PRIVATE_FAMILLE_V1.md` | Portail prive famille (architecture, securite, roadmap) | actif | Reference pour concevoir et exploiter l'espace prive modulaire |
| `README_PRIVATE_FAMILLE_BACKLOG_V1.md` | Backlog technique PVT-01 (tickets executables) | actif | Plan de travail implementation du portail prive |
| `README_ADMIN_EDITORIAL_NAV_V1.md` | Admin editorial + navigation + chantier menu | actif | Roadmap detaillee de la couche admin/navigation |
| `docs/README_REFONTE_LOT_C.md` | Isolement de la refonte heritee (Lot C) | actif | Cartographier les suppressions structurelles a committer a part du nettoyage |
| `docs/README_CONSOLIDATION_LOT_D.md` | Consolidation du nouveau code (Lot D) | actif | Decouper le code neuf par domaines et rattacher tests/README associes |
| `docs/sources/editorial/README.md` | Gouvernance des transcriptions et sources brutes editoriales | actif | Ranger et exploiter correctement les materiaux de travail editorial |
| `README_RENDER_ARTEFACTS_V1.md` | Politique de versionning des artefacts build/assets | actif | Utiliser pour gouverner nettoyage/build et l'etat Git |
| `docs/archive/README_ARCHIVES_INDEX.md` | Index central des documents archives | archive | Point d'entree pour consulter les archives README |
| `docs/archive/README_BLOG_PLAN_V1.md` | Ancien plan blog->CMS | archive | Ne pas utiliser pour pilotage courant |
| `backend/README_BOOTSTRAP_I18N.md` | Bootstrap backend + i18n serveur | canonique | Reference backend i18n/boot |
| `backend/README_PUBLIC_ENTRYPOINTS.md` | Gouvernance des routes publiques | canonique | Reference routes/admin/api/rss |
| `backend/README_INSTALLATION_HORS_WEBROOT.md` | Installation hors webroot | canonique | Reference installation securisee |
| `backend/README_LOGGING.md` | Logging applicatif et canaux | canonique | Reference observabilite applicative |
| `frontend/README_BUILD_PIPELINE.md` | Pipeline Vite -> backend/public | canonique | Reference build/publish frontend |
| `docs/private/README_ENV_PRODUCTION_QUESTIONNAIRE_V1.md` | Questionnaire de collecte pour `.env.production` | actif | Formulaire de decision avant generation du fichier de prod (hors Git) |

## Incoherences documentaires detectees

1. L'ancienne reference `backend/README_CONTENT_TEMPLATES.md` etait cassante (fichier absent) ; elle a ete remplacee par `docs/pages-dynamiques.md` dans les docs principales.
2. Plusieurs documents d'audit contiennent des etats de tests obsoletes (normal pour des documents historiques).
3. Le smoke check CI admin est aligne sur la route canonique (`/admin/menus`) ; eviter toute reintroduction d'URL legacy obfusquee.
4. Les anciens audits et plans historiques ont ete deplaces dans `docs/archive/` (`README_AUDIT_COMPLET_V1.md`, `README_AUDIT_PLAN_ACTION_V1.md`, `README_BLOG_PLAN_V1.md`) pour sortir les archives de la racine du depot.

## Regles de maintenance documentaire

1. Toute modification d'architecture doit mettre a jour au minimum `README.md` et le README de domaine concerne.
2. Toute evolution securite admin doit mettre a jour `README_SECURITE_ADMIN_V1.md`.
3. Toute evolution de routes publiques doit mettre a jour `backend/README_PUBLIC_ENTRYPOINTS.md`.
4. Toute evolution du pipeline assets doit mettre a jour `frontend/README_BUILD_PIPELINE.md`.
5. Les audits dates restent en `archive` (ou `historique` selon besoin) : ne pas ecraser leur contexte, ajouter plutot une note de date.
6. Toute mise a jour documentaire doit passer `cd frontend && npm run hygiene:docs`.
7. Toute evolution du portail prive famille doit mettre a jour `README_PRIVATE_FAMILLE_V1.md`.
8. Les sources brutes reutilisables de redaction ne vont pas a la racine `docs/` ; elles doivent etre rangees sous `docs/sources/editorial/` selon les regles du README dedie.

## Checklist "documentation saine"

- [x] Tous les liens Markdown internes pointent vers des fichiers existants.
- [x] `cd frontend && npm run hygiene:docs` est vert.
- [x] Chaque README de reference est cartographie avec un statut (`canonique`, `actif`, `historique`, `archive`).
- [x] Le plan V1 de deploiement est tenu a jour (`README_V1_PREPARATION_DEPLOIEMENT.md`).
- [x] Les commandes de verification documentees correspondent aux commandes reelles (`composer`, `npm`, CI).
- [x] Les exemples de config n'exposent aucun secret reel.

## Preuves 2026-03-21

- liens Markdown et hygiene docs :
  - `docs/private/recette-preprod-v1-2026-03-21/132-hygiene-docs.txt`
- cartographie statuts README + references :
  - `docs/private/recette-preprod-v1-2026-03-21/133-documentation-index-status-check.txt`
- correspondance commandes documentees / commandes reelles :
  - `docs/private/recette-preprod-v1-2026-03-21/129-init-db-admin-help.txt`
  - `docs/private/recette-preprod-v1-2026-03-21/130-init-db-admin-dry-run.txt`
  - `docs/private/recette-preprod-v1-2026-03-21/134-doc-command-smoke.txt`
- plan V1 a jour + controles post go-live :
  - `README_V1_PREPARATION_DEPLOIEMENT.md` (sections `Definition de "V1 prete au deploiement"` et `Preuves recette preprod`)
- exemples de config sans secrets reels (docs d'exploitation) :
  - `backend/tools/systemd/check-log-alerts.env.example`
