<?php
$urls = is_array($viewModel['rentalUrls'] ?? null) ? $viewModel['rentalUrls'] : [];
$currentSection = is_string($viewModel['rentalCurrentSection'] ?? null) ? (string) $viewModel['rentalCurrentSection'] : 'dashboard';
$currentSubsection = is_string($viewModel['rentalCurrentSubsection'] ?? null) ? (string) $viewModel['rentalCurrentSubsection'] : '';

$url = static function (string $key, string $fallback) use ($urls): string {
    return is_string($urls[$key] ?? null) ? (string) $urls[$key] : private_portal_url($fallback);
};

$mainItems = [
    'dashboard' => ['Tableau de bord', $url('dashboard', 'rental_dashboard')],
    'personal' => ['Gestion perso', $url('properties', 'rental_properties')],
    'agency' => ['Gestion agence', $url('agencyImports', 'rental_agency_imports')],
    'reports' => ['Rapports', $url('summary', 'rental_summary')],
];

$subItems = match ($currentSection) {
    'agency' => [
        'agencyImports' => ['Imports', $url('agencyImports', 'rental_agency_imports')],
        'agencyReview' => ['Documents a classer', $url('agencyReview', 'rental_agency_review')],
    ],
    'reports' => [
        'summary' => ['Synthese', $url('summary', 'rental_summary')],
        'exportCsv' => ['Export CSV', $url('exportCsv', 'rental_export_csv')],
        'exportPdf' => ['Export PDF', $url('exportPdf', 'rental_export_pdf')],
    ],
    'personal' => [
        'properties' => ['Biens', $url('properties', 'rental_properties')],
        'units' => ['Lots', $url('units', 'rental_units')],
        'members' => ['Membres', $url('members', 'rental_property_members')],
        'tenants' => ['Locataires', $url('tenants', 'rental_tenants')],
        'leases' => ['Baux', $url('leases', 'rental_leases')],
        'payments' => ['Loyers', $url('payments', 'rental_payments')],
        'expenses' => ['Charges', $url('expenses', 'rental_expenses')],
        'documents' => ['Documents', $url('documents', 'rental_documents')],
    ],
    default => [
        'properties' => ['Biens', $url('properties', 'rental_properties')],
        'payments' => ['Loyers', $url('payments', 'rental_payments')],
        'agencyImports' => ['Imports agence', $url('agencyImports', 'rental_agency_imports')],
        'summary' => ['Synthese', $url('summary', 'rental_summary')],
    ],
};
?>
<nav class="private-module-nav" aria-label="Navigation locations immobilieres">
  <div class="private-module-nav-row">
    <?php foreach ($mainItems as $key => [$label, $href]): ?>
      <a class="<?php echo $currentSection === $key ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="private-module-nav-row">
    <?php foreach ($subItems as $key => [$label, $href]): ?>
      <a class="<?php echo $currentSubsection === $key ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
      </a>
    <?php endforeach; ?>
  </div>
</nav>
