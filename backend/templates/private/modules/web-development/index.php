<?php

declare(strict_types=1);

$projects = is_array($viewModel['webDevelopmentProjects'] ?? null)
    ? $viewModel['webDevelopmentProjects']
    : [];
$baseUrl = is_string($viewModel['webDevelopmentBaseUrl'] ?? null)
    ? rtrim((string) $viewModel['webDevelopmentBaseUrl'], '/')
    : '';
$csrfToken = is_string($viewModel['webDevelopmentCsrfToken'] ?? null)
    ? (string) $viewModel['webDevelopmentCsrfToken']
    : '';
$errorCode = is_string($viewModel['webDevelopmentErrorCode'] ?? null)
    ? (string) $viewModel['webDevelopmentErrorCode']
    : '';
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$errorMessages = [
    'web_development_invalid_request' => 'La demande d’ouverture est invalide. Rechargez la page puis réessayez.',
    'web_development_invalid_csrf' => 'Votre session a expiré. Rechargez la page avant de réessayer.',
    'web_development_unauthorized' => 'Votre compte ne peut pas ouvrir cette prévisualisation.',
    'web_development_project_forbidden' => 'Ce projet ne vous est pas accessible.',
    'web_development_ticket_failed' => 'Le lien temporaire n’a pas pu être créé. Réessayez dans quelques instants.',
    'web_development_preview_host_missing' => 'La prévisualisation est temporairement indisponible.',
];
$errorMessage = $errorMessages[$errorCode] ?? '';
?>
<section class="private-dashboard">
  <p class="private-header-meta">Sites de travail confidentiels, non publiés et non indexables.</p>

  <?php if ($errorMessage !== ''): ?>
    <p class="notice notice-error" role="alert"><?php echo $escape($errorMessage); ?></p>
  <?php endif; ?>

  <div class="cards-grid">
    <?php if ($projects === []): ?>
      <section class="card">
        <span class="tag">Aucun accès</span>
        <h2>Aucun projet disponible</h2>
        <p class="muted">Votre compte possède le module, mais aucun projet actif ne lui est actuellement ouvert.</p>
      </section>
    <?php else: ?>
      <?php foreach ($projects as $project): ?>
        <?php
        $projectKey = is_string($project['projectKey'] ?? null) ? (string) $project['projectKey'] : '';
        $displayName = is_string($project['displayName'] ?? null) ? trim((string) $project['displayName']) : '';
        $description = is_string($project['description'] ?? null) ? trim((string) $project['description']) : '';
        if ($projectKey === '') {
            continue;
        }
        ?>
        <section class="card">
          <span class="tag">Prévisualisation privée</span>
          <h2><?php echo $escape($displayName !== '' ? $displayName : $projectKey); ?></h2>
          <?php if ($description !== ''): ?>
            <p class="muted"><?php echo nl2br($escape($description)); ?></p>
          <?php else: ?>
            <p class="muted">Version de travail réservée aux membres autorisés.</p>
          <?php endif; ?>
          <form method="post" action="<?php echo $escape($baseUrl . '/preview/' . rawurlencode($projectKey)); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>" />
            <button type="submit">Ouvrir la prévisualisation</button>
          </form>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <details class="card">
    <summary>Aide - Projets web privés</summary>
    <h2>Consulter un site de travail</h2>
    <p>Cette page permet d’ouvrir un projet web qui n’est pas encore publié. Il faut disposer du module Web development et voir le projet dans la liste.</p>
    <ol>
      <li>Repérez le projet à consulter.</li>
      <li>Sélectionnez « Ouvrir la prévisualisation ».</li>
      <li>Le site s’ouvre au moyen d’un accès temporaire lié à votre session privée.</li>
    </ol>
    <p>Si aucun projet n’apparaît, demandez à l’administrateur de vérifier votre accès. En cas de session expirée, rechargez cette page.</p>
    <h3>8. Informations techniques</h3>
    <p>Chaque ouverture crée un ticket court, utilisable une seule fois. La prévisualisation reste protégée par une session privée, des en-têtes anti-indexation et un fichier robots qui refuse l’exploration.</p>
  </details>
</section>
