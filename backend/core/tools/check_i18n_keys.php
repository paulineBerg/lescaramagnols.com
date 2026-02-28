<?php
// Script de vérification des clés i18n entre fr/en/de
// Usage : php core/tools/check_i18n_keys.php

declare(strict_types=1);

require_once __DIR__ . '/../env.php';

$langs = ['fr', 'en', 'de'];
$base = dirname(__DIR__, 1) . '/lang';

$all = [];
foreach ($langs as $lang) {
    $file = "$base/$lang.php";
    if (!file_exists($file)) {
        fwrite(STDERR, "Lang file missing: $file\n");
        exit(1);
    }

    $data = require $file;
    if (!is_array($data)) {
        fwrite(STDERR, "Lang file invalid: $file\n");
        exit(1);
    }

    $all[$lang] = array_keys($data);
}

$ref = $all['fr'];
$errors = false;

foreach ($langs as $lang) {
    $missing = array_diff($ref, $all[$lang]);
    if ($missing !== []) {
        $errors = true;
        echo "Clés manquantes en $lang : " . implode(', ', $missing) . "\n";
    }
}

if ($errors) {
    exit(1);
}

echo "Toutes les langues possèdent les mêmes clés.\n";
exit(0);
