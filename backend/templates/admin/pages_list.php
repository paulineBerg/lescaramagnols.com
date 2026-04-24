<?php
$statusLabels = [
    'draft' => 'Brouillon',
    'published' => 'Publié',
];
$pages = is_array($pages ?? null) ? $pages : [];
$draftCount = count(array_filter($pages, static fn (array $page): bool => ($page['status'] ?? '') === 'draft'));
$publishedCount = count(array_filter($pages, static fn (array $page): bool => ($page['status'] ?? '') === 'published'));
$currentStatusFilter = is_string($statusFilter ?? null) ? trim((string) $statusFilter) : '';
$currentLanguageFilter = is_string($languageFilter ?? null) ? trim((string) $languageFilter) : '';
$currentSearchQuery = is_string($searchQuery ?? null) ? trim((string) $searchQuery) : '';
?>

<section class="cards-grid">
  <article class="card">
    <h2>Registre éditorial</h2>
    <p>
      Le registre éditorial pilote les pages structurées. Selon la configuration, il est servi depuis
      <code>JSON</code>, <code>SQL</code> ou <code>double écriture</code>. Le workflow
      <code>brouillon / publié</code> est porté ici.
    </p>
    <p class="actions">
      <a class="button-link" href="<?php echo htmlspecialchars((string) ($createPageUrl ?? $adminPageCreateUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Créer une page</a>
    </p>
  </article>

  <article class="card">
    <h2>Vue rapide</h2>
    <ul>
      <li><span class="tag">Pages</span> <?php echo count($pages); ?> entrées visibles avec les filtres courants.</li>
      <li><span class="tag">Publié</span> <?php echo $publishedCount; ?> page(s).</li>
      <li><span class="tag">Brouillon</span> <?php echo $draftCount; ?> page(s).</li>
    </ul>
  </article>
</section>

<?php if (($message ?? null) !== null): ?>
<div class="notice notice-success"><?php echo htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<section class="card admin-pages-filters-card">
  <h2>Filtres</h2>

  <form class="admin-form-grid admin-pages-filters-grid" method="get" action="<?php echo htmlspecialchars((string) ($adminPagesUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="field admin-pages-filters-search">
      <label for="pages-q">Recherche</label>
      <input id="pages-q" name="q" type="text" value="<?php echo htmlspecialchars((string) ($searchQuery ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="slug, titre, route" />
    </div>

    <div class="field">
      <label for="pages-status">Statut</label>
      <select id="pages-status" name="status">
        <option value="">Tous</option>
        <?php foreach (($supportedStatuses ?? []) as $status): ?>
        <option value="<?php echo htmlspecialchars((string) $status, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($statusFilter ?? '') === $status ? ' selected' : ''; ?>>
          <?php echo htmlspecialchars($statusLabels[$status] ?? (string) $status, ENT_QUOTES, 'UTF-8'); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="pages-lang">Langue</label>
      <select id="pages-lang" name="lang">
        <option value="">Toutes</option>
        <?php foreach (($availableLanguages ?? []) as $language): ?>
        <option value="<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($languageFilter ?? '') === $language ? ' selected' : ''; ?>>
          <?php echo strtoupper(htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8')); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="actions-inline admin-pages-filters-actions">
      <a class="button-link button-link-muted" href="<?php echo htmlspecialchars((string) ($pagesResetUrl ?? $adminPagesUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Réinitialiser</a>
      <button type="submit">Filtrer</button>
    </div>
  </form>
</section>

<section class="card">
  <h2>Pages</h2>

  <?php if ($pages === []): ?>
  <p class="notice-muted">Aucune page ne correspond aux filtres courants.</p>
  <?php else: ?>
  <div class="table-shell">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Slug</th>
          <th>Titre</th>
          <th>Statut</th>
          <th>Langues</th>
          <th>Route</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pages as $page): ?>
        <?php
        $pageSlug = (string) ($page['slug'] ?? '');
        $languages = is_array($page['languages'] ?? null) ? $page['languages'] : [];
        $missingLanguages = is_array($page['missingLanguages'] ?? null) ? $page['missingLanguages'] : [];
        ?>
        <tr>
          <td><code><?php echo htmlspecialchars($pageSlug, ENT_QUOTES, 'UTF-8'); ?></code></td>
          <td><?php echo htmlspecialchars((string) ($page['title'] ?? $pageSlug), ENT_QUOTES, 'UTF-8'); ?></td>
          <td>
            <span class="status-pill status-<?php echo htmlspecialchars((string) ($page['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo htmlspecialchars($statusLabels[(string) ($page['status'] ?? '')] ?? (string) ($page['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
            </span>
          </td>
          <td>
            <div class="lang-badges">
              <?php foreach ($languages as $language): ?>
              <span class="lang-badge"><?php echo strtoupper(htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8')); ?></span>
              <?php endforeach; ?>
              <?php foreach ($missingLanguages as $language): ?>
              <span class="lang-badge lang-badge-missing"><?php echo strtoupper(htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8')); ?></span>
              <?php endforeach; ?>
            </div>
          </td>
          <td><code><?php echo htmlspecialchars((string) ($page['route'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
          <td>
            <?php $pageEditPath = admin_url('pages') . '/' . rawurlencode($pageSlug); ?>
            <div class="actions-inline admin-pages-row-actions">
              <a class="button-link admin-pages-row-action" href="<?php echo htmlspecialchars($pageEditPath, ENT_QUOTES, 'UTF-8'); ?>">Éditer</a>
              <?php
              $deleteWarningTemplate = (string) t('TXT_ADMIN_PAGES_DELETE_WARNING_TEMPLATE');
              if ($deleteWarningTemplate === 'TXT_ADMIN_PAGES_DELETE_WARNING_TEMPLATE') {
                  $deleteWarningTemplate = "ATTENTION : suppression definitive de la page \"%s\".\nToutes les traductions associees seront supprimees.";
              }

              $deleteWarning = sprintf($deleteWarningTemplate, $pageSlug);
              ?>
              <form class="admin-pages-row-action-form" method="post" action="<?php echo htmlspecialchars($pageEditPath, ENT_QUOTES, 'UTF-8'); ?>" data-page-delete-form data-delete-warning="<?php echo htmlspecialchars($deleteWarning, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="page_action" value="delete" />
                <input type="hidden" name="confirm_delete" value="1" />
                <?php if ($currentStatusFilter !== ''): ?>
                <input type="hidden" name="return_status" value="<?php echo htmlspecialchars($currentStatusFilter, ENT_QUOTES, 'UTF-8'); ?>" />
                <?php endif; ?>
                <?php if ($currentLanguageFilter !== ''): ?>
                <input type="hidden" name="return_lang" value="<?php echo htmlspecialchars($currentLanguageFilter, ENT_QUOTES, 'UTF-8'); ?>" />
                <?php endif; ?>
                <?php if ($currentSearchQuery !== ''): ?>
                <input type="hidden" name="return_q" value="<?php echo htmlspecialchars($currentSearchQuery, ENT_QUOTES, 'UTF-8'); ?>" />
                <?php endif; ?>
                <button class="button-danger admin-pages-row-action" type="submit">Supprimer</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>

<?php $cspNonce = (string) ($GLOBALS['csp_nonce'] ?? ''); ?>
<script<?php echo $cspNonce !== '' ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
(function () {
  function confirmPageDelete(form) {
    if (!(form instanceof HTMLFormElement)) {
      return false;
    }

    const warning = form.getAttribute('data-delete-warning') || 'ATTENTION : suppression definitive.';
    return window.confirm(warning);
  }

  document.querySelectorAll('form[data-page-delete-form]').forEach(function (form) {
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    form.addEventListener('submit', function (event) {
      if (!confirmPageDelete(form)) {
        event.preventDefault();
      }
    });
  });
})();
</script>
