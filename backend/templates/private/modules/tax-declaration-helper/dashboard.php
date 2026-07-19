<?php
$years = is_array($viewModel['taxYears'] ?? null) ? $viewModel['taxYears'] : [];
$currentYear = is_numeric($viewModel['taxCurrentYear'] ?? null) ? (int) $viewModel['taxCurrentYear'] : (int) date('Y');
$baseUrl = is_string($viewModel['taxBaseUrl'] ?? null) ? (string) $viewModel['taxBaseUrl'] : private_portal_url('tax_dashboard');
$year = $currentYear;
$taxCurrentSubsection = 'dashboard';
?>
<section>
  <?php include __DIR__ . '/_nav.php'; ?>
  <p class="notice">Aide non officielle : cette synthese ne remplace pas une declaration fiscale ni un conseil professionnel.</p>
  <section class="card">
    <h2>Années fiscales</h2>
    <p class="private-actions">
      <a href="<?php echo htmlspecialchars(rtrim($baseUrl, '/') . '/' . $currentYear, ENT_QUOTES, 'UTF-8'); ?>">Ouvrir <?php echo htmlspecialchars((string) $currentYear, ENT_QUOTES, 'UTF-8'); ?></a>
    </p>
    <?php if ($years === []): ?>
      <p class="muted">Aucune annee fiscale preparee.</p>
    <?php else: ?>
      <ul>
        <?php foreach ($years as $yearRow): ?>
          <?php if (!is_array($yearRow) || !is_numeric($yearRow['year'] ?? null)) { continue; } ?>
          <?php $year = (int) $yearRow['year']; ?>
          <li>
            <a href="<?php echo htmlspecialchars(rtrim($baseUrl, '/') . '/' . $year, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8'); ?></a>
            — <?php echo htmlspecialchars((string) ($yearRow['status'] ?? 'draft'), ENT_QUOTES, 'UTF-8'); ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</section>
