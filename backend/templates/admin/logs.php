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
$page = (int) ($logsView['page'] ?? 1);
$totalPages = (int) ($logsView['totalPages'] ?? 0);
$hasPreviousPage = (bool) ($logsView['hasPreviousPage'] ?? false);
$hasNextPage = (bool) ($logsView['hasNextPage'] ?? false);
$storageMessage = is_string($logsView['storageMessage'] ?? null) ? $logsView['storageMessage'] : null;
$cronLogsUrl = (string) ($adminLogsUrl ?? admin_url('logs')) . '?q=cron.';
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
$translateFormat = static function (string $key, string $fallback, mixed ...$args) use ($translate): string {
    return sprintf($translate($key, $fallback), ...$args);
};
$levelLabels = [
    'debug' => $translate('TXT_ADMIN_LOGS_LEVEL_DEBUG', 'Debug'),
    'info' => $translate('TXT_ADMIN_LOGS_LEVEL_INFO', 'Info'),
    'warning' => $translate('TXT_ADMIN_LOGS_LEVEL_WARNING', 'Warning'),
    'error' => $translate('TXT_ADMIN_LOGS_LEVEL_ERROR', 'Erreur'),
];
$bulkSelectionFormId = 'logs-bulk-selection-form';
$contextDetailLabels = [
    'actor' => $translate('TXT_ADMIN_LOGS_CONTEXT_ACTOR', 'Acteur'),
    'identifier' => $translate('TXT_ADMIN_LOGS_CONTEXT_IDENTIFIER', 'Identifiant'),
    'ip' => 'IP',
    'visitor_id' => $translate('TXT_ADMIN_LOGS_CONTEXT_VISITOR', 'Visiteur'),
    'uri' => 'URI',
    'query' => 'Query',
    'referer' => $translate('TXT_ADMIN_LOGS_CONTEXT_REFERER', 'Référent'),
    'user_agent' => 'User-Agent',
    'method' => $translate('TXT_ADMIN_LOGS_CONTEXT_METHOD', 'Méthode'),
    'page' => $translate('TXT_ADMIN_LOGS_CONTEXT_PAGE', 'Écran'),
    'template' => 'Template',
    'slug' => 'Slug',
    'status' => $translate('TXT_ADMIN_COMMON_STATUS', 'Statut'),
    'action' => $translate('TXT_ADMIN_COMMON_ACTION', 'Action'),
    'reason' => $translate('TXT_ADMIN_LOGS_CONTEXT_REASON', 'Raison'),
    'retry_after' => $translate('TXT_ADMIN_LOGS_CONTEXT_RETRY_AFTER', 'Réessai'),
    'error' => $translate('TXT_ADMIN_LOGS_LEVEL_ERROR', 'Erreur'),
    'exception' => $translate('TXT_ADMIN_LOGS_CONTEXT_EXCEPTION', 'Exception'),
    'path' => $translate('TXT_ADMIN_LOGS_CONTEXT_PATH', 'Chemin'),
    'created' => $translate('TXT_ADMIN_LOGS_CONTEXT_CREATED', 'Créé'),
    'deleted_count' => $translate('TXT_ADMIN_LOGS_CONTEXT_DELETED_COUNT', 'Suppression'),
    'storage' => $translate('TXT_ADMIN_LOGS_CONTEXT_STORAGE', 'Stockage'),
    'mode' => 'Mode',
    'lang' => $translate('TXT_ADMIN_COMMON_LANGUAGE', 'Langue'),
    'filters' => $translate('TXT_ADMIN_COMMON_FILTERS', 'Filtres'),
    'job_code' => $translate('TXT_ADMIN_LOGS_CONTEXT_JOB_CODE', 'Job cron'),
    'job_name' => $translate('TXT_ADMIN_LOGS_CONTEXT_JOB_NAME', 'Nom du job'),
    'script_path' => $translate('TXT_ADMIN_LOGS_CONTEXT_SCRIPT_PATH', 'Script'),
    'schedule_expression' => $translate('TXT_ADMIN_LOGS_CONTEXT_SCHEDULE', 'Planification'),
    'scheduled_at' => $translate('TXT_ADMIN_LOGS_CONTEXT_SCHEDULED_AT', 'Planifié pour'),
    'started_at' => $translate('TXT_ADMIN_LOGS_CONTEXT_STARTED_AT', 'Démarré'),
    'finished_at' => $translate('TXT_ADMIN_LOGS_CONTEXT_FINISHED_AT', 'Terminé'),
    'exit_code' => $translate('TXT_ADMIN_LOGS_CONTEXT_EXIT_CODE', 'Code retour'),
    'duration_ms' => $translate('TXT_ADMIN_LOGS_CONTEXT_DURATION', 'Durée'),
    'jobs_checked' => $translate('TXT_ADMIN_LOGS_CONTEXT_JOBS_CHECKED', 'Jobs vérifiés'),
    'jobs_due' => $translate('TXT_ADMIN_LOGS_CONTEXT_JOBS_DUE', 'Jobs dus'),
    'jobs_executed' => $translate('TXT_ADMIN_LOGS_CONTEXT_JOBS_EXECUTED', 'Jobs exécutés'),
    'now' => $translate('TXT_ADMIN_LOGS_CONTEXT_NOW', 'Date scheduler'),
    'stdout_text' => 'Stdout',
    'stderr_text' => 'Stderr',
    'message' => $translate('TXT_ADMIN_COMMON_MESSAGE', 'Message'),
    'dry_run' => 'Dry-run',
];
$priorityContextKeys = ['actor', 'identifier', 'ip', 'visitor_id', 'uri', 'query', 'method', 'referer', 'user_agent', 'page', 'template', 'slug', 'job_code', 'job_name', 'script_path', 'schedule_expression', 'scheduled_at', 'status', 'action', 'exit_code', 'duration_ms', 'jobs_checked', 'jobs_due', 'jobs_executed', 'now', 'message', 'reason', 'retry_after', 'error', 'exception', 'path', 'stdout_text', 'stderr_text', 'storage', 'mode', 'lang', 'deleted_count', 'created', 'filters'];
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$stringifyContextValue = static function (mixed $value) use ($translate): string {
    if (is_bool($value)) {
        return $value
            ? $translate('TXT_ADMIN_LAYOUT_YES', 'Oui')
            : $translate('TXT_ADMIN_LAYOUT_NO', 'Non');
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

$buildFilterQuery = static function (array $filters) use ($escape): string {
    $filtered = array_filter($filters, static fn ($v) => $v !== '');
    if ($filtered === []) {
        return '';
    }
    $query = http_build_query($filtered);
    return $query !== '' ? '&' . $query : '';
};
$formatContextSummary = static function (array $context) use ($translate): string {
    if ($context === []) {
        return $translate('TXT_ADMIN_LOGS_NO_CONTEXT', 'Sans contexte');
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

    return $parts === []
        ? $translate('TXT_ADMIN_LOGS_VIEW_CONTEXT', 'Voir le contexte')
        : implode(' · ', $parts);
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
$buildLogDetailPayload = static function (array $entry, array $contextDetails, string $createdAtLabel) use ($levelLabels): array {
    $level = (string) ($entry['level'] ?? '');
    $metadata = [];
    foreach (
        [
            'stream' => 'Stream',
            'application' => 'Application',
            'module' => 'Module',
            'requestId' => 'Request ID',
            'correlationId' => 'Correlation ID',
            'errorFingerprint' => 'Fingerprint',
        ] as $key => $label
    ) {
        $value = trim((string) ($entry[$key] ?? ''));
        if ($value === '') {
            continue;
        }

        $metadata[] = [
            'label' => $label,
            'value' => $value,
        ];
    }

    return [
        'id' => (int) ($entry['id'] ?? 0),
        'createdAt' => $createdAtLabel,
        'channel' => strtoupper((string) ($entry['channel'] ?? '')),
        'level' => $levelLabels[$level] ?? $level,
        'event' => (string) ($entry['event'] ?? ''),
        'metadata' => $metadata,
        'contextDetails' => $contextDetails,
        'contextJson' => (string) ($entry['contextJson'] ?? ''),
    ];
};
$encodeLogDetailPayload = static function (array $payload) use ($escape): string {
    $encoded = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );

    return $escape(is_string($encoded) ? $encoded : '{}');
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
    <span class="tag"><?php echo $escape($translate('TXT_ADMIN_LOGS_JOURNAL_TAG', 'Journal')); ?></span>
    <strong class="dashboard-kpi-value"><?php echo $filteredCount; ?></strong>
    <p class="dashboard-kpi-label"><?php echo $escape($translate('TXT_ADMIN_LOGS_SQL_LABEL', 'Journal SQL')); ?></p>
    <p class="dashboard-kpi-detail"><?php echo $escape($translateFormat('TXT_ADMIN_LOGS_SQL_DETAIL', '%d entrée(s) stockée(s) dans le journal SQL.', $totalCount)); ?></p>
    <p class="dashboard-kpi-detail"><?php echo $escape($translateFormat('TXT_ADMIN_LOGS_VIEW_LIMIT_DETAIL', 'Limite courante : %d ligne(s) par vue.', $limit)); ?></p>
  </article>

  <article class="card dashboard-kpi-card">
    <span class="tag"><?php echo $escape($translate('TXT_ADMIN_LOGS_SUMMARY_TAG', 'Synthèse')); ?></span>
    <strong class="dashboard-kpi-value"><?php echo (int) ($channelCounts['security'] ?? 0); ?></strong>
    <p class="dashboard-kpi-label"><?php echo $escape($translate('TXT_ADMIN_LOGS_QUICK_READ_LABEL', 'Lecture rapide')); ?></p>
    <p class="dashboard-kpi-detail">
      <?php echo $escape($translateFormat(
          'TXT_ADMIN_LOGS_CONTENT_VISITS_DETAIL',
          '%d entrée(s) contenu · %d entrée(s) visites.',
          (int) ($channelCounts['content'] ?? 0),
          (int) ($channelCounts['access'] ?? 0)
      )); ?>
    </p>
    <p class="dashboard-kpi-detail">
      <?php echo $escape($translateFormat(
          'TXT_ADMIN_LOGS_COUNTERS_DETAIL',
          'Cron %d · Debug %d · Warning %d · Erreur %d',
          (int) $cronEntriesCount,
          (int) ($levelCounts['debug'] ?? 0),
          (int) ($levelCounts['warning'] ?? 0),
          (int) ($levelCounts['error'] ?? 0)
      )); ?>
    </p>
  </article>

  <article class="card dashboard-kpi-card">
    <span class="tag"><?php echo $escape($translate('TXT_ADMIN_LOGS_CLEANUP_TAG', 'Nettoyage')); ?></span>
    <strong class="dashboard-kpi-value"><?php echo $hasActiveFilters ? 'OK' : '—'; ?></strong>
    <p class="dashboard-kpi-label"><?php echo $escape($translate('TXT_ADMIN_LOGS_FILTERED_PURGE_LABEL', 'Purge filtrée')); ?></p>
    <p class="dashboard-kpi-detail"><?php echo $escape($translate('TXT_ADMIN_LOGS_FILTERED_PURGE_DETAIL', 'Suppression unitaire dans le tableau, ou purge en masse uniquement sur les résultats filtrés.')); ?></p>
    <div class="actions-inline dashboard-card-actions">
      <?php if ($hasActiveFilters): ?>
      <form method="post" action="<?php echo $escape((string) ($adminLogsUrl ?? admin_url('logs'))); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $escape((string) ($csrfToken ?? '')); ?>" />
        <input type="hidden" name="log_action" value="purge_filtered" />
        <input type="hidden" name="page" value="<?php echo $escape((string) $page); ?>" />
        <?php $renderFilterFields($filters); ?>
        <button class="button-danger" type="submit"><?php echo $escape($translate('TXT_ADMIN_LOGS_DELETE_FILTERED_RESULTS', 'Supprimer les résultats filtrés')); ?></button>
      </form>
      <?php else: ?>
      <p class="notice-muted"><?php echo $escape($translate('TXT_ADMIN_LOGS_PURGE_FILTER_FIRST', 'Applique un filtre pour autoriser une purge en masse.')); ?></p>
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
  <h2><?php echo $escape($translate('TXT_ADMIN_COMMON_FILTERS', 'Filtres')); ?></h2>

  <form class="admin-form-grid admin-logs-filters-grid" method="get" action="<?php echo $escape((string) ($adminLogsUrl ?? admin_url('logs'))); ?>">
    <input type="hidden" name="page" value="1" />
    <div class="field admin-logs-filters-search">
      <label for="logs-q"><?php echo $escape($translate('TXT_ADMIN_COMMON_SEARCH', 'Recherche')); ?></label>
      <input id="logs-q" name="q" type="text" value="<?php echo $escape((string) ($filters['q'] ?? '')); ?>" placeholder="<?php echo $escape($translate('TXT_ADMIN_LOGS_SEARCH_PLACEHOLDER', 'événement, acteur, URI, slug')); ?>" />
    </div>

    <div class="field">
      <label for="logs-channel"><?php echo $escape($translate('TXT_ADMIN_LOGS_CHANNEL_LABEL', 'Canal')); ?></label>
      <select id="logs-channel" name="channel">
        <option value=""><?php echo $escape($translate('TXT_ADMIN_COMMON_ALL', 'Tous')); ?></option>
        <?php foreach ($availableChannels as $channel): ?>
        <option value="<?php echo $escape((string) $channel); ?>"<?php echo ($filters['channel'] ?? '') === $channel ? ' selected' : ''; ?>>
          <?php echo strtoupper($escape((string) $channel)); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="logs-level"><?php echo $escape($translate('TXT_ADMIN_LOGS_LEVEL_LABEL', 'Niveau')); ?></label>
      <select id="logs-level" name="level">
        <option value=""><?php echo $escape($translate('TXT_ADMIN_COMMON_ALL', 'Tous')); ?></option>
        <?php foreach ($availableLevels as $level): ?>
        <option value="<?php echo $escape((string) $level); ?>"<?php echo ($filters['level'] ?? '') === $level ? ' selected' : ''; ?>>
          <?php echo $escape($levelLabels[(string) $level] ?? (string) $level); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="logs-date-from"><?php echo $escape($translate('TXT_ADMIN_LOGS_DATE_FROM_LABEL', 'Du')); ?></label>
      <input id="logs-date-from" name="date_from" type="date" value="<?php echo $escape((string) ($filters['date_from'] ?? '')); ?>" />
    </div>

    <div class="field">
      <label for="logs-date-to"><?php echo $escape($translate('TXT_ADMIN_LOGS_DATE_TO_LABEL', 'Au')); ?></label>
      <input id="logs-date-to" name="date_to" type="date" value="<?php echo $escape((string) ($filters['date_to'] ?? '')); ?>" />
    </div>

    <div class="actions-inline admin-logs-filters-actions">
      <a class="button-link button-link-muted" href="<?php echo $escape((string) ($logsResetUrl ?? $adminLogsUrl ?? admin_url('logs'))); ?>"><?php echo $escape($translate('TXT_ADMIN_COMMON_RESET', 'Réinitialiser')); ?></a>
      <a class="button-link button-link-muted" href="<?php echo $escape($cronLogsUrl); ?>"><?php echo $escape($translate('TXT_ADMIN_LOGS_CRON_LINK', 'Logs cron')); ?></a>
      <button type="submit"><?php echo $escape($translate('TXT_ADMIN_COMMON_FILTER', 'Filtrer')); ?></button>
    </div>
  </form>
</section>

<section class="card">
  <h2><?php echo $escape($translate('TXT_ADMIN_LOGS_ENTRIES_TITLE', 'Entrées')); ?></h2>

  <form
    id="<?php echo $escape($bulkSelectionFormId); ?>"
    method="post"
    action="<?php echo $escape((string) ($adminLogsUrl ?? admin_url('logs'))); ?>"
    data-log-selection-root
  >
    <input type="hidden" name="csrf_token" value="<?php echo $escape((string) ($csrfToken ?? '')); ?>" />
    <input type="hidden" name="log_action" value="delete_selected" />
    <input type="hidden" name="page" value="<?php echo $escape((string) $page); ?>" />
    <?php $renderFilterFields($filters); ?>

    <div class="log-selection-toolbar">
      <div class="log-selection-toolbar__meta">
        <label class="checkbox-field" for="logs-select-all-toolbar">
          <input id="logs-select-all-toolbar" type="checkbox" data-log-select-all />
          <span><?php echo $escape($translate('TXT_ADMIN_LOGS_SELECT_ALL', 'Tout sélectionner')); ?></span>
        </label>
        <span class="log-selection-toolbar__count" data-log-selected-count><?php echo $escape($translateFormat('TXT_ADMIN_LOGS_SELECTED_COUNT', '%d sélectionnée(s)', 0)); ?></span>
      </div>

      <button class="button-danger" type="submit" data-log-delete-selected disabled><?php echo $escape($translate('TXT_ADMIN_LOGS_DELETE_SELECTION', 'Supprimer la sélection')); ?></button>
    </div>
  </form>

  <?php if ($entries === []): ?>
  <p class="notice-muted"><?php echo $escape($translate('TXT_ADMIN_LOGS_NO_RESULTS', 'Aucune entrée ne correspond aux filtres courants.')); ?></p>
  <?php else: ?>
  <div class="admin-logs-list">
    <div class="admin-logs-list-head">
      <label class="checkbox-field" for="logs-select-all-head">
        <input id="logs-select-all-head" type="checkbox" data-log-select-all aria-label="<?php echo $escape($translate('TXT_ADMIN_LOGS_SELECT_VISIBLE_ARIA', 'Sélectionner toutes les lignes visibles')); ?>" />
        <span><?php echo $escape($translate('TXT_ADMIN_LOGS_VISIBLE_ENTRIES', 'Entrées visibles')); ?></span>
      </label>
      <span><?php echo $escape($translate('TXT_ADMIN_LOGS_DOUBLE_CLICK_HINT', 'Double-clic sur une entrée pour ouvrir le log complet.')); ?></span>
    </div>

    <?php foreach ($entries as $entry): ?>
    <?php
    $context = is_array($entry['context'] ?? null) ? $entry['context'] : [];
    $contextDetails = $extractContextDetails($context);
    $createdAt = (string) ($entry['createdAt'] ?? '');
    $timestamp = $createdAt !== '' ? strtotime($createdAt) : false;
    $createdAtLabel = is_int($timestamp) ? date('d/m/Y H:i:s', $timestamp) : '—';
    $entryId = (int) ($entry['id'] ?? 0);
    $level = strtolower(trim((string) ($entry['level'] ?? '')));
    $levelLabel = $levelLabels[(string) ($entry['level'] ?? '')] ?? (string) ($entry['level'] ?? '');
    $payload = $buildLogDetailPayload($entry, $contextDetails, $createdAtLabel);
    $metadata = array_filter(
        [
            'stream' => trim((string) ($entry['stream'] ?? '')),
            'application' => trim((string) ($entry['application'] ?? '')),
            'module' => trim((string) ($entry['module'] ?? '')),
            'request' => trim((string) ($entry['requestId'] ?? '')),
            'correlation' => trim((string) ($entry['correlationId'] ?? '')),
            'fingerprint' => trim((string) ($entry['errorFingerprint'] ?? '')),
        ],
        static fn (string $value): bool => $value !== ''
    );
    ?>
    <article class="admin-log-entry" data-log-row data-log-detail="<?php echo $encodeLogDetailPayload($payload); ?>" tabindex="0">
      <div class="admin-log-entry__select">
        <input
          type="checkbox"
          name="log_ids[]"
          value="<?php echo $entryId; ?>"
          form="<?php echo $escape($bulkSelectionFormId); ?>"
          data-log-select-row
          aria-label="<?php echo $escape($translateFormat('TXT_ADMIN_LOGS_SELECT_ENTRY_ARIA', 'Sélectionner l’entrée %d', $entryId)); ?>"
        />
      </div>

      <div class="admin-log-entry__main">
        <div class="admin-log-entry__header">
          <div class="admin-log-entry__title">
            <code><?php echo $escape((string) ($entry['event'] ?? '')); ?></code>
            <time datetime="<?php echo $escape($createdAt); ?>"><?php echo $escape($createdAtLabel); ?></time>
          </div>
          <div class="admin-log-entry__badges">
            <span class="tag"><?php echo strtoupper($escape((string) ($entry['channel'] ?? ''))); ?></span>
            <span class="log-level-pill log-level-pill--<?php echo $escape($level !== '' ? $level : 'info'); ?>"><?php echo $escape($levelLabel); ?></span>
          </div>
        </div>

        <?php if ($metadata !== []): ?>
        <div class="admin-log-entry__metadata" aria-label="<?php echo $escape($translate('TXT_ADMIN_LOGS_TECHNICAL_DETAILS', 'Détails techniques')); ?>">
          <?php foreach (array_slice($metadata, 0, 4, true) as $metadataKey => $metadataValue): ?>
          <span><strong><?php echo $escape((string) $metadataKey); ?></strong> <?php echo $escape((string) $metadataValue); ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($contextDetails === []): ?>
        <p class="notice-muted admin-log-entry__empty-context"><?php echo $escape($translate('TXT_ADMIN_LOGS_NO_CONTEXT', 'Sans contexte')); ?></p>
        <?php else: ?>
        <dl class="admin-log-entry__details">
          <?php foreach (array_slice($contextDetails, 0, 6) as $detail): ?>
          <div>
            <dt><?php echo $escape((string) ($detail['label'] ?? '')); ?></dt>
            <dd><?php echo $escape((string) ($detail['value'] ?? '')); ?></dd>
          </div>
          <?php endforeach; ?>
        </dl>
        <?php endif; ?>
      </div>

      <div class="admin-log-entry__actions">
        <button class="button-small button-muted" type="button" data-log-open><?php echo $escape($translate('TXT_ADMIN_LOGS_OPEN_FULL_LOG', 'Détail')); ?></button>
        <form method="post" action="<?php echo $escape((string) ($adminLogsUrl ?? admin_url('logs'))); ?>">
          <input type="hidden" name="csrf_token" value="<?php echo $escape((string) ($csrfToken ?? '')); ?>" />
          <input type="hidden" name="log_action" value="delete_selected" />
          <input type="hidden" name="log_ids[]" value="<?php echo $entryId; ?>" />
          <input type="hidden" name="page" value="<?php echo $escape((string) $page); ?>" />
          <?php $renderFilterFields($filters); ?>
          <button class="button-danger button-small" type="submit"><?php echo $escape($translate('TXT_ADMIN_COMMON_DELETE', 'Supprimer')); ?></button>
        </form>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($totalPages > 1): ?>
  <nav class="pagination" aria-label="<?php echo $escape($translate('TXT_ADMIN_COMMON_PAGINATION', 'Pagination')); ?>">
    <ul class="pagination-list">
      <?php if ($hasPreviousPage): ?>
      <li class="pagination-item">
        <a
          class="pagination-link pagination-link--previous"
          href="<?php echo $escape((string) ($adminLogsUrl ?? admin_url('logs')) . '?page=' . max(1, $page - 1) . $buildFilterQuery($filters)); ?>"
          aria-label="<?php echo $escape($translate('TXT_ADMIN_COMMON_PREVIOUS_PAGE', 'Page précédente')); ?>"
        >
          <?php echo $escape($translate('TXT_ADMIN_COMMON_PREVIOUS', 'Précédent')); ?>
        </a>
      </li>
      <?php endif; ?>

      <?php
      $maxVisiblePages = 5;
      $halfVisible = (int) floor($maxVisiblePages / 2);
      $startPage = max(1, min($page - $halfVisible, $totalPages - $maxVisiblePages + 1));
      $endPage = min($totalPages, $startPage + $maxVisiblePages - 1);

      if ($startPage > 1) {
          echo '<li class="pagination-item"><a class="pagination-link" href="' . $escape((string) ($adminLogsUrl ?? admin_url('logs')) . '?page=1' . $buildFilterQuery($filters)) . '">1</a></li>';
          if ($startPage > 2) {
              echo '<li class="pagination-item pagination-item--ellipsis"><span>...</span></li>';
          }
      }

      for ($p = $startPage; $p <= $endPage; $p++):
      ?>
      <li class="pagination-item <?php echo $p === $page ? 'pagination-item--active' : ''; ?>">
        <a
          class="pagination-link <?php echo $p === $page ? 'pagination-link--active' : ''; ?>"
          href="<?php echo $escape((string) ($adminLogsUrl ?? admin_url('logs')) . '?page=' . $p . $buildFilterQuery($filters)); ?>"
          aria-current="<?php echo $p === $page ? 'page' : ''; ?>"
        >
          <?php echo $p; ?>
        </a>
      </li>
      <?php endfor; ?>

      <?php
      if ($endPage < $totalPages) {
          if ($endPage < $totalPages - 1) {
              echo '<li class="pagination-item pagination-item--ellipsis"><span>...</span></li>';
          }
          echo '<li class="pagination-item"><a class="pagination-link" href="' . $escape((string) ($adminLogsUrl ?? admin_url('logs')) . '?page=' . $totalPages . $buildFilterQuery($filters)) . '">' . $totalPages . '</a></li>';
      }
      ?>

      <?php if ($hasNextPage): ?>
      <li class="pagination-item">
        <a
          class="pagination-link pagination-link--next"
          href="<?php echo $escape((string) ($adminLogsUrl ?? admin_url('logs')) . '?page=' . ($page + 1) . $buildFilterQuery($filters)); ?>"
          aria-label="<?php echo $escape($translate('TXT_ADMIN_COMMON_NEXT_PAGE', 'Page suivante')); ?>"
        >
          <?php echo $escape($translate('TXT_ADMIN_COMMON_NEXT', 'Suivant')); ?>
        </a>
      </li>
      <?php endif; ?>
    </ul>
  </nav>
  <p class="pagination-info notice-muted">
    <?php echo $escape($translateFormat(
        'TXT_ADMIN_COMMON_PAGINATION_INFO',
        'Page %d sur %d (%d résultats)',
        $page,
        $totalPages,
        $filteredCount
    )); ?>
  </p>
  <?php endif; ?>
</section>

<dialog class="admin-log-dialog" data-log-dialog aria-labelledby="admin-log-dialog-title">
  <div class="admin-log-dialog__header">
    <div>
      <p class="tag" data-log-dialog-channel></p>
      <h2 id="admin-log-dialog-title" data-log-dialog-event><?php echo $escape($translate('TXT_ADMIN_LOGS_FULL_LOG_TITLE', 'Log complet')); ?></h2>
      <p class="notice-muted" data-log-dialog-meta></p>
    </div>
    <button class="button-small button-muted" type="button" data-log-dialog-close aria-label="<?php echo $escape($translate('TXT_ADMIN_COMMON_CLOSE', 'Fermer')); ?>">×</button>
  </div>
  <div class="admin-log-dialog__body">
    <section>
      <h3><?php echo $escape($translate('TXT_ADMIN_LOGS_TECHNICAL_DETAILS', 'Détails techniques')); ?></h3>
      <dl class="admin-log-dialog__list" data-log-dialog-metadata></dl>
    </section>
    <section>
      <h3><?php echo $escape($translate('TXT_ADMIN_LOGS_CONTEXT_LABEL', 'Contexte')); ?></h3>
      <dl class="admin-log-dialog__list" data-log-dialog-context></dl>
      <pre class="log-context admin-log-dialog__json" data-log-dialog-json></pre>
    </section>
  </div>
</dialog>

<?php $cspNonce = (string) ($GLOBALS['csp_nonce'] ?? ''); ?>
<script<?php echo $cspNonce !== '' ? ' nonce="' . $escape($cspNonce) . '"' : ''; ?>>
  (() => {
    const selectedCountTemplate = <?php echo json_encode($translate('TXT_ADMIN_LOGS_SELECTED_COUNT', '%d sélectionnée(s)'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const fullLogTitle = <?php echo json_encode($translate('TXT_ADMIN_LOGS_FULL_LOG_TITLE', 'Log complet'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const emptyContextLabel = <?php echo json_encode($translate('TXT_ADMIN_LOGS_NO_CONTEXT', 'Sans contexte'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const dialog = document.querySelector('[data-log-dialog]');
    const dialogEvent = document.querySelector('[data-log-dialog-event]');
    const dialogChannel = document.querySelector('[data-log-dialog-channel]');
    const dialogMeta = document.querySelector('[data-log-dialog-meta]');
    const dialogMetadata = document.querySelector('[data-log-dialog-metadata]');
    const dialogContext = document.querySelector('[data-log-dialog-context]');
    const dialogJson = document.querySelector('[data-log-dialog-json]');
    const dialogCloseButton = document.querySelector('[data-log-dialog-close]');

    const appendDetails = (target, details, emptyLabel) => {
      if (!(target instanceof HTMLElement)) {
        return;
      }

      target.textContent = '';

      if (!Array.isArray(details) || details.length === 0) {
        const wrapper = document.createElement('div');
        const term = document.createElement('dt');
        const definition = document.createElement('dd');
        term.textContent = emptyLabel;
        definition.textContent = '';
        wrapper.append(term, definition);
        target.append(wrapper);
        return;
      }

      details.forEach((detail) => {
        const wrapper = document.createElement('div');
        const term = document.createElement('dt');
        const definition = document.createElement('dd');
        term.textContent = typeof detail.label === 'string' ? detail.label : '';
        definition.textContent = typeof detail.value === 'string' ? detail.value : '';
        wrapper.append(term, definition);
        target.append(wrapper);
      });
    };

    const openLogDialog = (entry) => {
      if (!(dialog instanceof HTMLDialogElement)) {
        return;
      }

      let payload = {};
      try {
        payload = JSON.parse(entry.dataset.logDetail || '{}');
      } catch (error) {
        payload = {};
      }

      if (dialogEvent instanceof HTMLElement) {
        dialogEvent.textContent = typeof payload.event === 'string' && payload.event !== '' ? payload.event : fullLogTitle;
      }

      if (dialogChannel instanceof HTMLElement) {
        const channel = typeof payload.channel === 'string' ? payload.channel : '';
        const level = typeof payload.level === 'string' ? payload.level : '';
        dialogChannel.textContent = [channel, level].filter(Boolean).join(' · ');
      }

      if (dialogMeta instanceof HTMLElement) {
        const id = Number.isFinite(Number(payload.id)) && Number(payload.id) > 0 ? `#${payload.id}` : '';
        const createdAt = typeof payload.createdAt === 'string' ? payload.createdAt : '';
        dialogMeta.textContent = [id, createdAt].filter(Boolean).join(' · ');
      }

      appendDetails(dialogMetadata, payload.metadata, emptyContextLabel);
      appendDetails(dialogContext, payload.contextDetails, emptyContextLabel);

      if (dialogJson instanceof HTMLElement) {
        dialogJson.textContent = typeof payload.contextJson === 'string' && payload.contextJson !== ''
          ? payload.contextJson
          : '{}';
      }

      if (!dialog.open) {
        dialog.showModal();
      }
    };

    document.querySelectorAll('[data-log-detail]').forEach((entry) => {
      if (!(entry instanceof HTMLElement)) {
        return;
      }

      entry.addEventListener('dblclick', (event) => {
        if (event.target instanceof Element && event.target.closest('button, input, a, form')) {
          return;
        }

        openLogDialog(entry);
      });

      entry.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
          return;
        }

        if (event.target instanceof Element && event.target.closest('button, input, a, form')) {
          return;
        }

        openLogDialog(entry);
      });

      const openButton = entry.querySelector('[data-log-open]');
      if (openButton instanceof HTMLButtonElement) {
        openButton.addEventListener('click', () => openLogDialog(entry));
      }
    });

    if (dialogCloseButton instanceof HTMLButtonElement && dialog instanceof HTMLDialogElement) {
      dialogCloseButton.addEventListener('click', () => dialog.close());
      dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
          dialog.close();
        }
      });
    }

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

      countLabel.textContent = selectedCountTemplate.replace('%d', String(selectedCount));
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
