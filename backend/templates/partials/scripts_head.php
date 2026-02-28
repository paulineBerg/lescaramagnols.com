
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $pageTitle ?? 'Les Caramagnols : scripts_head.php' ?></title>
  <link rel="icon" href="/assets/images/structure/favicon.ico" />

  <?php
  $manifestPath = ROOT_PATH . '/public/.vite/manifest.json';

  if (file_exists($manifestPath)) {
      $manifest = json_decode(file_get_contents($manifestPath), true);

      if (isset($manifest['src/js/main.js'])) {
          $entry = $manifest['src/js/main.js'];

          if (!empty($entry['css'][0])) {
              echo '<link rel="stylesheet" href="/' . $entry['css'][0] . '">' . PHP_EOL;
          }

          if (!empty($entry['file'])) {
              echo '<script type="module" src="/' . $entry['file'] . '"></script>' . PHP_EOL;
          }
      } else {
          echo '<!-- ⚠️ "src/js/main.js" non trouvé dans le manifest -->' . PHP_EOL;
      }
  } else {
      echo '<!-- ⚠️ manifest.json introuvable dans public/.vite/ -->' . PHP_EOL;
  }
  ?>
