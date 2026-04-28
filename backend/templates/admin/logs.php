<?php
$logsView = is_array($logsView ?? null) ? $logsView : [];
$filters = is_array($logsView['filters'] ?? null) ? $logsView['filters'] : [
    'q' => '',
    'channel' => '',
    'level' => '',
    'date_from' => '',
    'date_to' => '',
];
$entries = is_array($logsView['entries'] ?? null) ? $logsView['entries'] : [];
$availableChannels = is_array($logsView['availableChannels'] ?? null) ? $logsView['availableChannels'] : [];
$availableLevels = is_array($logsView['availableLevels'] ?? null) ? $logsView['availableLevels'] : [];
$storageAvailable = (bool) ($logsView['storageAvailable'] ?? false);
$hasActiveFilters = (bool) ($logsView['hasActiveFilters'] ?? false);
$filteredCount = (int) ($logsView['filteredCount'] ?? 0);
$totalCount = (int) ($logsView['totalCount'] ?? 0);
$limit = (int) ($logsView['limit'] ?? 200);
$storageMessage = is_string($logsView['storageMessage'] ?? null) ? $logsView['storageMessage'] : null;
$cronLogsUrl = (string) ($adminLogsUrl ?? admin_url('logs')) . '?q=cron.';
$levelLabels = [
    'debug' => 'Debug',
    'info' => 'Info',
    'warning' => 'Warning',
    'error' => 'Erreur',
];
$bulkSelectionFormId = 'logs-bulk-selection-form';
$contextDetailLabels = [
    'actor' => 'Acteur',
    'identifier' => 'Identifiant',
    'ip' => 'IP',
    'visitor_id' => 'Visiteur',
    'uri' => 'URI',
    'query' => 'Query',
    'referer' => 'Référent',
    'user_agent' => 'User-Agent',
    'method' => 'Méthode',
    'page' => 'Écran',
    'template' => 'Template',
    'slug' => 'Slug',
    'status' => 'Statut',
    'action' => 'Action',
    'reason' => 'Raison',
    'retry_after' => 'Réessai',
    'error' => 'Erreur',
    'exception' => 'Exception',
    'path' => 'Chemin',
    'created' => 'Créé',
    'deleted_count' => 'Suppression',
    'storage' => 'Stockage',
    'mode' => 'Mode',
    'lang' => 'Langue',
    'filters' => 'Filtres',
    'job_code' => 'Job cron',
    'job_name' => 'Nom du job',
    'script_path' => 'Script',
    'schedule_expression' => 'Planification',
    'scheduled_at' => 'Planifié pour',
    'started_at' => 'Démarré',
    'finished_at' => 'Terminé',
    'exit_code' => 'Code retour',
    'duration_ms' => 'Durée',
    'jobs_checked' => 'Jobs vérifiés',
    'jobs_due' => 'Jobs dus',
    'jobs_executed' => 'Jobs exécutés',
    'now' => 'Date scheduler',
    'stdout_text' => 'Stdout',
    'stderr_text' => 'Stderr',
    'message' => 'Message',
    'dry_run' => 'Dry-run',
];
$priorityContextKeys = ['actor', 'identifier', 'ip', 'visitor_id', 'uri', 'query', 'method', 'referer', 'user_agent', 'page', 'template', 'slug', 'job_code', 'job_name', 'script_path', 'schedule_expression', 'scheduled_at', 'status', 'action', 'exit_code', 'duration_ms', 'jobs_checked', 'jobs_due', 'jobs_executed', 'now', 'message', 'reason', 'retry_after', 'error', 'exception', 'path', 'stdout_text', 'stderr_text', 'storage', 'mode', 'lang', 'deleted_count', 'created', 'filters'];
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$stringifyContextValue = static function (mixed $value): string {
    if (is_bool($value)) {
        return $value ? 'Oui' : 'Non';
    }

    if ($value === null) {
        return '';
    }

    if (is_scalar($value)) {
        return trim((string) $value);
    }

    if (is_array($value)) {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($encoded) ? $encoded : '';
    }

    if ($value instanceof \Stringable) {
        return trim((string) $value);
    }

    return '';
};
$renderFilterFields = static function (array $currentFilters) use ($escape): void {
    foreach ($currentFilters as $name => $value) {
        echo '<input type="hidden" name="filters[' . $escape((string) $name) . ']" value="' . $escape((string) $value) . '" />';
    }
};
$formatContextSummary = static function (array $context): string {
    if ($context === []) {
        return 'Sans contexte';
    }

    $parts = [];
    foreach (['actor', 'ip', 'page', 'slug', 'uri', 'identifier'] as $key) {
        if (!array_key_exists($key, $context)) {
            continue;
        }

        $value = $context[$key];
        if (is_scalar($value) && trim((string) $value) !== '') {
            $parts[] = $key . ' : ' . $value;
        }

        if (count($parts) >= 3) {
            break;
        }
    }

    return $parts === [] ? 'Voir le contexte' : implode(' · ', $parts);
};
$extractContextDetails = static function (array $context) use ($priorityContextKeys, $contextDetailLabels, $stringifyContextValue): array {
    if ($context === []) {
        return [];
    }

    $details = [];
    $seen = [];

    $orderedKeys = array_merge(
        $priorityContextKeys,
        array_values(array_filter(array_map('strval', array_keys($context)), static fn (string $key): bool => $key !== ''))
    );

    foreach ($orderedKeys as $key) {
        if (!array_key_exists($key, $context) || isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $value = $stringifyContextValue($context[$key]);
        if ($value === '') {
            continue;
        }

        $details[] = [
            'label' => $contextDetailLabels[$key] ?? ucfirst(str_replace('_', ' ', $key)),
            'value' => $value,
        ];
    }

    return $details;
};
$channelCounts = [
    'security' => 0,
    'content' => 0,
    'access' => 0,
];
$cronEntriesCount = 0;
$levelCounts = [
    'debug' => 0,
    'info' => 0,
    'warning' => 0,
    'error' => 0,
];
foreach ($entries as $entry) {
    $channel = strtolower(trim((string) ($entry['channel'] ?? '')));
    $level = strtolower(trim((string) ($entry['level'] ?? '')));

    if (array_key_exists($channel, $channelCounts)) {
        $channelCounts[$channel]++;
    }

    if (array_key_exists($level, $levelCounts)) {
        $levelCounts[$level]++;
    }

    if (str_starts_with((string) ($entry['event'] ?? ''), 'cron.')) {
        $cronEntriesCount++;
    }
}
?>

<section class="cards-grid dashboard-kpis">
  <article class="card dashboard-kpi-card">
    <span class="tag">Journal</span>
    <strong class="dashboard-kpi-value"><?php echo $filteredCount; ?></strong>
    <p class="dashboard-kpi-label">Journal SQL</p>
    <p class="dashboard-kpi-detail"><?php echo $totalCount; ?> entrée(s) stockée(s) dans le journal SQL.</p>
    <p class="dashboard-kpi-detail">Limite courante : <?php echo $limit; ?> ligne(s) par vue.</p>
  </article>

  <article class="card dashboard-kpi-card">
    <span class="tag">Synthèse</span>
    <strong class="dashboard-kpi-value"><?php echo (int) ($channelCounts['security'] ?? 0); ?></strong>
    <p class="dashboard-kpi-label">Lecture Rapide</p>
    <p class="dashboard-kpi-detail"><?php echo (int) ($channelCounts['content'] ?? 0); ?> entrée(s) contenu · <?php echo (int) ($channelCounts['access'] ?? 0); ?> entrée(s) visites.</p>
    <p class="dashboard-kpi-detail">Cron <?php echo (int) $cronEntriesCount; ?> · Debug <?php echo (int) ($levelCounts['debug'] ?? 0); ?> · Warning <?php echo (int) ($levelCounts['warning'] ?? 0); ?> · Erreur <?php echo (int) ($levelCounts['error'] ?? 0); ?></p>
  </article>

  <article class="card dashboard-kpi-card">
    <span class="tag">Nettoyage</span>
    <strong class="dashboard-kpi-value"><?php echo $hasActiveFilters ? 'OK' : '—'; ?></strong>
    <p class="dashboard-kpi-label">Purge filtrée</p>
    <p class="dashboard-kpi-detail">Suppression unitaire dans le tableau, ou purge en masse uniquement sur les résultats filtrés.</p>
    <div class="actions-inline dashboard-card-actions">
      <?php if ($hasActiveFilters): ?>
      <form method="post" action="<?php echo $escape((string) ($adminLogsUrl ?? admin_url('logs'))); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $escape((string) ($csrfToken ?? '')); ?>" />
        <input type="hidden" name="log_action" value="purge_filtered" />
        <?php $renderFilterFields($filters); ?>
        <button class="button-danger" type="submit">Supprimer les résultats filtrés</button>
      </form>
      <?php else: ?>
      <p class="notice-muted">Applique un filtre pour autoriser une purge en masse.</p>
      <?php endif; ?>
    </div>
  </article>
</section>

<?php if (($message ?? null) !== null): ?>
<div class="notice notice-success"><?php echo $escape((string) $message); ?></div>
<?php endif; ?>

<?php if (($error ?? null) !== null): ?>
<div class="notice notice-error"><?php echo $escape((string) $error); ?></div>
<?php endif; ?>

<?php if (!$storageAvailable && $storageMessage !== null): ?>
<div class="notice notice-error"><?php echo $escape($storageMessage); ?></div>
<?php endif; ?>

<section class="card admin-logs-filters-card">
  <h2>Filtres</h2>

  <form class="admin-form-grid admin-logs-filters-grid" method="get" action="<?php echo $escape((string) ($adminLogsUrl ?? admin_url('logs'))); ?>">
    <div class="field admin-logs-filters-search">
      <label for="logs-q">Recherche</label>
      <input id="logs-q" name="q" type="text" value="<?php echo $escape((string) ($filters['q'] ?? '')); ?>" placeholder="événement, acteur, URI, slug" />
    </div>

    <div class="field">
      <label for="logs-channel">Canal</label>
      <select id="logs-channel" name="channel">
        <option value="">Tous</option>
        <?php foreach ($availableChannels as $channel): ?>
        <option value="<?php echo $escape((string) $channel); ?>"<?php echo ($filters['channel'] ?? '') === $channel ? ' selected' : ''; ?>>
          <?php echo strtoupper($escape((string) $channel)); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="logs-level">Niveau</label>
      <select id="logs-level" name="level">
        <option value="">Tous</option>
        <?php foreach ($availableLevels as $level): ?>
        <option value="<?php echo $escape((string) $level); ?>"<?php echo ($filters['level'] ?? '') === $level ? ' selected' : ''; ?>>
          <?php echo $escape($levelLabels[(string) $level] ?? (string) $level); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="logs-date-from">Du</label>
      <input id="logs-date-from" name="date_from" type="date" value="<?php echo $escape((string) ($filters['date_from'] ?? '')); ?>" />
    </div>

    <div class="field">
      <label for="logs-date-to">Au</label>
      <input id="logs-date-to" name="date_to" type="date" value="<?php echo $escape((string) ($filters['date_to'] ?? '')); ?>" />
    </div>

    <div class="actions-inline admin-logs-filters-actions">
      <a class="button-link button-link-muted" href="<?php echo $escape((string) ($logsResetUrl ?? $adminLogsUrl ?? admin_url('logs'))); ?>">Réinitialiser</a>
      <a class="button-link button-link-muted" href="<?php echo $escape($cronLogsUrl); ?>">Logs cron</a>
      <button type="submit">Filtrer</button>
    </div>
  </form>
</section>

<section class="card">
  <h2>Entrées</h2>

  <form
    id="<?php echo $escape($bulkSelectionFormId); ?>"
    method="post"
    action="<?php echo $escape((string) ($adminLogsUrl ?? admin_url('logs'))); ?>"
    data-log-selection-root
  >
    <input type="hidden" name="csrf_token" value="<?php echo $escape((string) ($csrfToken ?? '')); ?>" />
    <input type="hidden" name="log_action" value="delete_selected" />
    <?php $renderFilterFields($filters); ?>

    <div class="log-selection-toolbar">
      <div class="log-selection-toolbar__meta">
        <label class="checkbox-field" for="logs-select-all-toolbar">
          <input id="logs-select-all-toolbar" type="checkbox" data-log-select-all />
          <span>Tout sélectionner</span>
        </label>
        <span class="log-selection-toolbar__count" data-log-selected-count>0 sélectionnée(s)</span>
      </div>

      <button class="button-danger" type="submit" data-log-delete-selected disabled>Supprimer la sélection</button>
    </div>
  </form>

  <?php if ($entries === []): ?>
  <p class="notice-muted">Aucune entrée ne correspond aux filtres courants.</p>
  <?php else: ?>
  <div class="table-shell">
    <table class="admin-table">
      <thead>
        <tr>
          <th class="admin-table-checkbox-cell">
            <input id="logs-select-all-head" type="checkbox" data-log-select-all aria-label="Sélectionner toutes les lignes visibles" />
          </th>
          <th>Date</th>
          <th>Canal</th>
          <th>Niveau</th>
          <th>Événement</th>
          <th>Contexte</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($entries as $entry): ?>
        <?php
        $context = is_array($entry['context'] ?? null) ? $entry['context'] : [];
        $contextDetails = $extractContextDetails($context);
        $createdAt = (string) ($entry['createdAt'] ?? '');
        $timestamp = $createdAt !== '' ? strtotime($createdAt) : false;
        $createdAtLabel = is_int($timestamp) ? date('d/m/Y H:i:s', $timestamp) : '—';
        $entryId = (int) ($entry['id'] ?? 0);
        $level = strtolower(trim((string) ($entry['level'] ?? '')));
        ?>
        <tr data-log-row>
          <td class="admin-table-checkbox-cell">
            <input
              type="checkbox"
              name="log_ids[]"
              value="<?php echo $entryId; ?>"
              form="<?php echo $escape($bulkSelectionFormId); ?>"
              data-log-select-row
              aria-label="Sélectionner l’entrée <?php echo $entryId; ?>"
            />
          </td>
          <td><time datetime="<?php echo $escape($createdAt); ?>"><?php echo $escape($createdAtLabel); ?></time></td>
          <td><span class="tag"><?php echo strtoupper($escape((string) ($entry['channel'] ?? ''))); ?></span></td>
          <td>
            <span class="log-level-pill log-level-pill--<?php echo $escape($level !== '' ? $level : 'info'); ?>">
              <?php echo $escape($levelLabels[(string) ($entry['level'] ?? '')] ?? (string) ($entry['level'] ?? '')); ?>
            </span>
          </td>
          <td>
            <div class="log-event-cell">
              <code><?php echo $escape((string) ($entry['event'] ?? '')); ?></code>
              <span class="notice-muted">Canal <?php echo strtoupper($escape((string) ($entry['channel'] ?? ''))); ?> · niveau <?php echo $escape($levelLabels[(string) ($entry['level'] ?? '')] ?? (string) ($entry['level'] ?? '')); ?></span>
            </div>
          </td>
          <td>
            <?php if ($context === []): ?>
            <span class="notice-muted">Sans contexte</span>
            <?php else: ?>
            <div class="log-detail-list">
              <?php foreach (array_slice($contextDetails, 0, 6) as $detail): ?>
              <div class="log-detail-item">
                <span class="log-detail-item__label"><?php echo $escape((string) ($detail['label'] ?? '')); ?></span>
                <span class="log-detail-item__value"><?php echo $escape((string) ($detail['value'] ?? '')); ?></span>
              </div>
              <?php endforeach; ?>
            </div>
            <details class="log-context-details">
              <summary><?php echo $escape($formatContextSummary($context)); ?> · JSON brut</summary>
              <pre class="log-context"><?php echo $escape((string) ($entry['contextJson'] ?? '')); ?></pre>
            </details>
            <?php endif; ?>
          </td>
          <td>
            <form method="post" action="<?php echo $escape((string) ($adminLogsUrl ?? admin_url('logs'))); ?>">
              <input type="hidden" name="csrf_token" value="<?php echo $escape((string) ($csrfToken ?? '')); ?>" />
              <input type="hidden" name="log_action" value="delete_selected" />
              <input type="hidden" name="log_ids[]" value="<?php echo $entryId; ?>" />
              <?php $renderFilterFields($filters); ?>
              <button class="button-danger button-small" type="submit">Supprimer</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>

<?php $cspNonce = (string) ($GLOBALS['csp_nonce'] ?? ''); ?>
<script<?php echo $cspNonce !== '' ? ' nonce="' . $escape($cspNonce) . '"' : ''; ?>>
  (() => {
    const root = document.querySelector('[data-log-selection-root]');
    if (!(root instanceof HTMLFormElement)) {
      return;
    }

    const rowCheckboxes = Array.from(document.querySelectorAll('[data-log-select-row]'));
    const selectAllCheckboxes = Array.from(document.querySelectorAll('[data-log-select-all]'));
    const deleteButton = root.querySelector('[data-log-delete-selected]');
    const countLabel = root.querySelector('[data-log-selected-count]');

    if (rowCheckboxes.length === 0 || !(deleteButton instanceof HTMLButtonElement) || !(countLabel instanceof HTMLElement)) {
      selectAllCheckboxes.forEach((checkbox) => {
        if (checkbox instanceof HTMLInputElement) {
          checkbox.disabled = true;
        }
      });
      return;
    }

    const syncRowState = () => {
      rowCheckboxes.forEach((checkbox) => {
        if (!(checkbox instanceof HTMLInputElement)) {
          return;
        }

        const row = checkbox.closest('[data-log-row]');
        if (!(row instanceof HTMLElement)) {
          return;
        }

        row.classList.toggle('is-selected', checkbox.checked);
      });
    };

    const syncControls = () => {
      const selectedCount = rowCheckboxes.filter((checkbox) => checkbox instanceof HTMLInputElement && checkbox.checked).length;
      const allSelected = selectedCount > 0 && selectedCount === rowCheckboxes.length;

      countLabel.textContent = `${selectedCount} selectionnée(s)`;
      deleteButton.disabled = selectedCount === 0;

      selectAllCheckboxes.forEach((checkbox) => {
        if (!(checkbox instanceof HTMLInputElement)) {
          return;
        }

        checkbox.checked = allSelected;
        checkbox.indeterminate = selectedCount > 0 && !allSelected;
      });

      syncRowState();
    };

    selectAllCheckboxes.forEach((checkbox) => {
      if (!(checkbox instanceof HTMLInputElement)) {
        return;
      }

      checkbox.addEventListener('change', () => {
        rowCheckboxes.forEach((rowCheckbox) => {
          if (rowCheckbox instanceof HTMLInputElement) {
            rowCheckbox.checked = checkbox.checked;
          }
        });

        syncControls();
      });
    });

    rowCheckboxes.forEach((checkbox) => {
      if (!(checkbox instanceof HTMLInputElement)) {
        return;
      }

      checkbox.addEventListener('change', syncControls);
    });

    syncControls();
  })();
</script>
