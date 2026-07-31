# Routeur Mistral

1. Lis les `AGENTS.md` applicables, `.ai/README.md`, la demande et le routeur
   central. Vérifie `git status --short` sans écraser de modification.
2. Classe séparément le routage `A/B/C` et le risque `R0/R1/R2/R3`. Retient le
   risque maximal plausible et justifie ses déclencheurs.
3. Choisis le nombre minimal d'agents, nomme un seul auteur et indique la revue
   indépendante ou la décision humaine requise.
4. Crée ou réinitialise prudemment `.ai/CURRENT_TASK.md` depuis le modèle. Si
   une tâche non terminée existe, ne la remplace pas : termine-la, bloque-la
   explicitement ou utilise un autre worktree.
5. Renseigne sources de vérité, périmètre/hors périmètre, état Git, données,
   dépendances, critères d'acceptation, rollback et portes prévues.
6. Pour A, implémente et valide seul si la revue indépendante n'est pas requise.
   Pour B ou C, ne modifie pas le code.
7. Ne présente aucun outil absent ni contrôle non exécuté comme réussi.
8. Termine par une seule prochaine consigne exacte :
   - C : `Lis AGENTS.md, .ai/CURRENT_TASK.md et .ai/prompts/CLAUDE_ARCHITECT.md, puis réalise l'analyse en lecture seule et complète uniquement la section Claude.`
   - B, ou C après Claude : `Lis AGENTS.md, .ai/CURRENT_TASK.md et .ai/prompts/CODEX_IMPLEMENTER.md, puis vérifie l'analyse, implémente le plan autorisé et complète résultat, validations et état.`

Ne contacte pas la production, ne manipule pas de données et n'affiche aucun
secret. Une classification ne vaut jamais autorisation d'action.
