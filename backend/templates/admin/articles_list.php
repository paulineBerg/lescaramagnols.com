<?php
$filters = is_array($filters ?? null) ? $filters : ['status' => null, 'scheduled_date' => null, 'category' => null, 'tag' => null, 'q' => ''];
$articles = is_array($articles ?? null) ? $articles : [];
$articleListSummary = is_array($articleListSummary ?? null) ? $articleListSummary : [];
$categoryOptions = is_array($availableCategoryOptions ?? null) ? array_values($availableCategoryOptions) : [];
$tagOptions = is_array($availableTagOptions ?? null) ? array_values($availableTagOptions) : [];
$translate = static function (string $key, string $fallback): string {
    if (function_exists('admin_translate')) {
        return admin_translate($key, $fallback);
    }

    if (!function_exists('t')) {
        return $fallback;
    }

    $translated = t($key);
    if (!is_string($translated) || $translated === '' || $translated === '[[' . $key . ']]') {
        return $fallback;
    }

    return $translated;
};
$statusLabels = [
    'draft' => $translate('TXT_ADMIN_ARTICLE_STATUS_DRAFT', 'Brouillon'),
    'scheduled' => $translate('TXT_ADMIN_ARTICLE_STATUS_SCHEDULED', 'Planifié'),
    'published' => $translate('TXT_ADMIN_ARTICLE_STATUS_PUBLISHED', 'Publié'),
];
$deleteWarningTemplate = function_exists('admin_translate')
    ? admin_translate('TXT_ADMIN_ARTICLE_DELETE_WARNING_TEMPLATE', 'Supprimer définitivement la version %s et les discussions rattachées ?')
    : 'Supprimer définitivement la version %s et les discussions rattachées ?';
$legacyDraftCount = count(array_filter(
    $articles,
    static fn (array $article): bool => ($article['status'] ?? '') === 'draft'
));
$legacyPublishedCount = count(array_filter(
    $articles,
    static fn (array $article): bool => (string) ($article['effectiveStatus'] ?? ($article['status'] ?? '')) === 'published'
));
$legacyScheduledCount = count(array_filter(
    $articles,
    static fn (array $article): bool => ($article['status'] ?? '') === 'scheduled'
));
$visibleArticleCount = (int) ($articleListSummary['total'] ?? count($articles));
$draftCount = (int) ($articleListSummary['drafts'] ?? $legacyDraftCount);
$publishedCount = (int) ($articleListSummary['published'] ?? $legacyPublishedCount);
$scheduledCount = (int) ($articleListSummary['scheduled'] ?? $legacyScheduledCount);
?>

<section class="cards-grid dashboard-kpis">
  <article class="card dashboard-kpi-card">
    <span class="tag"><?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLES_EDITORIAL_TAG', 'Éditorial'), ENT_QUOTES, 'UTF-8'); ?></span>
    <strong class="dashboard-kpi-value"><?php echo $visibleArticleCount; ?></strong>
    <p class="dashboard-kpi-label"><?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLES_VISIBLE_LABEL', 'Articles visibles'), ENT_QUOTES, 'UTF-8'); ?></p>
    <p class="dashboard-kpi-detail"><?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLES_EDITORIAL_DETAIL', 'Gestion moderne des articles éditoriaux avec catégorie principale et tags.'), ENT_QUOTES, 'UTF-8'); ?></p>
    <p class="dashboard-kpi-detail"><?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLES_FILTERING_DETAIL', 'Le front s’appuie sur ces taxonomies pour la filtration rapide côté serveur.'), ENT_QUOTES, 'UTF-8'); ?></p>
    <p class="actions-inline dashboard-card-actions">
      <a class="button-link" href="<?php echo htmlspecialchars((string) ($createArticleUrl ?? $adminArticleCreateUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLES_CREATE', 'Créer un article'), ENT_QUOTES, 'UTF-8'); ?></a>
    </p>
  </article>

  <article class="card dashboard-kpi-card">
    <span class="tag"><?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLES_PUBLICATION_TAG', 'Publication'), ENT_QUOTES, 'UTF-8'); ?></span>
    <strong class="dashboard-kpi-value"><?php echo $publishedCount; ?></strong>
    <p class="dashboard-kpi-label"><?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLES_PUBLISHED_LABEL', 'Articles publiés'), ENT_QUOTES, 'UTF-8'); ?></p>
    <p class="dashboard-kpi-detail"><?php echo htmlspecialchars(sprintf($translate('TXT_ADMIN_ARTICLES_DRAFT_VISIBLE', '%d brouillon(s) visibles dans la vue courante.'), $draftCount), ENT_QUOTES, 'UTF-8'); ?></p>
    <p class="dashboard-kpi-detail"><?php echo htmlspecialchars(sprintf($translate('TXT_ADMIN_ARTICLES_SCHEDULED_VISIBLE', '%d article(s) planifié(s) dans la vue courante.'), $scheduledCount), ENT_QUOTES, 'UTF-8'); ?></p>
    <p class="dashboard-kpi-detail"><?php echo htmlspecialchars(sprintf($translate('TXT_ADMIN_ARTICLES_CANONICAL_CATEGORIES', '%d catégorie(s) canoniques disponibles.'), count($categoryOptions)), ENT_QUOTES, 'UTF-8'); ?></p>
  </article>
</section>

<?php if (($message ?? null) !== null): ?>
<div class="notice notice-success"><?php echo htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<section class="card admin-articles-filters-card">
  <h2><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_FILTERS', 'Filtres'), ENT_QUOTES, 'UTF-8'); ?></h2>

  <form class="admin-form-grid admin-articles-filters-grid" method="get" action="<?php echo htmlspecialchars((string) ($adminArticlesUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="field admin-articles-filters-search">
      <label for="articles-q"><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_SEARCH', 'Recherche'), ENT_QUOTES, 'UTF-8'); ?></label>
      <input id="articles-q" name="q" type="text" value="<?php echo htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLES_SEARCH_PLACEHOLDER', 'titre, slug, auteur, tag'), ENT_QUOTES, 'UTF-8'); ?>" />
    </div>

    <div class="field">
      <label for="articles-status"><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_STATUS', 'Statut'), ENT_QUOTES, 'UTF-8'); ?></label>
      <select id="articles-status" name="status">
        <option value=""><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_ALL', 'Tous'), ENT_QUOTES, 'UTF-8'); ?></option>
        <?php foreach (($supportedStatuses ?? []) as $status): ?>
        <option value="<?php echo htmlspecialchars((string) $status, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($filters['status'] ?? null) === $status ? ' selected' : ''; ?>>
          <?php echo htmlspecialchars($statusLabels[$status] ?? (string) $status, ENT_QUOTES, 'UTF-8'); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="articles-scheduled-date"><?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLE_SCHEDULED_AT_LABEL', 'Publication programmée'), ENT_QUOTES, 'UTF-8'); ?></label>
      <input id="articles-scheduled-date" name="scheduled_date" type="date" value="<?php echo htmlspecialchars((string) ($filters['scheduled_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
    </div>

    <div class="field">
      <label for="articles-category"><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CATEGORY', 'Catégorie'), ENT_QUOTES, 'UTF-8'); ?></label>
      <select id="articles-category" name="category">
        <option value=""><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_ALL_FEMININE', 'Toutes'), ENT_QUOTES, 'UTF-8'); ?></option>
        <?php foreach ($categoryOptions as $category): ?>
        <?php $categorySlug = (string) ($category['slug'] ?? ''); ?>
        <option value="<?php echo htmlspecialchars($categorySlug, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($filters['category'] ?? null) === $categorySlug ? ' selected' : ''; ?>>
          <?php echo htmlspecialchars((string) ($category['label'] ?? $categorySlug), ENT_QUOTES, 'UTF-8'); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="articles-tag"><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_TAG', 'Tag'), ENT_QUOTES, 'UTF-8'); ?></label>
      <select id="articles-tag" name="tag">
        <option value=""><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_ALL', 'Tous'), ENT_QUOTES, 'UTF-8'); ?></option>
        <?php foreach ($tagOptions as $tag): ?>
        <?php $tagSlug = (string) ($tag['slug'] ?? ''); ?>
        <option value="<?php echo htmlspecialchars($tagSlug, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($filters['tag'] ?? null) === $tagSlug ? ' selected' : ''; ?>>
          <?php echo htmlspecialchars((string) ($tag['label'] ?? $tagSlug), ENT_QUOTES, 'UTF-8'); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="actions-inline admin-articles-filters-actions">
      <button type="submit"><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_FILTER', 'Filtrer'), ENT_QUOTES, 'UTF-8'); ?></button>
      <a class="button-link button-link-muted" href="<?php echo htmlspecialchars((string) ($articlesResetUrl ?? $adminArticlesUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_RESET', 'Réinitialiser'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
  </form>
</section>

<section class="card">
  <h2><?php echo htmlspecialchars($translate('TXT_ADMIN_NAV_ARTICLES', 'Articles'), ENT_QUOTES, 'UTF-8'); ?></h2>

<?php if ($articles === []): ?>
  <p class="notice-muted"><?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLES_NO_RESULTS', 'Aucun article ne correspond aux filtres courants.'), ENT_QUOTES, 'UTF-8'); ?></p>
  <?php else: ?>
  <div class="table-shell">
    <table class="admin-table">
      <thead>
        <tr>
          <th><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_SLUG', 'Slug'), ENT_QUOTES, 'UTF-8'); ?></th>
          <th><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_TITLE', 'Titre'), ENT_QUOTES, 'UTF-8'); ?></th>
          <th><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_LANGUAGES', 'Langues'), ENT_QUOTES, 'UTF-8'); ?></th>
          <th><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_STATUS', 'Statut'), ENT_QUOTES, 'UTF-8'); ?></th>
          <th><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CATEGORY', 'Catégorie'), ENT_QUOTES, 'UTF-8'); ?></th>
          <th><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_SUBCATEGORY', 'Sous-catégorie'), ENT_QUOTES, 'UTF-8'); ?></th>
          <th><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_TAGS', 'Tags'), ENT_QUOTES, 'UTF-8'); ?></th>
          <th><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_DATE', 'Date'), ENT_QUOTES, 'UTF-8'); ?></th>
          <th><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_ACTION', 'Action'), ENT_QUOTES, 'UTF-8'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($articles as $article): ?>
        <?php
        $articleSlug = (string) ($article['slug'] ?? '');
        $articleLanguages = is_array($article['languages'] ?? null) ? $article['languages'] : [];
        $articleMissingLanguages = is_array($article['missingLanguages'] ?? null) ? $article['missingLanguages'] : [];
        $articleTranslations = is_array($article['translations'] ?? null) ? $article['translations'] : [];
        ?>
        <tr>
          <td><code><?php echo htmlspecialchars($articleSlug, ENT_QUOTES, 'UTF-8'); ?></code></td>
          <td>
            <strong><?php echo htmlspecialchars((string) ($article['title'] ?? $articleSlug), ENT_QUOTES, 'UTF-8'); ?></strong>
            <?php if (($article['pageSlug'] ?? '') !== ''): ?>
            <br /><span class="notice-muted">
              <?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLES_ATTACHED_TO_PAGE', 'Accroché à la page'), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars((string) (($article['pageTitle'] ?? '') !== '' ? $article['pageTitle'] : $article['pageSlug']), ENT_QUOTES, 'UTF-8'); ?>
              <?php if (($article['pageRoute'] ?? '') !== ''): ?>
              · <?php echo htmlspecialchars((string) $article['pageRoute'], ENT_QUOTES, 'UTF-8'); ?>
              <?php endif; ?>
            </span>
            <?php endif; ?>
            <?php if (($article['parentSlug'] ?? '') !== ''): ?>
            <br /><span class="notice-muted">
              <?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLES_UNDER_PARENT', 'Sous'), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars((string) ($article['parentSlug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
              <?php if (($article['childSortOrder'] ?? null) !== null): ?>
              · <?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLES_MANUAL_ORDER', 'ordre'), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars((string) $article['childSortOrder'], ENT_QUOTES, 'UTF-8'); ?>
              <?php endif; ?>
            </span>
            <?php endif; ?>
          </td>
          <td>
            <div class="lang-badges">
              <?php foreach ($articleLanguages as $language): ?>
              <?php if (!is_string($language) || $language === ''): ?>
              <?php continue; ?>
              <?php endif; ?>
              <span class="lang-badge"><?php echo strtoupper(htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8')); ?></span>
              <?php endforeach; ?>
              <?php foreach ($articleMissingLanguages as $language): ?>
              <?php if (!is_string($language) || $language === ''): ?>
              <?php continue; ?>
              <?php endif; ?>
              <span class="lang-badge lang-badge-missing"><?php echo strtoupper(htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8')); ?></span>
              <?php endforeach; ?>
            </div>
          </td>
          <td>
            <?php
            $rawStatus = (string) ($article['status'] ?? 'draft');
            $effectiveStatus = (string) ($article['effectiveStatus'] ?? $rawStatus);
            ?>
            <span class="status-pill status-<?php echo htmlspecialchars($effectiveStatus, ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo htmlspecialchars($statusLabels[$rawStatus] ?? $rawStatus, ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <?php if ($rawStatus === 'scheduled'): ?>
            <br /><span class="notice-muted">
              <?php if ($effectiveStatus === 'published'): ?>
              <?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLE_SCHEDULED_IS_LIVE', 'Publication automatique atteinte : article visible sur le front.'), ENT_QUOTES, 'UTF-8'); ?>
              <?php else: ?>
              <?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLE_SCHEDULED_PENDING', 'En attente de publication automatique.'), ENT_QUOTES, 'UTF-8'); ?>
              <?php endif; ?>
            </span>
            <?php endif; ?>
          </td>
          <td><?php echo htmlspecialchars((string) (($article['category'] ?? '') !== '' ? $article['category'] : '—'), ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars((string) (($article['subcategory'] ?? '') !== '' ? $article['subcategory'] : '—'), ENT_QUOTES, 'UTF-8'); ?></td>
          <td>
            <?php $tags = is_array($article['tags'] ?? null) ? $article['tags'] : []; ?>
            <?php if ($tags === []): ?>
            <span class="notice-muted">—</span>
            <?php else: ?>
            <div class="taxonomy-chip-list">
              <?php foreach ($tags as $tag): ?>
              <span class="taxonomy-chip"><?php echo htmlspecialchars((string) $tag, ENT_QUOTES, 'UTF-8'); ?></span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </td>
          <td><?php echo htmlspecialchars((string) ($article['date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          <td>
            <div class="actions-inline">
              <?php foreach ($articleLanguages as $language): ?>
              <?php if (!is_string($language) || $language === ''): ?>
              <?php continue; ?>
              <?php endif; ?>
              <?php $translation = is_array($articleTranslations[$language] ?? null) ? $articleTranslations[$language] : []; ?>
              <?php if ($translation === []): ?>
              <?php continue; ?>
              <?php endif; ?>
              <?php $editPath = is_string($translation['editPath'] ?? null) ? trim((string) $translation['editPath']) : ''; ?>
              <?php if ($editPath === ''): ?>
              <?php continue; ?>
              <?php endif; ?>
              <?php $languageLabel = strtoupper((string) $language); ?>
              <?php $deleteWarning = sprintf($deleteWarningTemplate, $languageLabel); ?>
              <a class="button-link" href="<?php echo htmlspecialchars((string) $editPath, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(sprintf($translate('TXT_ADMIN_ARTICLES_EDIT_LANGUAGE', 'Éditer %s'), strtoupper((string) $language)), ENT_QUOTES, 'UTF-8'); ?></a>
              <form
                method="post"
                action="<?php echo htmlspecialchars((string) $editPath, ENT_QUOTES, 'UTF-8'); ?>"
                class="admin-article-row-action"
                data-article-delete-form
                data-article-delete-warning="<?php echo htmlspecialchars((string) $deleteWarning, ENT_QUOTES, 'UTF-8'); ?>"
              >
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="article_action" value="delete" />
                <input type="hidden" name="confirm_delete" value="1" />
                <button class="button-danger button-small" type="submit"><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_DELETE', 'Supprimer'), ENT_QUOTES, 'UTF-8'); ?></button>
              </form>
              <?php endforeach; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>

<script>
(function () {
  document.querySelectorAll('form[data-article-delete-form]').forEach(function (form) {
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    form.addEventListener('submit', function (event) {
      const warning = form.getAttribute('data-article-delete-warning')
        || <?php echo json_encode($translate('TXT_ADMIN_ARTICLE_DELETE_WARNING_FALLBACK', 'Supprimer définitivement cette version et les discussions rattachées ?'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

      if (!window.confirm(warning)) {
        event.preventDefault();
      }
    });
  });
})();
</script>
