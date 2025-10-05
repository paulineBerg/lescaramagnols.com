<?php
// core/tools/generate_favicon.php

declare(strict_types=1);

$root = dirname(__DIR__, 3);

$sourceCandidates = [
    $root . '/frontend/src/assets/images/structure/logo.jpg',
    $root . '/frontend/src/assets/images/structure/logo.png',
];

$targetDir = $root . '/frontend/src/assets/images/structure';

$icoSizes = [16, 32, 48, 64];
$extraPngSizes = [180, 192, 512];

function pickSourcePath(array $candidates): string
{
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    fwrite(STDERR, 'No logo found (logo.jpg or logo.png) in structure directory.' . PHP_EOL);
    exit(1);
}

function loadSource(string $path)
{
    $data = file_get_contents($path);
    if ($data === false) {
        fwrite(STDERR, 'Cannot read source file: ' . $path . PHP_EOL);
        exit(1);
    }

    $image = imagecreatefromstring($data);
    if ($image === false) {
        fwrite(STDERR, 'GD failed to open the provided image.' . PHP_EOL);
        exit(1);
    }

    imagesavealpha($image, true);
    imagealphablending($image, false);

    return $image;
}

function exportPng($source, int $size): string
{
    $resized = imagecreatetruecolor($size, $size);
    imagesavealpha($resized, true);
    imagealphablending($resized, false);

    $width = imagesx($source);
    $height = imagesy($source);

    imagecopyresampled($resized, $source, 0, 0, 0, 0, $size, $size, $width, $height);

    ob_start();
    imagepng($resized);
    $pngData = (string) ob_get_clean();

    imagedestroy($resized);

    return $pngData;
}

function buildIco($source, array $sizes): string
{
    $entries = [];
    $offset = 6 + 16 * count($sizes);

    foreach ($sizes as $size) {
        $pngData = exportPng($source, $size);
        $entries[] = [
            'size' => $size,
            'data' => $pngData,
            'dataLength' => strlen($pngData),
            'offset' => $offset,
        ];
        $offset += strlen($pngData);
    }

    $ico = pack('vvv', 0, 1, count($entries));

    foreach ($entries as $entry) {
        $dimensionByte = $entry['size'] === 256 ? 0 : $entry['size'];
        $ico .= pack('CCCCvvVV',
            $dimensionByte,
            $dimensionByte,
            0,
            0,
            1,
            32,
            $entry['dataLength'],
            $entry['offset']
        );
    }

    foreach ($entries as $entry) {
        $ico .= $entry['data'];
    }

    return $ico;
}

$sourcePath = pickSourcePath($sourceCandidates);
$sourceImage = loadSource($sourcePath);

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

$icoBinary = buildIco($sourceImage, $icoSizes);
file_put_contents($targetDir . '/favicon.ico', $icoBinary);

foreach ($icoSizes as $size) {
    $pngData = exportPng($sourceImage, $size);
    file_put_contents(sprintf('%s/favicon-%dx%d.png', $targetDir, $size, $size), $pngData);
}

foreach ($extraPngSizes as $size) {
    $pngData = exportPng($sourceImage, $size);
    file_put_contents(sprintf('%s/favicon-%dx%d.png', $targetDir, $size, $size), $pngData);
}

echo 'favicon.ico generated from ' . $sourcePath . PHP_EOL;
echo 'PNG variants generated (' . implode(', ', array_merge($icoSizes, $extraPngSizes)) . ' px)' . PHP_EOL;

imagedestroy($sourceImage);
