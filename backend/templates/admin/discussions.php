<?php
$filters = is_array($discussionFilters ?? null) ? $discussionFilters : ['status' => null, 'lang' => null, 'q' => ''];
$rows = is_array($discussionRows ?? null) ? $discussionRows : [];
$counts = is_array($discussionCounts ?? null)
    ? $discussionCounts
    : ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
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
    'pending' => $translate('TXT_ADMIN_DISCUSSION_STATUS_PENDING', 'En attente'),
    'approved' => $translate('TXT_ADMIN_DISCUSSION_STATUS_APPROVED', 'Approuvée'),
    'rejected' => $translate('TXT_ADMIN_DISCUSSION_STATUS_REJECTED', 'Rejetée'),
];
$formatDate = static function (string $value): string {
    $timestamp = strtotime($value);

    return is_int($timestamp) ? date('d/m/Y H:i', $timestamp) : $value;
};
?>

<section class="cards-grid dashboard-kpis">
  <article class="card dashboard-kpi-card">
    <span class="tag"><?php echo htmlspecialchars($translate('TXT_ADMIN_DISCUSSIONS_MODERATION_TAG', 'Modération'), ENT_QUOTES, 'UTF-8'); ?></span>
    <strong class="dashboard-kpi-value"><?php echo (int) ($counts['pending'] ?? 0); ?></strong>
    <p class="dashboard-kpi-label"><?php echo htmlspecialchars($translate('TXT_ADMIN_DISCUSSIONS_PENDING_LABEL', 'Messages en attente'), ENT_QUOTES, 'UTF-8'); ?></p>
    <p class="dashboard-kpi-detail"><?php echo htmlspecialchars($translate('TXT_ADMIN_DISCUSSIONS_PENDING_DETAIL', 'Chaque nouveau message est stocké en attente de validation.'), ENT_QUOTES, 'UTF-8'); ?></p>
  </article>

  <article class="card dashboard-kpi-card">
    <span class="tag"><?php echo htmlspecialchars($translate('TXT_ADMIN_DISCUSSIONS_VOLUME_TAG', 'Volume'), ENT_QUOTES, 'UTF-8'); ?></span>
    <strong class="dashboard-kpi-value"><?php echo (int) ($counts['total'] ?? 0); ?></strong>
    <p class="dashboard-kpi-label"><?php echo htmlspecialchars($translate('TXT_ADMIN_DISCUSSIONS_RECORDED_LABEL', 'Messages enregistrés'), ENT_QUOTES, 'UTF-8'); ?></p>
    <p class="dashboard-kpi-detail"><?php echo htmlspecialchars(sprintf($translate('TXT_ADMIN_DISCUSSIONS_APPROVED_REJECTED_DETAIL', '%d approuvé(s) · %d rejeté(s).'), (int) ($counts['approved'] ?? 0), (int) ($counts['rejected'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></p>
  </article>
</section>

<?php if (($message ?? null) !== null): ?>
<div class="notice notice-success"><?php echo htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<?php if (($error ?? null) !== null): ?>
<div class="notice notice-error"><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<section class="card admin-articles-filters-card">
  <h2><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_FILTERS', 'Filtres'), ENT_QUOTES, 'UTF-8'); ?></h2>
  <form class="admin-form-grid admin-articles-filters-grid" method="get" action="<?php echo htmlspecialchars((string) ($adminDiscussionsUrl ?? admin_url('discussions')), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="field admin-articles-filters-search">
      <label for="discussion-q"><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_SEARCH', 'Recherche'), ENT_QUOTES, 'UTF-8'); ?></label>
      <input id="discussion-q" name="q" type="text" value="<?php echo htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars($translate('TXT_ADMIN_DISCUSSIONS_SEARCH_PLACEHOLDER', 'auteur, email, article, contenu'), ENT_QUOTES, 'UTF-8'); ?>" />
    </div>

    <div class="field">
      <label for="discussion-status"><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_STATUS', 'Statut'), ENT_QUOTES, 'UTF-8'); ?></label>
      <select id="discussion-status" name="status">
        <option value=""><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_ALL', 'Tous'), ENT_QUOTES, 'UTF-8'); ?></option>
        <?php foreach (($supportedStatuses ?? []) as $status): ?>
        <option value="<?php echo htmlspecialchars((string) $status, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($filters['status'] ?? null) === $status ? ' selected' : ''; ?>>
          <?php echo htmlspecialchars((string) ($statusLabels[$status] ?? $status), ENT_QUOTES, 'UTF-8'); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="discussion-lang"><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_LANGUAGE', 'Langue'), ENT_QUOTES, 'UTF-8'); ?></label>
      <select id="discussion-lang" name="lang">
        <option value=""><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_ALL_FEMININE', 'Toutes'), ENT_QUOTES, 'UTF-8'); ?></option>
        <?php foreach (($availableLanguages ?? []) as $language): ?>
        <option value="<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($filters['lang'] ?? null) === $language ? ' selected' : ''; ?>>
          <?php echo strtoupper(htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8')); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="actions-inline admin-articles-filters-actions">
      <button type="submit"><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_FILTER', 'Filtrer'), ENT_QUOTES, 'UTF-8'); ?></button>
      <a class="button-link button-link-muted" href="<?php echo htmlspecialchars((string) ($discussionsResetUrl ?? $adminDiscussionsUrl ?? admin_url('discussions')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_RESET', 'Réinitialiser'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
  </form>
</section>

<section class="card">
  <h2><?php echo htmlspecialchars($translate('TXT_ADMIN_DISCUSSIONS_MESSAGES_TITLE', 'Messages'), ENT_QUOTES, 'UTF-8'); ?></h2>

  <?php if ($rows === []): ?>
  <p class="notice-muted"><?php echo htmlspecialchars($translate('TXT_ADMIN_DISCUSSIONS_NO_RESULTS', 'Aucune discussion ne correspond aux filtres courants.'), ENT_QUOTES, 'UTF-8'); ?></p>
  <?php else: ?>
  <div class="table-shell">
    <table class="admin-table">
      <thead>
        <tr>
          <th><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_ARTICLE', 'Article'), ENT_QUOTES, 'UTF-8'); ?></th>
          <th><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_AUTHOR', 'Auteur'), ENT_QUOTES, 'UTF-8'); ?></th>
          <th><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_MESSAGE', 'Message'), ENT_QUOTES, 'UTF-8'); ?></th>
          <th><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_STATUS', 'Statut'), ENT_QUOTES, 'UTF-8'); ?></th>
          <th><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_DATES', 'Dates'), ENT_QUOTES, 'UTF-8'); ?></th>
          <th><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_ACTIONS', 'Actions'), ENT_QUOTES, 'UTF-8'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
          <td>
            <strong><?php echo htmlspecialchars((string) ($row['articleTitle'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong><br />
            <a href="<?php echo htmlspecialchars((string) ($row['articleUrl'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
              <?php echo htmlspecialchars((string) ($row['articleSlug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · <?php echo strtoupper(htmlspecialchars((string) ($row['articleLang'] ?? ''), ENT_QUOTES, 'UTF-8')); ?>
            </a>
          </td>
          <td>
            <strong><?php echo htmlspecialchars((string) ($row['author'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong><br />
            <span class="notice-muted"><?php echo htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
          </td>
          <td><?php echo htmlspecialchars((string) ($row['content'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
          <td>
            <span class="status-pill status-<?php echo htmlspecialchars((string) ($row['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo htmlspecialchars((string) ($statusLabels[(string) ($row['status'] ?? 'pending')] ?? ($row['status'] ?? 'pending')), ENT_QUOTES, 'UTF-8'); ?>
            </span>
          </td>
          <td>
            <?php echo htmlspecialchars($translate('TXT_ADMIN_DISCUSSIONS_CREATED_AT', 'Créée:'), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($formatDate((string) ($row['createdAt'] ?? '')), ENT_QUOTES, 'UTF-8'); ?><br />
            <?php if (trim((string) ($row['moderatedAt'] ?? '')) !== ''): ?>
            <?php echo htmlspecialchars($translate('TXT_ADMIN_DISCUSSIONS_MODERATED_AT', 'Modérée:'), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($formatDate((string) ($row['moderatedAt'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
            <?php if (trim((string) ($row['moderatedBy'] ?? '')) !== ''): ?>
            <br /><span class="notice-muted"><?php echo htmlspecialchars((string) ($row['moderatedBy'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
            <?php endif; ?>
          </td>
          <td>
            <div class="actions-inline">
              <?php if (($row['status'] ?? 'pending') !== 'approved'): ?>
              <form method="post" action="<?php echo htmlspecialchars((string) ($adminDiscussionsUrl ?? admin_url('discussions')), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="discussion_id" value="<?php echo htmlspecialchars((string) ($row['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="discussion_action" value="approve" />
                <input type="hidden" name="status" value="<?php echo htmlspecialchars((string) ($filters['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="lang" value="<?php echo htmlspecialchars((string) ($filters['lang'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="q" value="<?php echo htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <button type="submit"><?php echo htmlspecialchars($translate('TXT_ADMIN_DISCUSSIONS_APPROVE', 'Approuver'), ENT_QUOTES, 'UTF-8'); ?></button>
              </form>
              <?php endif; ?>

              <?php if (($row['status'] ?? 'pending') !== 'rejected'): ?>
              <form method="post" action="<?php echo htmlspecialchars((string) ($adminDiscussionsUrl ?? admin_url('discussions')), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="discussion_id" value="<?php echo htmlspecialchars((string) ($row['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="discussion_action" value="reject" />
                <input type="hidden" name="status" value="<?php echo htmlspecialchars((string) ($filters['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="lang" value="<?php echo htmlspecialchars((string) ($filters['lang'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="q" value="<?php echo htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <button type="submit" class="button-link button-link-muted"><?php echo htmlspecialchars($translate('TXT_ADMIN_DISCUSSIONS_REJECT', 'Rejeter'), ENT_QUOTES, 'UTF-8'); ?></button>
              </form>
              <?php endif; ?>

              <?php if (($row['status'] ?? 'pending') !== 'pending'): ?>
              <form method="post" action="<?php echo htmlspecialchars((string) ($adminDiscussionsUrl ?? admin_url('discussions')), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="discussion_id" value="<?php echo htmlspecialchars((string) ($row['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="discussion_action" value="pending" />
                <input type="hidden" name="status" value="<?php echo htmlspecialchars((string) ($filters['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="lang" value="<?php echo htmlspecialchars((string) ($filters['lang'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="q" value="<?php echo htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <button type="submit" class="button-link button-link-muted"><?php echo htmlspecialchars($translate('TXT_ADMIN_DISCUSSIONS_BACK_TO_PENDING', 'Remettre en attente'), ENT_QUOTES, 'UTF-8'); ?></button>
              </form>
              <?php endif; ?>

              <form method="post" action="<?php echo htmlspecialchars((string) ($adminDiscussionsUrl ?? admin_url('discussions')), ENT_QUOTES, 'UTF-8'); ?>" onsubmit="return confirm('<?php echo htmlspecialchars($translate('TXT_ADMIN_DISCUSSIONS_DELETE_CONFIRM', 'Supprimer cette discussion ?'), ENT_QUOTES, 'UTF-8'); ?>');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="discussion_id" value="<?php echo htmlspecialchars((string) ($row['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="discussion_action" value="delete" />
                <input type="hidden" name="status" value="<?php echo htmlspecialchars((string) ($filters['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="lang" value="<?php echo htmlspecialchars((string) ($filters['lang'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="q" value="<?php echo htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <button type="submit" class="button-link button-link-muted"><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_DELETE', 'Supprimer'), ENT_QUOTES, 'UTF-8'); ?></button>
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
