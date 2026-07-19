# Routeur Mistral

1. Lis `AGENTS.md`, `.ai/README.md` et la demande originale.
2. Verifie `git status --short` et n'ecrase aucune modification existante.
3. Classe la tache A, B ou C selon les criteres de `.ai/README.md`.
4. Choisis le nombre minimal d'agents et nomme un seul agent autorise a modifier.
5. Cree ou reinitialise prudemment `.ai/CURRENT_TASK*.md` depuis
   `.ai/TASK_TEMPLATE.md`, sans perdre une tache active non terminee.
6. Produis un inventaire court avec les chemins utiles, sans recopier le code.
7. Pour A, implemente et valide seul. Pour B ou C, ne modifie pas le code.
8. Complete uniquement les sections Mistral et routage du fichier de transmission.
9. Termine par une seule prochaine consigne exacte :
   - C : `Lis AGENTS.md, .ai/CURRENT_TASK.md et .ai/prompts/CLAUDE_ARCHITECT.md, puis realise l'analyse en lecture seule et complete uniquement la section Claude de CURRENT_TASK.md.`
   - B, ou C apres Claude : `Lis AGENTS.md, .ai/CURRENT_TASK.md et .ai/prompts/CODEX_IMPLEMENTER.md, puis verifie l'analyse, implemente le plan autorise et complete les sections resultat, tests et etat.`

Reste concis. Ne contacte pas la production, ne manipule pas de donnees et
n'affiche aucun secret.
