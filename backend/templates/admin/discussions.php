<?php
$filters = is_array($discussionFilters ?? null) ? $discussionFilters : ['status' => null, 'lang' => null, 'q' => ''];
$rows = is_array($discussionRows ?? null) ? $discussionRows : [];
$counts = is_array($discussionCounts ?? null)
    ? $discussionCounts
    : ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$statusLabels = [
    'pending' => 'En attente',
    'approved' => 'Approuvée',
    'rejected' => 'Rejetée',
];
$formatDate = static function (string $value): string {
    $timestamp = strtotime($value);

    return is_int($timestamp) ? date('d/m/Y H:i', $timestamp) : $value;
};
?>

<section class="cards-grid dashboard-kpis">
  <article class="card dashboard-kpi-card">
    <span class="tag">Modération</span>
    <strong class="dashboard-kpi-value"><?php echo (int) ($counts['pending'] ?? 0); ?></strong>
    <p class="dashboard-kpi-label">Messages en attente</p>
    <p class="dashboard-kpi-detail">Chaque nouveau message est stocké en attente de validation.</p>
  </article>

  <article class="card dashboard-kpi-card">
    <span class="tag">Volume</span>
    <strong class="dashboard-kpi-value"><?php echo (int) ($counts['total'] ?? 0); ?></strong>
    <p class="dashboard-kpi-label">Messages enregistrés</p>
    <p class="dashboard-kpi-detail"><?php echo (int) ($counts['approved'] ?? 0); ?> approuvé(s) · <?php echo (int) ($counts['rejected'] ?? 0); ?> rejeté(s).</p>
  </article>
</section>

<?php if (($message ?? null) !== null): ?>
<div class="notice notice-success"><?php echo htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<?php if (($error ?? null) !== null): ?>
<div class="notice notice-error"><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<section class="card admin-articles-filters-card">
  <h2>Filtres</h2>
  <form class="admin-form-grid admin-articles-filters-grid" method="get" action="<?php echo htmlspecialchars((string) ($adminDiscussionsUrl ?? admin_url('discussions')), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="field admin-articles-filters-search">
      <label for="discussion-q">Recherche</label>
      <input id="discussion-q" name="q" type="text" value="<?php echo htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="auteur, email, article, contenu" />
    </div>

    <div class="field">
      <label for="discussion-status">Statut</label>
      <select id="discussion-status" name="status">
        <option value="">Tous</option>
        <?php foreach (($supportedStatuses ?? []) as $status): ?>
        <option value="<?php echo htmlspecialchars((string) $status, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($filters['status'] ?? null) === $status ? ' selected' : ''; ?>>
          <?php echo htmlspecialchars((string) ($statusLabels[$status] ?? $status), ENT_QUOTES, 'UTF-8'); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="discussion-lang">Langue</label>
      <select id="discussion-lang" name="lang">
        <option value="">Toutes</option>
        <?php foreach (($availableLanguages ?? []) as $language): ?>
        <option value="<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($filters['lang'] ?? null) === $language ? ' selected' : ''; ?>>
          <?php echo strtoupper(htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8')); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="actions-inline admin-articles-filters-actions">
      <button type="submit">Filtrer</button>
      <a class="button-link button-link-muted" href="<?php echo htmlspecialchars((string) ($discussionsResetUrl ?? $adminDiscussionsUrl ?? admin_url('discussions')), ENT_QUOTES, 'UTF-8'); ?>">Réinitialiser</a>
    </div>
  </form>
</section>

<section class="card">
  <h2>Messages</h2>

  <?php if ($rows === []): ?>
  <p class="notice-muted">Aucune discussion ne correspond aux filtres courants.</p>
  <?php else: ?>
  <div class="table-shell">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Article</th>
          <th>Auteur</th>
          <th>Message</th>
          <th>Statut</th>
          <th>Dates</th>
          <th>Actions</th>
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
            Créée: <?php echo htmlspecialchars($formatDate((string) ($row['createdAt'] ?? '')), ENT_QUOTES, 'UTF-8'); ?><br />
            <?php if (trim((string) ($row['moderatedAt'] ?? '')) !== ''): ?>
            Modérée: <?php echo htmlspecialchars($formatDate((string) ($row['moderatedAt'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
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
                <button type="submit">Approuver</button>
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
                <button type="submit" class="button-link button-link-muted">Rejeter</button>
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
                <button type="submit" class="button-link button-link-muted">Remettre en attente</button>
              </form>
              <?php endif; ?>

              <form method="post" action="<?php echo htmlspecialchars((string) ($adminDiscussionsUrl ?? admin_url('discussions')), ENT_QUOTES, 'UTF-8'); ?>" onsubmit="return confirm('Supprimer cette discussion ?');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="discussion_id" value="<?php echo htmlspecialchars((string) ($row['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="discussion_action" value="delete" />
                <input type="hidden" name="status" value="<?php echo htmlspecialchars((string) ($filters['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="lang" value="<?php echo htmlspecialchars((string) ($filters['lang'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="q" value="<?php echo htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <button type="submit" class="button-link button-link-muted">Supprimer</button>
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
