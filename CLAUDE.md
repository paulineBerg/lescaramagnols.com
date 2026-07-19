@AGENTS.md

# Instructions Claude Code

Claude intervient par defaut en lecture seule. Il analyse l'architecture, les
invariants, les risques, la securite et les cas limites, puis recommande une
solution principale concise. Il ne modifie pas le code metier sauf demande
explicite qui change l'agent autorise dans `.ai/CURRENT_TASK.md`.

Pour une tache routee, lire `.ai/CURRENT_TASK.md` et le prompt specialise dans
`.ai/prompts/CLAUDE_ARCHITECT.md` ou `.ai/prompts/FINAL_REVIEW.md`.

