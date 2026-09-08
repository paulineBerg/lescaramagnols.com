<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Caramagnols\PrivateApps\FamilyDiscussion\Attachment\DiscussionAttachmentStorage;
use Caramagnols\PrivateApps\FamilyDiscussion\Repository\DiscussionRepository;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$options = parse_private_discussion_attachment_scan_options(array_slice($argv ?? [], 1));
if ($options['help']) {
    echo "Usage: php backend/core/tools/scan_private_discussion_attachments.php [--json] [--limit=N] [--verify-content]\n";
    echo "Controle les fichiers de pieces jointes actives des discussions privees.\n";
    echo "--verify-content dechiffre les fichiers pour verifier taille et SHA-256.\n";
    exit(0);
}

$startedAt = microtime(true);
$database = editorial_database();
$repository = new DiscussionRepository($database);
$repository->ensureSchema();
$storage = DiscussionAttachmentStorage::fromAppConfig();
$attachmentsTable = $database->table('discussion_message_attachments');

$statement = $database->pdo()->prepare(sprintf(
    "SELECT `attachment_id`, `storage_path`, `size_bytes`, `sha256`, `purge_status`
     FROM `%s`
     WHERE `purge_status` <> 'purged'
     ORDER BY `id` ASC
     LIMIT :limit",
    $attachmentsTable
));
$statement->bindValue(':limit', $options['limit'], PDO::PARAM_INT);
$statement->execute();
$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
$rows = is_array($rows) ? $rows : [];

$report = [
    'success' => true,
    'checked' => 0,
    'missing_files' => 0,
    'invalid_paths' => 0,
    'unreadable_files' => 0,
    'unencrypted_files' => 0,
    'size_mismatches' => 0,
    'hash_mismatches' => 0,
    'verify_content' => $options['verify-content'],
    'limit' => $options['limit'],
    'anomalies' => [],
    'generated_at' => date('c'),
];

foreach ($rows as $row) {
    $report['checked']++;
    $attachmentId = (string) ($row['attachment_id'] ?? '');
    $storagePath = (string) ($row['storage_path'] ?? '');
    $absolutePath = $storage->absolutePath($storagePath);

    if ($absolutePath === null) {
        add_private_discussion_attachment_scan_anomaly($report, 'invalid_path', $attachmentId);
        $report['invalid_paths']++;
        continue;
    }

    if (!is_file($absolutePath)) {
        add_private_discussion_attachment_scan_anomaly($report, 'missing_file', $attachmentId);
        $report['missing_files']++;
        continue;
    }

    if (!is_readable($absolutePath)) {
        add_private_discussion_attachment_scan_anomaly($report, 'unreadable_file', $attachmentId);
        $report['unreadable_files']++;
        continue;
    }

    if (!$storage->isEncryptedStoredFile($storagePath)) {
        add_private_discussion_attachment_scan_anomaly($report, 'unencrypted_file', $attachmentId);
        $report['unencrypted_files']++;
    }

    if (!$options['verify-content']) {
        continue;
    }

    $content = $storage->read($storagePath);
    if (!is_string($content)) {
        add_private_discussion_attachment_scan_anomaly($report, 'decrypt_failed', $attachmentId);
        $report['unreadable_files']++;
        continue;
    }

    if (strlen($content) !== (int) ($row['size_bytes'] ?? -1)) {
        add_private_discussion_attachment_scan_anomaly($report, 'size_mismatch', $attachmentId);
        $report['size_mismatches']++;
    }

    $expectedHash = strtolower(trim((string) ($row['sha256'] ?? '')));
    if ($expectedHash !== '' && hash('sha256', $content) !== $expectedHash) {
        add_private_discussion_attachment_scan_anomaly($report, 'hash_mismatch', $attachmentId);
        $report['hash_mismatches']++;
    }
}

$report['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
$report['success'] = count($report['anomalies']) === 0;

if ($options['json']) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($report['success'] ? 0 : 1);
}

printf("Pieces jointes controlees: %d\n", $report['checked']);
printf("Anomalies: %d\n", count($report['anomalies']));
printf("Verification contenu: %s\n", $options['verify-content'] ? 'oui' : 'non');
if ($report['anomalies'] !== []) {
    foreach ($report['anomalies'] as $anomaly) {
        printf("- %s: %s\n", (string) ($anomaly['type'] ?? 'unknown'), (string) ($anomaly['attachment_id'] ?? ''));
    }
}

exit($report['success'] ? 0 : 1);

/**
 * @param array<int, string> $arguments
 * @return array{json: bool, help: bool, verify-content: bool, limit: int}
 */
function parse_private_discussion_attachment_scan_options(array $arguments): array
{
    $options = [
        'json' => false,
        'help' => false,
        'verify-content' => false,
        'limit' => 500,
    ];

    foreach ($arguments as $argument) {
        $argument = trim($argument);
        if ($argument === '--json') {
            $options['json'] = true;
        } elseif ($argument === '--help' || $argument === '-h') {
            $options['help'] = true;
        } elseif ($argument === '--verify-content') {
            $options['verify-content'] = true;
        } elseif (str_starts_with($argument, '--limit=')) {
            $options['limit'] = max(1, min(5000, (int) substr($argument, strlen('--limit='))));
        }
    }

    return $options;
}

/**
 * @param array<string, mixed> $report
 */
function add_private_discussion_attachment_scan_anomaly(array &$report, string $type, string $attachmentId): void
{
    if (count($report['anomalies']) >= 25) {
        return;
    }

    $safeAttachmentId = preg_replace('/[^A-Za-z0-9._-]/', '', $attachmentId);
    $report['anomalies'][] = [
        'type' => $type,
        'attachment_id' => is_string($safeAttachmentId) ? substr($safeAttachmentId, 0, 64) : '',
    ];
}
