<?php
/**
 * Centre de documents : bibliothèque centrale partagée par toutes les webapps.
 */

$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$hubDocuments = is_array($hubDocuments ?? null) ? $hubDocuments : [];
$hubCategories = is_array($hubCategories ?? null) ? $hubCategories : [];
$hubStats = is_array($hubStats ?? null) ? $hubStats : [];
$hubFilters = is_array($hubFilters ?? null) ? $hubFilters : [];
$hubCsrfToken = is_string($hubCsrfToken ?? null) ? (string) $hubCsrfToken : '';
$hubUrl = is_string($hubUrl ?? null) ? (string) $hubUrl : private_portal_url('documents_hub');
$hubImportUrl = is_string($hubImportUrl ?? null) ? (string) $hubImportUrl : private_portal_url('documents_hub_import');
$hubActionUrl = is_string($hubActionUrl ?? null) ? (string) $hubActionUrl : private_portal_url('documents_hub_action');
$hubFileBaseUrl = is_string($hubFileBaseUrl ?? null) ? (string) $hubFileBaseUrl : private_portal_url('documents_hub_file');
$hubReturnRoute = is_string($hubReturnRoute ?? null) ? (string) $hubReturnRoute : 'documents_hub';
$hubImportProfileCode = is_string($hubImportProfileCode ?? null) ? (string) $hubImportProfileCode : '';
$hubImportContext = is_array($hubImportContext ?? null) ? $hubImportContext : [];
$notice = is_string($notice ?? null) ? (string) $notice : '';
$errorMessage = is_string($errorMessage ?? null) ? (string) $errorMessage : '';

$currentView = is_string($hubFilters['vue'] ?? null) ? (string) $hubFilters['vue'] : '';

$categoryLabels = [];
foreach ($hubCategories as $categoryRow) {
    $code = is_string($categoryRow['code'] ?? null) ? (string) $categoryRow['code'] : '';
    if ($code !== '') {
        $categoryLabels[$code] = is_string($categoryRow['label'] ?? null) ? (string) $categoryRow['label'] : $code;
    }
}

$formatBytes = static function (int $size): string {
    if ($size <= 0) {
        return '0 o';
    }
    $units = ['o', 'Ko', 'Mo', 'Go'];
    $value = (float) $size;
    $unitIndex = 0;
    while ($value >= 1024 && $unitIndex < count($units) - 1) {
        $value /= 1024;
        ++$unitIndex;
    }

    return ($unitIndex === 0 ? (string) (int) $value : number_format($value, 1, ',', ' ')) . ' ' . $units[$unitIndex];
};

$viewUrl = static function (string $view) use ($hubUrl): string {
    return $view === '' ? $hubUrl : $hubUrl . '?vue=' . rawurlencode($view);
};
?>
<div class="private-module-dashboard">
  <header class="private-list-header">
    <h2>Bibliothèque de documents</h2>
    <p class="muted">
      Tous les documents importés depuis les webapps, dédupliqués et classés dans une taxonomie commune.
      <?php if (($hubStats['documents'] ?? 0) > 0): ?>
        <?php echo (int) $hubStats['documents']; ?> document(s),
        <?php echo (int) $hubStats['objects']; ?> fichier(s) uniques,
        <?php echo $h($formatBytes((int) ($hubStats['dedup_saved_bytes'] ?? 0))); ?> économisés par déduplication.
      <?php endif; ?>
    </p>
  </header>

  <?php if ($notice !== ''): ?>
    <div class="notice notice-success" role="status"><?php echo $h($notice); ?></div>
  <?php endif; ?>
  <?php if ($errorMessage !== ''): ?>
    <div class="notice notice-error" role="alert"><?php echo $h($errorMessage); ?></div>
  <?php endif; ?>

  <nav class="private-module-nav" aria-label="Vues de la bibliothèque">
    <div class="private-module-nav-row">
      <a class="<?php echo $currentView === '' ? 'tag' : 'muted'; ?>" href="<?php echo $h($viewUrl('')); ?>">Tous</a>
      <a class="<?php echo $currentView === 'a-classer' ? 'tag' : 'muted'; ?>" href="<?php echo $h($viewUrl('a-classer')); ?>">
        À classer<?php echo ($hubStats['inbox'] ?? 0) > 0 ? ' (' . (int) $hubStats['inbox'] . ')' : ''; ?>
      </a>
      <a class="<?php echo $currentView === 'archives' ? 'tag' : 'muted'; ?>" href="<?php echo $h($viewUrl('archives')); ?>">Archives</a>
      <a class="<?php echo $currentView === 'corbeille' ? 'tag' : 'muted'; ?>" href="<?php echo $h($viewUrl('corbeille')); ?>">Corbeille</a>
    </div>
  </nav>

  <?php
    $documentImportUrl = $hubImportUrl;
    $documentImportCsrfToken = $hubCsrfToken;
    $documentImportProfileCode = $hubImportProfileCode;
    $documentImportReturnRoute = $hubReturnRoute;
    $documentImportContext = $hubImportContext;
    $documentImportCategories = $hubCategories;
    $documentImportDefaultCategory = '';
    $documentImportAllowedCategories = [];
    $documentImportAllowMultiple = true;
    $documentImportTitle = 'Importer dans mon espace personnel';
    require ROOT_PATH . '/templates/private/components/document-import.php';
  ?>

  <section class="private-dashboard-panel">
    <form method="get" action="<?php echo $h($hubUrl); ?>" class="private-list-tools">
      <?php if ($currentView !== ''): ?>
        <input type="hidden" name="vue" value="<?php echo $h($currentView); ?>" />
      <?php endif; ?>
      <div class="private-list-filter-grid">
        <label>
          Recherche
          <input type="search" name="q" value="<?php echo $h($hubFilters['q'] ?? ''); ?>" placeholder="Titre, nom de fichier…" />
        </label>
        <label>
          Catégorie
          <select name="categorie">
            <option value="">Toutes</option>
            <?php foreach ($hubCategories as $categoryRow): ?>
              <?php
                $code = is_string($categoryRow['code'] ?? null) ? (string) $categoryRow['code'] : '';
                $parent = is_string($categoryRow['parent_code'] ?? null) ? (string) $categoryRow['parent_code'] : '';
              ?>
              <option value="<?php echo $h($code); ?>" <?php echo ($hubFilters['categorie'] ?? '') === $code ? 'selected' : ''; ?>>
                <?php echo $h(($parent !== '' ? '— ' : '') . ($categoryRow['label'] ?? $code)); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Année
          <input type="number" name="annee" min="1990" max="2100" value="<?php echo $h($hubFilters['annee'] ?? ''); ?>" />
        </label>
        <label>
          Type de fichier
          <input type="text" name="type" maxlength="8" value="<?php echo $h($hubFilters['type'] ?? ''); ?>" placeholder="pdf, jpg…" />
        </label>
      </div>
      <div class="private-list-filter-actions">
        <button type="submit" class="private-button-secondary">Filtrer</button>
        <a class="muted" href="<?php echo $h($viewUrl($currentView)); ?>">Réinitialiser</a>
      </div>
    </form>

    <div class="private-table-wrap private-documents-table-wrap">
      <table>
        <thead>
          <tr>
            <th scope="col">Document</th>
            <th scope="col">Catégorie</th>
            <th scope="col">Date</th>
            <th scope="col">Taille</th>
            <th scope="col">Rattachements</th>
            <th scope="col">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($hubDocuments === []): ?>
            <tr class="private-empty-row">
              <td colspan="6">Aucun document pour ces critères.</td>
            </tr>
          <?php endif; ?>
          <?php foreach ($hubDocuments as $documentRow): ?>
            <?php
              $uid = is_string($documentRow['document_uid'] ?? null) ? (string) $documentRow['document_uid'] : '';
              $title = trim((string) ($documentRow['title'] ?? ''));
              $originalName = (string) ($documentRow['original_filename'] ?? '');
              $displayName = $title !== '' ? $title : $originalName;
              $categoryCode = (string) ($documentRow['category_code'] ?? 'inbox');
              $status = (string) ($documentRow['status'] ?? 'active');
              $legalHold = (int) ($documentRow['legal_hold'] ?? 0) === 1;
              $linksDescribed = is_array($documentRow['links_described'] ?? null) ? $documentRow['links_described'] : [];
              $downloadUrl = $hubFileBaseUrl . '/' . rawurlencode($uid);
            ?>
            <tr>
              <td>
                <strong><?php echo $h($displayName); ?></strong>
                <?php if ($title !== '' && $title !== $originalName): ?>
                  <br /><span class="muted"><?php echo $h($originalName); ?></span>
                <?php endif; ?>
                <?php if ($status === 'archived'): ?>
                  <br /><span class="tag">Archivé</span>
                <?php endif; ?>
                <?php if ($legalHold): ?>
                  <br /><span class="tag">Gel juridique</span>
                <?php endif; ?>
                <?php if (($documentRow['deduplicated'] ?? false) === true): ?>
                  <br /><span class="muted">Contenu identique déjà présent (dédupliqué)</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (in_array($status, ['active', 'closed'], true)): ?>
                  <form method="post" action="<?php echo $h($hubActionUrl); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $h($hubCsrfToken); ?>" />
                    <input type="hidden" name="hub_action" value="set_category" />
                    <input type="hidden" name="document_uid" value="<?php echo $h($uid); ?>" />
                    <input type="hidden" name="return_route" value="<?php echo $h($hubReturnRoute); ?>" />
                    <label class="document-hub-category-label">
                      <span class="visually-hidden">Catégorie du document <?php echo $h($displayName); ?></span>
                      <select name="category_code">
                        <?php foreach ($hubCategories as $categoryRow): ?>
                          <?php
                            $code = is_string($categoryRow['code'] ?? null) ? (string) $categoryRow['code'] : '';
                            $parent = is_string($categoryRow['parent_code'] ?? null) ? (string) $categoryRow['parent_code'] : '';
                          ?>
                          <option value="<?php echo $h($code); ?>" <?php echo $code === $categoryCode ? 'selected' : ''; ?>>
                            <?php echo $h(($parent !== '' ? '— ' : '') . ($categoryRow['label'] ?? $code)); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <button type="submit" class="private-button-secondary">OK</button>
                  </form>
                <?php else: ?>
                  <?php echo $h($categoryLabels[$categoryCode] ?? $categoryCode); ?>
                <?php endif; ?>
              </td>
              <td>
                <?php echo $h((string) ($documentRow['document_date'] ?? '') ?: '—'); ?>
                <?php if (!empty($documentRow['fiscal_year'])): ?>
                  <br /><span class="muted"><?php echo (int) $documentRow['fiscal_year']; ?></span>
                <?php endif; ?>
              </td>
              <td><?php echo $h($formatBytes((int) ($documentRow['stored_size'] ?? 0))); ?></td>
              <td>
                <?php if ($linksDescribed === []): ?>
                  <span class="muted">—</span>
                <?php endif; ?>
                <?php foreach ($linksDescribed as $link): ?>
                  <span class="tag" title="<?php echo $h($link['entity_type']); ?>">
                    <?php echo $h($link['label'] !== '' ? $link['label'] : $link['entity_type'] . ' #' . $link['entity_id']); ?>
                  </span>
                <?php endforeach; ?>
              </td>
              <td>
                <a class="private-button-secondary" href="<?php echo $h($downloadUrl); ?>">Télécharger</a>
                <?php if (in_array($status, ['active', 'closed'], true)): ?>
                  <form method="post" action="<?php echo $h($hubActionUrl); ?>" class="document-hub-inline-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $h($hubCsrfToken); ?>" />
                    <input type="hidden" name="hub_action" value="archive" />
                    <input type="hidden" name="document_uid" value="<?php echo $h($uid); ?>" />
                    <input type="hidden" name="return_route" value="<?php echo $h($hubReturnRoute); ?>" />
                    <button type="submit" class="private-button-secondary">Archiver</button>
                  </form>
                <?php endif; ?>
                <?php if ($status === 'trashed'): ?>
                  <form method="post" action="<?php echo $h($hubActionUrl); ?>" class="document-hub-inline-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $h($hubCsrfToken); ?>" />
                    <input type="hidden" name="hub_action" value="restore" />
                    <input type="hidden" name="document_uid" value="<?php echo $h($uid); ?>" />
                    <input type="hidden" name="return_route" value="<?php echo $h($hubReturnRoute); ?>" />
                    <button type="submit" class="private-button-secondary">Restaurer</button>
                  </form>
                <?php elseif (!$legalHold): ?>
                  <form method="post" action="<?php echo $h($hubActionUrl); ?>" class="document-hub-inline-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $h($hubCsrfToken); ?>" />
                    <input type="hidden" name="hub_action" value="trash" />
                    <input type="hidden" name="document_uid" value="<?php echo $h($uid); ?>" />
                    <input type="hidden" name="return_route" value="<?php echo $h($hubReturnRoute); ?>" />
                    <button type="submit" class="private-button-danger">Corbeille</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
