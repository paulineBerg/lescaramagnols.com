<?php
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

$privateModules = is_array($privateModules ?? null) ? $privateModules : [];
$privateUserIdentifier = is_string($privateUserIdentifier ?? null) ? (string) $privateUserIdentifier : '';
$privatePasswordForgotUrl = is_string($privatePasswordForgotUrl ?? null) ? (string) $privatePasswordForgotUrl : private_portal_url('password_forgot');
?>
<section>
  <p>
    <?php echo htmlspecialchars(
        $translate('TXT_PRIVATE_DASHBOARD_WELCOME', 'Bienvenue dans votre espace privé.'),
        ENT_QUOTES,
        'UTF-8'
    ); ?>
    <?php if ($privateUserIdentifier !== ''): ?>
      <strong><?php echo htmlspecialchars($privateUserIdentifier, ENT_QUOTES, 'UTF-8'); ?></strong>.
    <?php endif; ?>
  </p>

  <section class="card">
    <h2><?php echo htmlspecialchars($translate('TXT_PRIVATE_DASHBOARD_MODULES_TITLE', 'Modules disponibles'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <?php if ($privateModules !== []): ?>
      <ul>
        <?php foreach ($privateModules as $module): ?>
          <?php if (!is_string($module) || trim($module) === ''): ?>
            <?php continue; ?>
          <?php endif; ?>
          <li><?php echo htmlspecialchars($module, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="muted">
        <?php echo htmlspecialchars(
            $translate('TXT_PRIVATE_DASHBOARD_NO_MODULE', 'Aucun module actif pour l’instant.'),
            ENT_QUOTES,
            'UTF-8'
        ); ?>
      </p>
    <?php endif; ?>
  </section>

  <p class="muted">
    <a href="<?php echo htmlspecialchars($privatePasswordForgotUrl, ENT_QUOTES, 'UTF-8'); ?>">
      <?php echo htmlspecialchars($translate('TXT_PRIVATE_PASSWORD_FORGOT_LINK', 'Réinitialiser le mot de passe'), ENT_QUOTES, 'UTF-8'); ?>
    </a>
  </p>
</section>
