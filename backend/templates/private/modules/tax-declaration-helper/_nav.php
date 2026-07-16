<?php
$taxNavUrls = is_array($urls ?? null) ? $urls : [];
$taxNavYear = is_numeric($year ?? null) ? (int) $year : (int) date('Y');
$taxNavCurrent = is_string($taxCurrentSubsection ?? null) ? (string) $taxCurrentSubsection : 'dashboard';
$taxNavDashboardUrl = is_string($taxNavUrls['dashboard'] ?? null)
    ? (string) $taxNavUrls['dashboard']
    : (is_string($baseUrl ?? null) ? (string) $baseUrl : private_portal_url('tax_dashboard'));
$taxNavYearUrl = is_string($taxNavUrls['year'] ?? null)
    ? (string) $taxNavUrls['year']
    : rtrim($taxNavDashboardUrl, '/') . '/' . $taxNavYear;
$taxNavItems = [
    'dashboard' => ['Tableau de bord', $taxNavDashboardUrl],
    'year' => ['Synthèse ' . $taxNavYear, $taxNavYearUrl],
    'manual' => ['Revenus manuels', (string) ($taxNavUrls['manual'] ?? ($taxNavYearUrl . '/revenus-manuels'))],
    'controls' => ['Contrôle', (string) ($taxNavUrls['controls'] ?? ($taxNavYearUrl . '/controle'))],
    'documents' => ['Documents', (string) ($taxNavUrls['documents'] ?? ($taxNavYearUrl . '/documents'))],
];
?>
<nav class="private-module-nav" aria-label="Navigation aide impôts">
  <div class="private-module-nav-row">
    <?php foreach ($taxNavItems as $taxNavKey => [$taxNavLabel, $taxNavHref]): ?>
      <a class="<?php echo $taxNavCurrent === $taxNavKey ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($taxNavHref, ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo htmlspecialchars($taxNavLabel, ENT_QUOTES, 'UTF-8'); ?>
      </a>
    <?php endforeach; ?>
  </div>
</nav>
