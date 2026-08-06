<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$translate = static function (string $key, string $fallback): string {
    if (function_exists('admin_translate')) {
        return admin_translate($key, $fallback);
    }

    return $fallback;
};
$devices = is_array($trustedDevices ?? null) ? $trustedDevices : [];
?>
<h1><?php echo $escape($translate('TXT_ADMIN_SECURITY_DEVICES_TITLE', 'Appareils et sessions')); ?></h1>

<?php if (($message ?? null) !== null): ?>
<div class="notice notice-success" role="status"><?php echo $escape($message); ?></div>
<?php endif; ?>
<?php if (($error ?? null) !== null): ?>
<div class="notice notice-error" role="alert"><?php echo $escape($error); ?></div>
<?php endif; ?>

<section class="admin-section">
  <form method="post" action="<?php echo $escape($adminSecurityDevicesUrl ?? admin_url('security_devices')); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken ?? ''); ?>" />
    <input type="hidden" name="device_action" value="revoke_all" />
    <button class="button-danger" type="submit"><?php echo $escape($translate('TXT_ADMIN_SECURITY_DEVICES_REVOKE_ALL', 'Révoquer tous mes appareils Admin')); ?></button>
  </form>
</section>

<section class="admin-section">
  <?php if ($devices === []): ?>
    <p class="muted"><?php echo $escape($translate('TXT_ADMIN_SECURITY_DEVICES_EMPTY', 'Aucun appareil de confiance enregistré.')); ?></p>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th><?php echo $escape($translate('TXT_ADMIN_SECURITY_DEVICE_NAME', 'Nom')); ?></th>
          <th><?php echo $escape($translate('TXT_ADMIN_SECURITY_DEVICE_TYPE', 'Type')); ?></th>
          <th><?php echo $escape($translate('TXT_ADMIN_SECURITY_DEVICE_SCOPES', 'Scopes actifs')); ?></th>
          <th><?php echo $escape($translate('TXT_ADMIN_SECURITY_DEVICE_LAST_SEEN', 'Dernière activité')); ?></th>
          <th><?php echo $escape($translate('TXT_ADMIN_SECURITY_DEVICE_EXPIRES', 'Expiration')); ?></th>
          <th><?php echo $escape($translate('TXT_ADMIN_SECURITY_DEVICE_STATUS', 'Statut')); ?></th>
          <th><?php echo $escape($translate('TXT_ADMIN_SECURITY_DEVICE_ACTIONS', 'Actions')); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($devices as $device): ?>
        <tr>
          <td>
            <form method="post" action="<?php echo $escape($adminSecurityDevicesUrl ?? admin_url('security_devices')); ?>">
              <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken ?? ''); ?>" />
              <input type="hidden" name="device_action" value="rename" />
              <input type="hidden" name="device_id" value="<?php echo $escape($device['id'] ?? ''); ?>" />
              <input type="text" name="device_name" value="<?php echo $escape($device['name'] ?? ''); ?>" maxlength="120" />
              <button type="submit"><?php echo $escape($translate('TXT_ADMIN_SECURITY_DEVICE_RENAME', 'Renommer')); ?></button>
            </form>
          </td>
          <td><?php echo $escape($device['device_type'] ?? ''); ?></td>
          <td><?php echo $escape(implode(', ', array_map('strval', is_array($device['active_scopes'] ?? null) ? $device['active_scopes'] : []))); ?></td>
          <td><?php echo $escape($device['last_seen_at'] ?? ''); ?></td>
          <td><?php echo $escape($device['trusted_until'] ?? ''); ?></td>
          <td><?php echo ((string) ($device['revoked_at'] ?? '')) === '' ? $escape($translate('TXT_ADMIN_SECURITY_DEVICE_ACTIVE', 'Actif')) : $escape($translate('TXT_ADMIN_SECURITY_DEVICE_REVOKED', 'Révoqué')); ?></td>
          <td>
            <form method="post" action="<?php echo $escape($adminSecurityDevicesUrl ?? admin_url('security_devices')); ?>">
              <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken ?? ''); ?>" />
              <input type="hidden" name="device_action" value="revoke" />
              <input type="hidden" name="device_id" value="<?php echo $escape($device['id'] ?? ''); ?>" />
              <button class="button-danger" type="submit"><?php echo $escape($translate('TXT_ADMIN_SECURITY_DEVICE_REVOKE', 'Révoquer')); ?></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
