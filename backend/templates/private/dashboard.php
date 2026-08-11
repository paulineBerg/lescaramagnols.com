<?php
$translate = static function (string $key, string $fallback): string {
    if (!function_exists('t')) {
        return $fallback;
    }

    $translated = t($key);
    if (!is_string($translated) || $translated === '' || $translated === '[[' . $key . ']]') {
        return $fallback;
    }

    return $translated;
};

$privateModules = is_array($privateModules ?? null) ? $privateModules : [];
$privateModuleCodes = is_array($privateModuleCodes ?? null) ? $privateModuleCodes : [];
$privateNormalizedModuleCodes = array_values(array_filter(array_map(
    static fn (mixed $value): string => strtolower(trim((string) $value)),
    $privateModuleCodes
)));
$privateModuleDataCounts = is_array($viewModel['privateModuleDataCounts'] ?? null) ? $viewModel['privateModuleDataCounts'] : [];
$privateUserIdentifier = is_string($privateUserIdentifier ?? null) ? (string) $privateUserIdentifier : '';
$privatePasswordForgotUrl = is_string($privatePasswordForgotUrl ?? null) ? (string) $privatePasswordForgotUrl : private_portal_url('password_forgot');
$privateDashboardNotice = is_string($privateDashboardNotice ?? null) ? (string) $privateDashboardNotice : '';
$privateDashboardErrorMessage = is_string($privateDashboardErrorMessage ?? null) ? (string) $privateDashboardErrorMessage : '';

// Utiliser PrivateAppRegistry pour obtenir les données de tuiles dashboard
$privateModuleTiles = [];
try {
    $tiles = \Caramagnols\PrivatePortal\PrivateAppRegistry::allDashboardTileData();
    foreach ($tiles as $tile) {
        $privateModuleTiles[$tile['module_code']] = [
            'name' => $tile['label'],
            'description' => $tile['description'],
            'stat_code' => $tile['stat_code'],
        ];
    }
} catch (\Throwable $e) {
    // Si le registre n'est pas disponible, utiliser les valeurs par défaut
}

// Module code by name - à migrer progressivement vers le registre
$privateModuleCodeByName = [
    'Tableau de bord privé' => 'dashboard',
    'Documents' => 'documents',
    'Bloc-note' => 'blocnote',
    'Discussions' => 'discussions',
    'Locations immobilières' => 'real_estate_rental',
    'Aide impôts' => 'tax_declaration_helper',
    'Web development' => 'web_development',
];

// Ajouter les modules depuis le registre si disponibles
if (!empty($privateModuleTiles)) {
    foreach ($privateModuleTiles as $moduleCode => $tile) {
        if (!in_array($tile['name'], array_keys($privateModuleCodeByName), true)) {
            $privateModuleCodeByName[$tile['name']] = $moduleCode;
        }
    }
}

$privateModuleStat = static function (string $code, string $singular, string $plural) use ($privateModuleDataCounts): string {
    $count = max(0, (int) ($privateModuleDataCounts[$code] ?? 0));

    return $count . ' ' . ($count > 1 ? $plural : $singular);
};
$privateHasModuleCode = static function (string $code, string $fallbackName) use ($privateNormalizedModuleCodes, $privateModules): bool {
    return in_array(strtolower(trim($code)), $privateNormalizedModuleCodes, true)
        || in_array($fallbackName, $privateModules, true);
};
?>
<section class="private-dashboard">
  <p class="private-header-meta">
    <?php echo htmlspecialchars(
        $translate('TXT_PRIVATE_DASHBOARD_WELCOME', 'Bienvenue dans votre espace privé.'),
        ENT_QUOTES,
        'UTF-8'
    ); ?>
    <?php if ($privateUserIdentifier !== ''): ?>
      <strong><?php echo htmlspecialchars($privateUserIdentifier, ENT_QUOTES, 'UTF-8'); ?></strong>.
    <?php endif; ?>
  </p>

  <?php if ($privateDashboardNotice !== ''): ?>
    <p class="notice notice-success">
      <?php echo htmlspecialchars($privateDashboardNotice, ENT_QUOTES, 'UTF-8'); ?>
    </p>
  <?php endif; ?>

  <?php if ($privateDashboardErrorMessage !== ''): ?>
    <p class="notice notice-error">
      <?php echo htmlspecialchars($privateDashboardErrorMessage, ENT_QUOTES, 'UTF-8'); ?>
    </p>
  <?php endif; ?>

  <div class="cards-grid">
    <section class="card">
      <span class="tag"><?php echo htmlspecialchars($translate('TXT_PRIVATE_DASHBOARD_PROTECTION_TAG', 'Protection'), ENT_QUOTES, 'UTF-8'); ?></span>
      <h2>Confidentialité et exploitation</h2>
      <p class="muted">Les exports privés sont servis avec en-têtes noindex et ne contiennent pas les secrets de connexion.</p>
      <p class="private-actions">
        <a href="<?php echo htmlspecialchars(private_portal_url('privacy_export'), ENT_QUOTES, 'UTF-8'); ?>">Exporter mes données privées</a>
        <a href="<?php echo htmlspecialchars(private_portal_url('ops_backup'), ENT_QUOTES, 'UTF-8'); ?>">Créer une sauvegarde vérifiée</a>
      </p>
    </section>

    <section class="card">
      <span class="tag"><?php echo htmlspecialchars($translate('TXT_PRIVATE_DASHBOARD_ACCESS_TAG', 'Accès'), ENT_QUOTES, 'UTF-8'); ?></span>
      <h2><?php echo htmlspecialchars($translate('TXT_PRIVATE_DASHBOARD_MODULES_TITLE', 'Modules disponibles'), ENT_QUOTES, 'UTF-8'); ?></h2>
      <?php if ($privateModules !== []): ?>
        <ul>
          <?php foreach ($privateModules as $module): ?>
            <?php if (!is_string($module) || trim($module) === ''): ?>
              <?php continue; ?>
            <?php endif; ?>
            <?php $moduleCode = $privateModuleCodeByName[$module] ?? ''; ?>
            <li>
              <?php echo htmlspecialchars($module, ENT_QUOTES, 'UTF-8'); ?>
              <?php if ($moduleCode !== '' && $moduleCode !== 'dashboard'): ?>
                <span class="muted">
                  · <?php echo htmlspecialchars($privateModuleStat($moduleCode, 'élément', 'éléments'), ENT_QUOTES, 'UTF-8'); ?>
                </span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="muted">
          <?php echo htmlspecialchars(
              $translate('TXT_PRIVATE_DASHBOARD_NO_MODULE', 'Aucun module actif pour l’instant.'),
              ENT_QUOTES,
              'UTF-8'
          ); ?>
        </p>
      <?php endif; ?>
    </section>

    <?php if ($privateHasModuleCode('documents', 'Documents')): ?>
    <section class="card">
      <span class="tag"><?php echo htmlspecialchars($translate('TXT_PRIVATE_DASHBOARD_FILES_TAG', 'Fichiers'), ENT_QUOTES, 'UTF-8'); ?></span>
      <h2><?php echo htmlspecialchars($translate('TXT_PRIVATE_DASHBOARD_DOCUMENTS_TITLE', 'Documents'), ENT_QUOTES, 'UTF-8'); ?></h2>
      <p class="muted">
        <?php echo htmlspecialchars($translate('TXT_PRIVATE_DOCUMENTS_MODULE_INTRO', 'Ajoutez, classez et téléchargez vos fichiers privés depuis un module dédié.'), ENT_QUOTES, 'UTF-8'); ?>
      </p>
      <p class="muted">
        <strong>Statistique :</strong>
        <?php echo htmlspecialchars($privateModuleStat('documents', 'document ou catégorie', 'documents ou catégories'), ENT_QUOTES, 'UTF-8'); ?>.
      </p>
      <p class="private-actions">
        <a href="<?php echo htmlspecialchars(private_portal_url('documents'), ENT_QUOTES, 'UTF-8'); ?>">
          <?php echo htmlspecialchars($translate('TXT_PRIVATE_DOCUMENTS_MODULE_OPEN', 'Ouvrir les documents'), ENT_QUOTES, 'UTF-8'); ?>
        </a>
      </p>
    </section>
    <?php endif; ?>

    <?php if ($privateHasModuleCode('blocnote', 'Bloc-note')): ?>
    <section class="card">
      <span class="tag">Notes</span>
      <h2>Bloc-note</h2>
      <p class="muted">
        Créez des notes privées, classez-les par catégorie et retrouvez rapidement les informations utiles.
      </p>
      <p class="muted">
        <strong>Statistique :</strong>
        <?php echo htmlspecialchars($privateModuleStat('blocnote', 'note ou catégorie', 'notes ou catégories'), ENT_QUOTES, 'UTF-8'); ?>.
      </p>
      <p class="private-actions">
        <a href="<?php echo htmlspecialchars(private_portal_url('blocnote'), ENT_QUOTES, 'UTF-8'); ?>">Ouvrir le bloc-note</a>
      </p>
    </section>
    <?php endif; ?>

    <?php if ($privateHasModuleCode('discussions', 'Discussions')): ?>
    <section class="card">
      <span class="tag">Messages</span>
      <h2>Discussions famille</h2>
      <p class="muted">Messages privés, groupes, images et fichiers joints avec conservation limitée à 60 jours.</p>
      <p class="muted">
        <strong>Statistique :</strong>
        <?php echo htmlspecialchars($privateModuleStat('discussions', 'élément actif', 'éléments actifs'), ENT_QUOTES, 'UTF-8'); ?>.
      </p>
      <p class="private-actions">
        <a href="<?php echo htmlspecialchars(private_portal_url('discussion_index'), ENT_QUOTES, 'UTF-8'); ?>">Ouvrir les discussions</a>
      </p>
    </section>
    <?php endif; ?>

    <?php if ($privateHasModuleCode('real_estate_rental', 'Locations immobilières')): ?>
    <section class="card">
      <span class="tag"><?php echo htmlspecialchars($translate('TXT_PRIVATE_DASHBOARD_RENTAL_TAG', 'Gestion'), ENT_QUOTES, 'UTF-8'); ?></span>
      <h2>Locations immobilières</h2>
      <p class="muted">
        <strong>Statistique :</strong>
        <?php echo htmlspecialchars($privateModuleStat('real_estate_rental', 'élément de gestion', 'éléments de gestion'), ENT_QUOTES, 'UTF-8'); ?>.
      </p>
      <p class="private-actions">
        <a href="<?php echo htmlspecialchars(private_portal_url('rental_dashboard'), ENT_QUOTES, 'UTF-8'); ?>">Tableau de bord</a>
        <a href="<?php echo htmlspecialchars(private_portal_url('rental_lessors'), ENT_QUOTES, 'UTF-8'); ?>">Bailleurs</a>
        <a href="<?php echo htmlspecialchars(private_portal_url('rental_properties'), ENT_QUOTES, 'UTF-8'); ?>">Propriétés</a>
        <a href="<?php echo htmlspecialchars(private_portal_url('rental_units'), ENT_QUOTES, 'UTF-8'); ?>">Biens locatifs</a>
        <a href="<?php echo htmlspecialchars(private_portal_url('rental_tenants'), ENT_QUOTES, 'UTF-8'); ?>">Locataires</a>
        <a href="<?php echo htmlspecialchars(private_portal_url('rental_rents'), ENT_QUOTES, 'UTF-8'); ?>">Loyers</a>
        <a href="<?php echo htmlspecialchars(private_portal_url('rental_payments'), ENT_QUOTES, 'UTF-8'); ?>">Paiements</a>
        <a href="<?php echo htmlspecialchars(private_portal_url('rental_regularizations'), ENT_QUOTES, 'UTF-8'); ?>">Régularisations</a>
        <a href="<?php echo htmlspecialchars(private_portal_url('rental_agencies'), ENT_QUOTES, 'UTF-8'); ?>">Agences</a>
        <a href="<?php echo htmlspecialchars(private_portal_url('rental_agency_imports'), ENT_QUOTES, 'UTF-8'); ?>">Imports agence</a>
        <a href="<?php echo htmlspecialchars(private_portal_url('rental_summary'), ENT_QUOTES, 'UTF-8'); ?>">Synthèse</a>
      </p>
    </section>
    <?php endif; ?>

    <?php if ($privateHasModuleCode('tax_declaration_helper', 'Aide impôts')): ?>
    <section class="card">
      <span class="tag"><?php echo htmlspecialchars($translate('TXT_PRIVATE_DASHBOARD_TAX_TAG', 'Préparation'), ENT_QUOTES, 'UTF-8'); ?></span>
      <h2>Aide impôts</h2>
      <p class="muted">Aide non officielle destinée à préparer les données, sans remplacer la déclaration fiscale.</p>
      <p class="muted">
        <strong>Statistique :</strong>
        <?php echo htmlspecialchars($privateModuleStat('tax_declaration_helper', 'élément fiscal', 'éléments fiscaux'), ENT_QUOTES, 'UTF-8'); ?>.
      </p>
      <p class="private-actions">
        <a href="<?php echo htmlspecialchars(private_portal_url('tax_dashboard'), ENT_QUOTES, 'UTF-8'); ?>">Préparer une synthèse annuelle</a>
      </p>
    </section>
    <?php endif; ?>

    <?php if ($privateHasModuleCode('web_development', 'Web development')): ?>
    <section class="card">
      <span class="tag">Prévisualisation</span>
      <h2>Projets web privés</h2>
      <p class="muted">Consultez les sites de travail qui vous ont été confiés, sans les rendre publics ni indexables.</p>
      <p class="private-actions">
        <a href="<?php echo htmlspecialchars(private_portal_url('web_development'), ENT_QUOTES, 'UTF-8'); ?>">Voir mes projets web</a>
      </p>
    </section>
    <?php endif; ?>

  </div>

  <p class="muted">
    <a href="<?php echo htmlspecialchars($privatePasswordForgotUrl, ENT_QUOTES, 'UTF-8'); ?>">
      <?php echo htmlspecialchars($translate('TXT_PRIVATE_PASSWORD_FORGOT_LINK', 'Réinitialiser le mot de passe'), ENT_QUOTES, 'UTF-8'); ?>
    </a>
  </p>
</section>
