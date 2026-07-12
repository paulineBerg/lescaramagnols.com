# Regles Codex et contribution

## Source de verite

Les regles de gouvernance du depot sont definies dans `AGENTS.md`.

## Principes obligatoires

- privilegier la securite en production
- respecter les zones modifiables et les zones protegees
- ne pas modifier les artefacts generes comme source canonique
- maintenir la coherence multilingue (`fr`, `en`, `de`) sur le public
- valider via tests/lints adaptes au perimetre modifie

## Workflow recommande

1. Lire `AGENTS.md` et les docs de domaine concernees.
2. Appliquer un diff minimal et cible.
3. Mettre a jour la documentation impactee.
4. Verifier les liens docs et la qualite locale.

## Points d'attention

- aucune fuite de secret (`.env`, overrides locaux)
- aucune reintroduction de routes legacy obfusquees
- aucune URL SEO machine avec fragment `#`
- aucune divergence non signalee entre source SQL et miroirs JSON
