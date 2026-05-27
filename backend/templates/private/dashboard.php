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
$privateDocumentsEnabled = is_bool($privateDocumentsEnabled ?? null) ? (bool) $privateDocumentsEnabled : false;
$privateDocuments = is_array($privateDocuments ?? null) ? $privateDocuments : [];
$privateUserIdentifier = is_string($privateUserIdentifier ?? null) ? (string) $privateUserIdentifier : '';
$privatePasswordForgotUrl = is_string($privatePasswordForgotUrl ?? null) ? (string) $privatePasswordForgotUrl : private_portal_url('password_forgot');
$privateDashboardNotice = is_string($privateDashboardNotice ?? null) ? (string) $privateDashboardNotice : '';
$privateDashboardErrorMessage = is_string($privateDashboardErrorMessage ?? null) ? (string) $privateDashboardErrorMessage : '';
$privateDocumentsUploadUrl = is_string($privateDocumentsUploadUrl ?? null) ? (string) $privateDocumentsUploadUrl : private_portal_url('files_upload');
$privateDocumentUploadCsrfToken = is_string($privateDocumentUploadCsrfToken ?? null)
    ? (string) $privateDocumentUploadCsrfToken
    : '';
$privateFilesBaseUrl = trim((string) ($privateFilesBaseUrl ?? ''));
if ($privateFilesBaseUrl === '') {
    $privateFilesBaseUrl = private_portal_url('files');
}

$formatBytes = static function (int $size): string {
    if ($size <= 0) {
        return '0 o';
    }

    $units = ['o', 'Ko', 'Mo', 'Go', 'To'];
    $value = (float) $size;
    $unitIndex = 0;

    while ($value >= 1024 && $unitIndex < count($units) - 1) {
        $value /= 1024;
        ++$unitIndex;
    }

    if ($unitIndex === 0) {
        return (string) (int) $value . ' ' . $units[$unitIndex];
    }

    return number_format($value, 1, '.', ' ') . ' ' . $units[$unitIndex];
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
            <li><?php echo htmlspecialchars($module, ENT_QUOTES, 'UTF-8'); ?></li>
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

    <?php if (in_array('Locations immobilières', $privateModules, true)): ?>
    <section class="card">
      <span class="tag"><?php echo htmlspecialchars($translate('TXT_PRIVATE_DASHBOARD_RENTAL_TAG', 'Gestion'), ENT_QUOTES, 'UTF-8'); ?></span>
      <h2>Locations immobilières</h2>
      <p class="private-actions">
        <a href="<?php echo htmlspecialchars(private_portal_url('rental_properties'), ENT_QUOTES, 'UTF-8'); ?>">Biens</a>
        <a href="<?php echo htmlspecialchars(private_portal_url('rental_units'), ENT_QUOTES, 'UTF-8'); ?>">Lots</a>
        <a href="<?php echo htmlspecialchars(private_portal_url('rental_property_members'), ENT_QUOTES, 'UTF-8'); ?>">Membres</a>
        <a href="<?php echo htmlspecialchars(private_portal_url('rental_tenants'), ENT_QUOTES, 'UTF-8'); ?>">Locataires</a>
        <a href="<?php echo htmlspecialchars(private_portal_url('rental_leases'), ENT_QUOTES, 'UTF-8'); ?>">Baux</a>
        <a href="<?php echo htmlspecialchars(private_portal_url('rental_payments'), ENT_QUOTES, 'UTF-8'); ?>">Loyers</a>
        <a href="<?php echo htmlspecialchars(private_portal_url('rental_expenses'), ENT_QUOTES, 'UTF-8'); ?>">Charges</a>
        <a href="<?php echo htmlspecialchars(private_portal_url('rental_documents'), ENT_QUOTES, 'UTF-8'); ?>">Documents locatifs</a>
        <a href="<?php echo htmlspecialchars(private_portal_url('rental_summary'), ENT_QUOTES, 'UTF-8'); ?>">Synthèse</a>
      </p>
    </section>
    <?php endif; ?>

    <?php if (in_array('Aide impôts', $privateModules, true)): ?>
    <section class="card">
      <span class="tag"><?php echo htmlspecialchars($translate('TXT_PRIVATE_DASHBOARD_TAX_TAG', 'Préparation'), ENT_QUOTES, 'UTF-8'); ?></span>
      <h2>Aide impôts</h2>
      <p class="muted">Aide non officielle destinée à préparer les données, sans remplacer la déclaration fiscale.</p>
      <p class="private-actions">
        <a href="<?php echo htmlspecialchars(private_portal_url('tax_dashboard'), ENT_QUOTES, 'UTF-8'); ?>">Préparer une synthèse annuelle</a>
      </p>
    </section>
    <?php endif; ?>

    <section class="card private-card-wide" id="private-documents">
      <span class="tag"><?php echo htmlspecialchars($translate('TXT_PRIVATE_DASHBOARD_FILES_TAG', 'Fichiers'), ENT_QUOTES, 'UTF-8'); ?></span>
      <h2><?php echo htmlspecialchars($translate('TXT_PRIVATE_DASHBOARD_DOCUMENTS_TITLE', 'Documents'), ENT_QUOTES, 'UTF-8'); ?></h2>

      <?php if ($privateDocumentsEnabled): ?>
        <form method="post" action="<?php echo htmlspecialchars($privateDocumentsUploadUrl, ENT_QUOTES, 'UTF-8'); ?>" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($privateDocumentUploadCsrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
          <label for="private-document-file"><?php echo htmlspecialchars($translate('TXT_PRIVATE_DOCUMENT_FILE_LABEL', 'Ajouter un document'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="private-document-file" type="file" name="<?php echo htmlspecialchars('document_file', ENT_QUOTES, 'UTF-8'); ?>" required />
          <button type="submit"><?php echo htmlspecialchars($translate('TXT_PRIVATE_DOCUMENT_UPLOAD_SUBMIT', 'Envoyer'), ENT_QUOTES, 'UTF-8'); ?></button>
        </form>
      <?php else: ?>
        <p class="muted">
          <?php echo htmlspecialchars($translate('TXT_PRIVATE_DOCUMENT_MODULE_DISABLED', 'Le module documents n’est pas activé pour votre compte.'), ENT_QUOTES, 'UTF-8'); ?>
        </p>
      <?php endif; ?>

      <?php if (!$privateDocumentsEnabled || $privateDocuments === []): ?>
        <p class="muted">
          <?php echo htmlspecialchars($translate('TXT_PRIVATE_DASHBOARD_NO_DOCUMENTS', 'Aucun document pour le moment.'), ENT_QUOTES, 'UTF-8'); ?>
        </p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th><?php echo htmlspecialchars($translate('TXT_PRIVATE_DOCUMENT_TABLE_NAME', 'Nom'), ENT_QUOTES, 'UTF-8'); ?></th>
              <th><?php echo htmlspecialchars($translate('TXT_PRIVATE_DOCUMENT_TABLE_SIZE', 'Poids'), ENT_QUOTES, 'UTF-8'); ?></th>
              <th><?php echo htmlspecialchars($translate('TXT_PRIVATE_DOCUMENT_TABLE_DATE', 'Ajouté le'), ENT_QUOTES, 'UTF-8'); ?></th>
              <th><?php echo htmlspecialchars($translate('TXT_PRIVATE_DOCUMENT_TABLE_ACTIONS', 'Actions'), ENT_QUOTES, 'UTF-8'); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($privateDocuments as $document): ?>
              <?php if (!is_array($document)) : ?>
                <?php continue; ?>
              <?php endif; ?>
              <?php
              $documentId = is_string($document['documentId'] ?? null) ? trim((string) $document['documentId']) : '';
              $originalName = is_string($document['originalName'] ?? null) ? trim((string) $document['originalName']) : '';
              $sizeBytes = is_scalar($document['sizeBytes'] ?? null) ? (int) $document['sizeBytes'] : 0;
              $uploadedAtRaw = is_string($document['uploadedAt'] ?? null) ? trim((string) $document['uploadedAt']) : '';

              if ($documentId === '') {
                  continue;
              }

              $uploadedAt = $uploadedAtRaw !== '' && strtotime($uploadedAtRaw) !== false
                  ? date('d/m/Y H:i', strtotime($uploadedAtRaw))
                  : $translate('TXT_PRIVATE_UNKNOWN', '—');

              $downloadUrl = rtrim($privateFilesBaseUrl, '/') . '/' . rawurlencode($documentId);
              $deleteUrl = rtrim($privateFilesBaseUrl, '/') . '/' . rawurlencode($documentId) . '/delete';
              ?>
              <tr>
                <td>
                  <a href="<?php echo htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($originalName !== '' ? $originalName : $documentId, ENT_QUOTES, 'UTF-8'); ?>
                  </a>
                </td>
                <td><?php echo htmlspecialchars($formatBytes(max(0, $sizeBytes)), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($uploadedAt, ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                  <form method="post" action="<?php echo htmlspecialchars($deleteUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($privateDocumentUploadCsrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
                    <button type="submit"><?php echo htmlspecialchars($translate('TXT_PRIVATE_DOCUMENT_DELETE', 'Supprimer'), ENT_QUOTES, 'UTF-8'); ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  </div>

  <p class="muted">
    <a href="<?php echo htmlspecialchars($privatePasswordForgotUrl, ENT_QUOTES, 'UTF-8'); ?>">
      <?php echo htmlspecialchars($translate('TXT_PRIVATE_PASSWORD_FORGOT_LINK', 'Réinitialiser le mot de passe'), ENT_QUOTES, 'UTF-8'); ?>
    </a>
  </p>
</section>
