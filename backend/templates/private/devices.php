<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$translate = static function (string $key, string $fallback): string {
    if (!function_exists('t')) {
        return $fallback;
    }
    $translated = t($key);

    return is_string($translated) && $translated !== '' && $translated !== $key && $translated !== '[[' . $key . ']]'
        ? $translated
        : $fallback;
};
$devices = is_array($privateDevices ?? null) ? $privateDevices : [];
$csrf = is_string($privateDevicesCsrfToken ?? null) ? (string) $privateDevicesCsrfToken : '';
$notice = is_string($notice ?? null) ? (string) $notice : '';
$errorKey = is_string($errorKey ?? null) ? (string) $errorKey : '';
?>
<section>
  <h1><?php echo $escape($translate('TXT_PRIVATE_DEVICES_TITLE', 'Mes appareils')); ?></h1>

  <?php if ($notice !== ''): ?>
    <p class="notice"><?php echo $escape(str_starts_with($notice, 'devices_revoked:') ? $translate('TXT_PRIVATE_DEVICES_REVOKED_ALL', 'Appareils révoqués.') : $notice); ?></p>
  <?php endif; ?>
  <?php if ($errorKey !== ''): ?>
    <p class="notice notice-error"><?php echo $escape($translate('TXT_PRIVATE_DEVICES_ERROR', 'Action impossible sur cet appareil.')); ?></p>
  <?php endif; ?>

  <form method="post" action="<?php echo $escape(private_portal_url('member_devices')); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo $escape($csrf); ?>" />
    <input type="hidden" name="device_action" value="revoke_all" />
    <button type="submit"><?php echo $escape($translate('TXT_PRIVATE_DEVICES_REVOKE_ALL', 'Déconnecter tous mes appareils')); ?></button>
  </form>

  <?php if ($devices === []): ?>
    <p class="muted"><?php echo $escape($translate('TXT_PRIVATE_DEVICES_EMPTY', 'Aucun appareil de confiance enregistré.')); ?></p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th><?php echo $escape($translate('TXT_PRIVATE_DEVICE_NAME', 'Nom')); ?></th>
          <th><?php echo $escape($translate('TXT_PRIVATE_DEVICE_TYPE', 'Type')); ?></th>
          <th><?php echo $escape($translate('TXT_PRIVATE_DEVICE_SCOPES', 'Autorisations')); ?></th>
          <th><?php echo $escape($translate('TXT_PRIVATE_DEVICE_LAST_SEEN', 'Dernière activité')); ?></th>
          <th><?php echo $escape($translate('TXT_PRIVATE_DEVICE_EXPIRES', 'Expiration')); ?></th>
          <th><?php echo $escape($translate('TXT_PRIVATE_DEVICE_ACTIONS', 'Actions')); ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($devices as $device): ?>
        <tr>
          <td>
            <form method="post" action="<?php echo $escape(private_portal_url('member_devices')); ?>">
              <input type="hidden" name="csrf_token" value="<?php echo $escape($csrf); ?>" />
              <input type="hidden" name="device_action" value="rename" />
              <input type="hidden" name="device_id" value="<?php echo $escape($device['id'] ?? ''); ?>" />
              <input type="text" name="device_name" value="<?php echo $escape($device['name'] ?? ''); ?>" maxlength="120" />
              <button type="submit"><?php echo $escape($translate('TXT_PRIVATE_DEVICE_RENAME', 'Renommer')); ?></button>
            </form>
          </td>
          <td><?php echo $escape($device['device_type'] ?? ''); ?></td>
          <td><?php echo $escape(implode(', ', array_map('strval', is_array($device['active_scopes'] ?? null) ? $device['active_scopes'] : []))); ?></td>
          <td><?php echo $escape($device['last_seen_at'] ?? ''); ?></td>
          <td><?php echo $escape($device['trusted_until'] ?? ''); ?></td>
          <td>
            <form method="post" action="<?php echo $escape(private_portal_url('member_devices')); ?>">
              <input type="hidden" name="csrf_token" value="<?php echo $escape($csrf); ?>" />
              <input type="hidden" name="device_action" value="revoke" />
              <input type="hidden" name="device_id" value="<?php echo $escape($device['id'] ?? ''); ?>" />
              <button type="submit"><?php echo $escape($translate('TXT_PRIVATE_DEVICE_REVOKE', 'Révoquer')); ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
