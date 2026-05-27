<?php
$summary = is_array($viewModel['taxSummary'] ?? null) ? $viewModel['taxSummary'] : [];
$documents = is_array($summary['missingDocuments'] ?? null) ? $summary['missingDocuments'] : [];
$year = is_numeric($summary['year'] ?? null) ? (int) $summary['year'] : (int) date('Y');
$urls = is_array($viewModel['taxUrls'] ?? null) ? $viewModel['taxUrls'] : [];
?>
<section>
  <p class="muted"><a href="<?php echo htmlspecialchars((string) ($urls['year'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Retour synthese <?php echo htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8'); ?></a></p>
  <section class="card">
    <h2>Documents et justificatifs</h2>
    <?php if ($documents === []): ?><p class="notice notice-success">Aucun justificatif manquant detecte.</p><?php else: ?>
      <ul>
        <?php foreach ($documents as $document): ?>
          <?php if (!is_array($document)) { continue; } ?>
          <li><?php echo htmlspecialchars((string) ($document['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> — <?php echo htmlspecialchars((string) ($document['sourceReference'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</section>
