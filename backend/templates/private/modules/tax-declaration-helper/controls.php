<?php
$summary = is_array($viewModel['taxSummary'] ?? null) ? $viewModel['taxSummary'] : [];
$controls = is_array($summary['controls'] ?? null) ? $summary['controls'] : [];
$year = is_numeric($summary['year'] ?? null) ? (int) $summary['year'] : (int) date('Y');
$urls = is_array($viewModel['taxUrls'] ?? null) ? $viewModel['taxUrls'] : [];
?>
<section>
  <p class="muted"><a href="<?php echo htmlspecialchars((string) ($urls['year'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Retour synthese <?php echo htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8'); ?></a></p>
  <section class="card">
    <h2>Controles de coherence</h2>
    <?php if ($controls === []): ?><p class="notice notice-success">Aucun controle bloquant.</p><?php else: ?>
      <ul><?php foreach ($controls as $control): ?><li><?php echo htmlspecialchars((string) $control, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
  </section>
</section>
