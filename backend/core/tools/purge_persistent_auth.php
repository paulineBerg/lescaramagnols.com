<?php

declare(strict_types=1);

use Caramagnols\Identity\Repository\PersistentTokenRepository;
use Caramagnols\Identity\Repository\TrustedDeviceRepository;

require_once __DIR__ . '/../bootstrap.php';

$options = getopt('', ['dry-run', 'json']);
$dryRun = array_key_exists('dry-run', $options);
$json = array_key_exists('json', $options);

$lockPath = ROOT_PATH . '/var/locks/purge-persistent-auth.lock';
if (!is_dir(dirname($lockPath))) {
    @mkdir(dirname($lockPath), 0775, true);
}

$lock = fopen($lockPath, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    $payload = ['ok' => false, 'error' => 'lock_unavailable'];
    echo $json ? json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL : "lock_unavailable\n";
    exit(1);
}

$tokens = new PersistentTokenRepository(editorial_database());
$devices = new TrustedDeviceRepository(editorial_database());
$expiredRetention = (int) app_config('identity.persistent.expired_token_retention_seconds', 2592000);
$revokedRetention = (int) app_config('identity.persistent.revoked_token_retention_seconds', 7776000);

if ($dryRun) {
    $payload = [
        'ok' => true,
        'dry_run' => true,
        'expired_token_retention_seconds' => $expiredRetention,
        'revoked_token_retention_seconds' => $revokedRetention,
    ];
} else {
    $tokenResult = $tokens->purge($expiredRetention, $revokedRetention);
    $deletedDevices = $devices->purgeRevoked($revokedRetention);
    $payload = [
        'ok' => true,
        'dry_run' => false,
        'deleted_tokens' => (int) ($tokenResult['deleted_tokens'] ?? 0),
        'deleted_devices' => $deletedDevices,
    ];

    app_event_logger()->security('auth.persistent_maintenance.completed', $payload);
}

echo $json ? json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL : implode(PHP_EOL, array_map(
    static fn (string $key, mixed $value): string => $key . '=' . (is_bool($value) ? ($value ? 'true' : 'false') : (string) $value),
    array_keys($payload),
    $payload
)) . PHP_EOL;
