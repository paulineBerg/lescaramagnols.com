
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $pageTitle ?? 'Les Caramagnols : scripts_head.php' ?></title>
  <link rel="icon" href="/assets/images/structure/favicon.ico" />

  <?php
  $manifestPath = ROOT_PATH . '/public/.vite/manifest.json';

  if (file_exists($manifestPath)) {
      $manifest = json_decode(file_get_contents($manifestPath), true);

      $entryKey = $manifest['src/js/main.ts'] ?? $manifest['src/js/main.js'] ?? null;
      if ($entryKey) {
          $entry = $entryKey;

          if (!empty($entry['css'][0])) {
              echo '<link rel="stylesheet" href="/' . $entry['css'][0] . '">' . PHP_EOL;
          }

          if (!empty($entry['file'])) {
              $nonce = $GLOBALS['csp_nonce'] ?? '';
              $nonceAttr = $nonce !== '' ? " nonce=\"$nonce\"" : '';
              echo '<script type="module"' . $nonceAttr . ' src="/' . $entry['file'] . '"></script>' . PHP_EOL;
          }
      } else {
          echo '<!-- ⚠️ "src/js/main.js" non trouvé dans le manifest -->' . PHP_EOL;
      }
  } else {
      echo '<!-- ⚠️ manifest.json introuvable dans public/.vite/ -->' . PHP_EOL;
  }
  ?>
