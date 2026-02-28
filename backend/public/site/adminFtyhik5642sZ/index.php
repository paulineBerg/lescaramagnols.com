<?php
// backend/public/site/adminFtyhik5642sZ/index.php

require_once dirname(__DIR__, 3) . '/core/bootstrap.php';
require_once dirname(__DIR__, 3) . '/core/auth/admin.php';

if (admin_is_authenticated()) {
    header('Location: dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!admin_validate_csrf_token($token)) {
        $error = 'Session expirée, merci de réessayer.';
    } else {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if (admin_login((string) $email, (string) $password)) {
            header('Location: dashboard.php');
            exit;
        }

        $error = 'Identifiants invalides.';
    }
}

$csrfToken = admin_csrf_token();
$loginPath = app_config('admin.login_path', 'adminFtyhik5642sZ');
$adminEmail = app_config('admin.email', '');
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Connexion Admin · Les Caramagnols</title>
    <style>
      :root {
        color-scheme: light dark;
      }

      body {
        margin: 0;
        min-height: 100vh;
        font-family: "Segoe UI", Tahoma, Arial, sans-serif;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #123d6b, #1d6f8d);
        color: #111;
      }

      .app {
        width: 100%;
        max-width: 420px;
        background: rgba(255, 255, 255, 0.92);
        border-radius: 18px;
        box-shadow: 0 18px 40px rgba(0, 0, 0, 0.25);
        padding: 2.5rem 2rem;
      }

      h1 {
        margin-top: 0;
        margin-bottom: 1.25rem;
        font-size: 1.75rem;
        text-align: center;
        color: #0d305e;
      }

      .intro {
        margin-bottom: 2rem;
        font-size: 0.95rem;
        color: #274b6d;
        text-align: center;
      }

      .field {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        margin-bottom: 1.4rem;
      }

      label {
        font-size: 0.9rem;
        font-weight: 600;
        color: #274b6d;
      }

      input[type="email"],
      input[type="password"] {
        border: 1px solid rgba(39, 75, 109, 0.25);
        border-radius: 10px;
        padding: 0.75rem 0.9rem;
        font-size: 1rem;
        transition: border 0.2s ease;
      }

      input[type="email"]:focus,
      input[type="password"]:focus {
        outline: none;
        border-color: #1d6f8d;
        box-shadow: 0 0 0 3px rgba(29, 111, 141, 0.2);
      }

      .actions {
        margin-top: 2rem;
      }

      button {
        width: 100%;
        background: linear-gradient(135deg, #1d6f8d, #24a0b5);
        color: #fff;
        border: none;
        border-radius: 12px;
        padding: 0.85rem;
        font-size: 1.05rem;
        font-weight: 600;
        cursor: pointer;
        transition: transform 0.15s ease, box-shadow 0.2s ease;
      }

      button:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 24px rgba(29, 111, 141, 0.3);
      }

      button:focus {
        outline: 3px solid rgba(29, 111, 141, 0.25);
      }

      .error {
        margin-top: -0.5rem;
        margin-bottom: 1.5rem;
        color: #a11a2a;
        background: rgba(161, 26, 42, 0.12);
        border-radius: 10px;
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
      }

      .hint {
        margin-top: 1.8rem;
        text-align: center;
        font-size: 0.8rem;
        color: rgba(39, 75, 109, 0.7);
      }
    </style>
  </head>
  <body>
    <main class="app" aria-labelledby="admin-login-title">
      <h1 id="admin-login-title">Espace admin</h1>
      <p class="intro">
        Connectez-vous pour accéder au tableau de bord du blog Les Caramagnols.
      </p>
      <?php if ($error !== null): ?>
      <div class="error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>
      <form method="post" autocomplete="off" novalidate>
        <div class="field">
          <label for="email">Adresse e-mail</label>
          <input
            id="email"
            name="email"
            type="email"
            inputmode="email"
            required
            value="<?php echo htmlspecialchars((string) ($adminEmail ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
            autofocus
          />
        </div>
        <div class="field">
          <label for="password">Mot de passe</label>
          <input id="password" name="password" type="password" required />
        </div>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
        <div class="actions">
          <button type="submit">Se connecter</button>
        </div>
      </form>
      <p class="hint">
        Dossier protégé : <code><?php echo htmlspecialchars((string) $loginPath, ENT_QUOTES, 'UTF-8'); ?></code>
      </p>
    </main>
  </body>
</html>
