<?php
$filters = is_array($filters ?? null) ? $filters : ['status' => null, 'lang' => null, 'category' => null, 'tag' => null, 'q' => ''];
$articles = is_array($articles ?? null) ? $articles : [];
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
$statusLabels = [
    'draft' => $translate('TXT_ADMIN_ARTICLE_STATUS_DRAFT', 'Brouillon'),
    'scheduled' => $translate('TXT_ADMIN_ARTICLE_STATUS_SCHEDULED', 'Planifié'),
    'published' => $translate('TXT_ADMIN_ARTICLE_STATUS_PUBLISHED', 'Publié'),
];
$draftCount = count(array_filter($articles, static fn (array $article): bool => ($article['status'] ?? '') === 'draft'));
$publishedCount = count(array_filter(
    $articles,
    static fn (array $article): bool => (string) ($article['effectiveStatus'] ?? ($article['status'] ?? '')) === 'published'
));
$scheduledCount = count(array_filter($articles, static fn (array $article): bool => ($article['status'] ?? '') === 'scheduled'));
?>

<section class="cards-grid dashboard-kpis">
  <article class="card dashboard-kpi-card">
    <span class="tag">Éditorial</span>
    <strong class="dashboard-kpi-value"><?php echo count($articles); ?></strong>
    <p class="dashboard-kpi-label">Articles visibles</p>
    <p class="dashboard-kpi-detail">Gestion moderne des articles éditoriaux avec catégorie principale et tags.</p>
    <p class="dashboard-kpi-detail">Le front s’appuie sur ces taxonomies pour la filtration rapide côté serveur.</p>
    <p class="actions-inline dashboard-card-actions">
      <a class="button-link" href="<?php echo htmlspecialchars((string) ($createArticleUrl ?? $adminArticleCreateUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Créer un article</a>
    </p>
  </article>

  <article class="card dashboard-kpi-card">
    <span class="tag">Publication</span>
    <strong class="dashboard-kpi-value"><?php echo $publishedCount; ?></strong>
    <p class="dashboard-kpi-label">Articles publiés</p>
    <p class="dashboard-kpi-detail"><?php echo $draftCount; ?> brouillon(s) visibles dans la vue courante.</p>
    <p class="dashboard-kpi-detail"><?php echo $scheduledCount; ?> article(s) planifié(s) dans la vue courante.</p>
    <p class="dashboard-kpi-detail"><?php echo count(is_array($availableCategories ?? null) ? $availableCategories : []); ?> suggestion(s) de catégorie disponibles.</p>
  </article>
</section>

<?php if (($message ?? null) !== null): ?>
<div class="notice notice-success"><?php echo htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<section class="card admin-articles-filters-card">
  <h2>Filtres</h2>

  <form class="admin-form-grid admin-articles-filters-grid" method="get" action="<?php echo htmlspecialchars((string) ($adminArticlesUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="field admin-articles-filters-search">
      <label for="articles-q">Recherche</label>
      <input id="articles-q" name="q" type="text" value="<?php echo htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="titre, slug, auteur, tag" />
    </div>

    <div class="field">
      <label for="articles-status">Statut</label>
      <select id="articles-status" name="status">
        <option value="">Tous</option>
        <?php foreach (($supportedStatuses ?? []) as $status): ?>
        <option value="<?php echo htmlspecialchars((string) $status, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($filters['status'] ?? null) === $status ? ' selected' : ''; ?>>
          <?php echo htmlspecialchars($statusLabels[$status] ?? (string) $status, ENT_QUOTES, 'UTF-8'); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="articles-lang">Langue</label>
      <select id="articles-lang" name="lang">
        <option value="">Toutes</option>
        <?php foreach (($availableLanguages ?? []) as $language): ?>
        <option value="<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($filters['lang'] ?? null) === $language ? ' selected' : ''; ?>>
          <?php echo strtoupper(htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8')); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="articles-category">Catégorie</label>
      <select id="articles-category" name="category">
        <option value="">Toutes</option>
        <?php foreach (($availableCategories ?? []) as $category): ?>
        <option value="<?php echo htmlspecialchars((string) $category, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($filters['category'] ?? null) === strtolower((string) $category) ? ' selected' : ''; ?>>
          <?php echo htmlspecialchars((string) $category, ENT_QUOTES, 'UTF-8'); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="articles-tag">Tag</label>
      <select id="articles-tag" name="tag">
        <option value="">Tous</option>
        <?php foreach (($availableTags ?? []) as $tag): ?>
        <option value="<?php echo htmlspecialchars((string) $tag, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($filters['tag'] ?? null) === strtolower((string) $tag) ? ' selected' : ''; ?>>
          <?php echo htmlspecialchars((string) $tag, ENT_QUOTES, 'UTF-8'); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="actions-inline admin-articles-filters-actions">
      <button type="submit">Filtrer</button>
      <a class="button-link button-link-muted" href="<?php echo htmlspecialchars((string) ($adminArticlesUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Réinitialiser</a>
    </div>
  </form>
</section>

<section class="card">
  <h2>Articles</h2>

  <?php if ($articles === []): ?>
  <p class="notice-muted">Aucun article ne correspond aux filtres courants.</p>
  <?php else: ?>
  <div class="table-shell">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Titre</th>
          <th>Langue</th>
          <th>Statut</th>
          <th>Catégorie</th>
          <th>Tags</th>
          <th>Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($articles as $article): ?>
        <tr>
          <td>
            <strong><?php echo htmlspecialchars((string) ($article['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong><br />
            <code><?php echo htmlspecialchars((string) ($article['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
            <?php if (($article['pageSlug'] ?? '') !== ''): ?>
            <br /><span class="notice-muted">
              Accroché à la page <?php echo htmlspecialchars((string) (($article['pageTitle'] ?? '') !== '' ? $article['pageTitle'] : $article['pageSlug']), ENT_QUOTES, 'UTF-8'); ?>
              <?php if (($article['pageRoute'] ?? '') !== ''): ?>
              · <?php echo htmlspecialchars((string) $article['pageRoute'], ENT_QUOTES, 'UTF-8'); ?>
              <?php endif; ?>
            </span>
            <?php endif; ?>
            <?php if (($article['parentSlug'] ?? '') !== ''): ?>
            <br /><span class="notice-muted">
              Sous <?php echo htmlspecialchars((string) ($article['parentSlug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
              <?php if (($article['childSortOrder'] ?? null) !== null): ?>
              · ordre <?php echo htmlspecialchars((string) $article['childSortOrder'], ENT_QUOTES, 'UTF-8'); ?>
              <?php endif; ?>
            </span>
            <?php endif; ?>
          </td>
          <td><span class="lang-badge"><?php echo strtoupper(htmlspecialchars((string) ($article['lang'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></span></td>
          <td>
            <?php
            $rawStatus = (string) ($article['status'] ?? '');
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
          <td><?php echo htmlspecialchars((string) ($article['category'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
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
              <a class="button-link" href="<?php echo htmlspecialchars((string) ($article['editPath'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Éditer</a>
              <form method="post" action="<?php echo htmlspecialchars((string) ($article['editPath'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" onsubmit="return confirm('Supprimer définitivement cet article et les discussions rattachées ?');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="article_action" value="delete" />
                <input type="hidden" name="confirm_delete" value="1" />
                <button class="button-danger button-small" type="submit">Supprimer</button>
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
