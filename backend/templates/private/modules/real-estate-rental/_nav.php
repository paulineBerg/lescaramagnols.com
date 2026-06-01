<?php
$urls = is_array($viewModel['rentalUrls'] ?? null) ? $viewModel['rentalUrls'] : [];
$currentSection = is_string($viewModel['rentalCurrentSection'] ?? null) ? (string) $viewModel['rentalCurrentSection'] : 'dashboard';
$currentSubsection = is_string($viewModel['rentalCurrentSubsection'] ?? null) ? (string) $viewModel['rentalCurrentSubsection'] : '';

$url = static function (string $key, string $fallback) use ($urls): string {
    return is_string($urls[$key] ?? null) ? (string) $urls[$key] : private_portal_url($fallback);
};

$mainItems = [
    'dashboard' => ['Tableau de bord', $url('dashboard', 'rental_dashboard')],
    'personal' => ['Biens et locations', $url('lessors', 'rental_lessors')],
    'agency' => ['Agence', $url('agencies', 'rental_agencies')],
    'reports' => ['Rapports', $url('summary', 'rental_summary')],
];

$subItems = match ($currentSection) {
    'agency' => [
        'agencies' => ['Agences', $url('agencies', 'rental_agencies')],
        'agencyImports' => ['Importer document', $url('agencyImports', 'rental_agency_imports')],
        'agencyReview' => ['Valider les documents', $url('agencyReview', 'rental_agency_review')],
    ],
    'reports' => [
        'summary' => ['Synthèse', $url('summary', 'rental_summary')],
        'exportCsv' => ['Export CSV', $url('exportCsv', 'rental_export_csv')],
        'exportPdf' => ['Export PDF', $url('exportPdf', 'rental_export_pdf')],
    ],
    'personal' => [
        'lessors' => ['Bailleurs', $url('lessors', 'rental_lessors')],
        'properties' => ['Propriétés', $url('properties', 'rental_properties')],
        'units' => ['Biens locatifs', $url('units', 'rental_units')],
        'tenants' => ['Locataires', $url('tenants', 'rental_tenants')],
        'leases' => ['Baux', $url('leases', 'rental_leases')],
        'rents' => ['Loyers', $url('rents', 'rental_rents')],
        'payments' => ['Paiements', $url('payments', 'rental_payments')],
        'expenses' => ['Charges', $url('expenses', 'rental_expenses')],
        'regularizations' => ['Régularisations', $url('regularizations', 'rental_regularizations')],
        'documents' => ['Documents locatifs', $url('documents', 'rental_documents')],
    ],
    default => [],
};
?>
<nav class="private-module-nav" aria-label="Navigation locations immobilieres">
  <div class="private-module-nav-row">
    <?php foreach ($mainItems as $key => [$label, $href]) : ?>
      <a class="<?php echo $currentSection === $key ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
      </a>
    <?php endforeach; ?>
  </div>
  <?php if ($subItems !== []) : ?>
    <div class="private-module-nav-row">
        <?php foreach ($subItems as $key => [$label, $href]) : ?>
        <a class="<?php echo $currentSubsection === $key ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
        </a>
        <?php endforeach; ?>
    </div>
  <?php endif; ?>
</nav>
