<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

const DEFAULT_WIDTHS = [480, 640, 768, 960, 1200];
const SOURCE_ROOT = ROOT_PATH . '/public/uploads/editorial';

/**
 * @return array{
 *   paths: array<int, string>,
 *   widths: array<int, int>,
 *   qualityJpeg: int,
 *   qualityWebp: int,
 *   force: bool,
 *   dryRun: bool
 * }
 */
function parse_options(array $argv): array
{
    $paths = [];
    $widths = DEFAULT_WIDTHS;
    $qualityJpeg = 80;
    $qualityWebp = 78;
    $force = false;
    $dryRun = false;

    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--help' || $argument === '-h') {
            print_help();
            exit(0);
        }

        if ($argument === '--force') {
            $force = true;
            continue;
        }

        if ($argument === '--dry-run') {
            $dryRun = true;
            continue;
        }

        if (str_starts_with($argument, '--path=')) {
            $value = trim((string) substr($argument, strlen('--path=')));
            if ($value !== '') {
                $paths[] = $value;
            }
            continue;
        }

        if (str_starts_with($argument, '--widths=')) {
            $rawWidths = trim((string) substr($argument, strlen('--widths=')));
            if ($rawWidths !== '') {
                $widthTokens = array_filter(array_map('trim', explode(',', $rawWidths)), static fn (string $token): bool => $token !== '');
                $parsedWidths = [];
                foreach ($widthTokens as $token) {
                    if (!ctype_digit($token)) {
                        continue;
                    }
                    $width = (int) $token;
                    if ($width >= 200 && $width <= 4096) {
                        $parsedWidths[] = $width;
                    }
                }

                if ($parsedWidths !== []) {
                    sort($parsedWidths, SORT_NUMERIC);
                    $widths = array_values(array_unique($parsedWidths));
                }
            }
            continue;
        }

        if (str_starts_with($argument, '--quality-jpg=')) {
            $token = trim((string) substr($argument, strlen('--quality-jpg=')));
            if (ctype_digit($token)) {
                $qualityJpeg = max(30, min(100, (int) $token));
            }
            continue;
        }

        if (str_starts_with($argument, '--quality-webp=')) {
            $token = trim((string) substr($argument, strlen('--quality-webp=')));
            if (ctype_digit($token)) {
                $qualityWebp = max(30, min(100, (int) $token));
            }
            continue;
        }
    }

    return [
        'paths' => array_values(array_unique($paths)),
        'widths' => $widths,
        'qualityJpeg' => $qualityJpeg,
        'qualityWebp' => $qualityWebp,
        'force' => $force,
        'dryRun' => $dryRun,
    ];
}

function print_help(): void
{
    echo "Usage:\n";
    echo "  php backend/core/tools/generate_editorial_responsive_variants.php [--path=<public-path>] [--widths=480,640,768,960,1200] [--quality-jpg=80] [--quality-webp=78] [--force] [--dry-run]\n\n";
    echo "Examples:\n";
    echo "  php backend/core/tools/generate_editorial_responsive_variants.php --path=/uploads/editorial/media/2026/04/herbert-austin-1905.jpg\n";
    echo "  php backend/core/tools/generate_editorial_responsive_variants.php --path=/uploads/editorial/media/2026/04/austin-25-30-1906-wikimedia.jpg --path=/uploads/editorial/media/2026/04/austin-seven-1922-wikimedia.jpg\n";
}

/**
 * @return array<int, string>
 */
function resolve_source_files(array $requestedPaths): array
{
    if ($requestedPaths !== []) {
        $resolved = [];
        foreach ($requestedPaths as $path) {
            $publicPath = normalize_public_path($path);
            if ($publicPath === null) {
                continue;
            }

            if (!is_file($publicPath)) {
                continue;
            }

            $resolved[] = $publicPath;
        }

        sort($resolved, SORT_STRING);

        return array_values(array_unique($resolved));
    }

    if (!is_dir(SOURCE_ROOT)) {
        return [];
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(SOURCE_ROOT, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    $files = [];
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
            continue;
        }

        $extension = strtolower((string) $fileInfo->getExtension());
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            continue;
        }

        $absolutePath = (string) $fileInfo->getPathname();
        if (preg_match('/-w[0-9]{3,4}\.(?:jpe?g|png|webp)$/i', $absolutePath) === 1) {
            continue;
        }

        $files[] = $absolutePath;
    }

    sort($files, SORT_STRING);

    return $files;
}

function normalize_public_path(string $path): ?string
{
    $path = trim($path);
    if ($path === '') {
        return null;
    }

    if (str_starts_with($path, ROOT_PATH . '/public/')) {
        $absolute = $path;
    } elseif (str_starts_with($path, '/uploads/editorial/')) {
        $absolute = ROOT_PATH . '/public' . $path;
    } else {
        return null;
    }

    $real = realpath($absolute);
    if ($real === false || !is_file($real)) {
        return null;
    }

    $normalizedRoot = str_replace('\\', '/', SOURCE_ROOT);
    $normalizedPath = str_replace('\\', '/', $real);
    if (!str_starts_with($normalizedPath, $normalizedRoot . '/')) {
        return null;
    }

    return $real;
}

/**
 * @return array{created: int, skipped: int, errors: int}
 */
function generate_variants_for_file(
    string $sourcePath,
    array $widths,
    int $qualityJpeg,
    int $qualityWebp,
    bool $force,
    bool $dryRun
): array {
    $result = ['created' => 0, 'skipped' => 0, 'errors' => 0];

    $imageInfo = @getimagesize($sourcePath);
    if (!is_array($imageInfo)) {
        fwrite(STDERR, "[skip] Non-image file: {$sourcePath}\n");
        $result['skipped']++;
        return $result;
    }

    $mime = strtolower((string) $imageInfo['mime']);
    $sourceWidth = max(1, (int) $imageInfo[0]);
    $sourceHeight = max(1, (int) $imageInfo[1]);
    $sourceExtension = strtolower((string) pathinfo($sourcePath, PATHINFO_EXTENSION));
    $directory = (string) pathinfo($sourcePath, PATHINFO_DIRNAME);
    $filename = (string) pathinfo($sourcePath, PATHINFO_FILENAME);

    $sourceImage = create_source_image($sourcePath, $mime);
    if (!$sourceImage instanceof GdImage) {
        fwrite(STDERR, "[error] Unsupported source format for {$sourcePath}\n");
        $result['errors']++;
        return $result;
    }

    if ($sourceExtension !== 'webp') {
        $fullWebpPath = $directory . '/' . $filename . '.webp';
        if (!is_file($fullWebpPath) || $force) {
            if ($dryRun) {
                echo "[dry-run] {$fullWebpPath}\n";
            } else {
                $created = resize_and_write_image(
                    $sourceImage,
                    $sourceWidth,
                    $sourceHeight,
                    $sourceWidth,
                    $sourceHeight,
                    $fullWebpPath,
                    'webp',
                    $qualityWebp
                );
                if ($created) {
                    $result['created']++;
                } else {
                    $result['errors']++;
                }
            }
        } else {
            $result['skipped']++;
        }
    }

    foreach ($widths as $width) {
        if ($width >= $sourceWidth) {
            continue;
        }

        $targetHeight = max(1, (int) round($sourceHeight * ($width / $sourceWidth)));
        if ($sourceExtension !== 'webp') {
            $targetJpegPath = $directory . '/' . $filename . '-w' . $width . '.' . $sourceExtension;
            if (!is_file($targetJpegPath) || $force) {
                if ($dryRun) {
                    echo "[dry-run] {$targetJpegPath}\n";
                } else {
                    $created = resize_and_write_image(
                        $sourceImage,
                        $sourceWidth,
                        $sourceHeight,
                        $width,
                        $targetHeight,
                        $targetJpegPath,
                        $sourceExtension,
                        $qualityJpeg
                    );
                    if ($created) {
                        $result['created']++;
                    } else {
                        $result['errors']++;
                    }
                }
            } else {
                $result['skipped']++;
            }
        }

        $targetWebpPath = $directory . '/' . $filename . '-w' . $width . '.webp';
        if (!is_file($targetWebpPath) || $force) {
            if ($dryRun) {
                echo "[dry-run] {$targetWebpPath}\n";
            } else {
                $created = resize_and_write_image(
                    $sourceImage,
                    $sourceWidth,
                    $sourceHeight,
                    $width,
                    $targetHeight,
                    $targetWebpPath,
                    'webp',
                    $qualityWebp
                );
                if ($created) {
                    $result['created']++;
                } else {
                    $result['errors']++;
                }
            }
        } else {
            $result['skipped']++;
        }
    }

    imagedestroy($sourceImage);

    return $result;
}

/**
 * @return GdImage|false
 */
function create_source_image(string $path, string $mime)
{
    return match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($path),
        'image/png' => @imagecreatefrompng($path),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default => false,
    };
}

function resize_and_write_image(
    GdImage $sourceImage,
    int $sourceWidth,
    int $sourceHeight,
    int $targetWidth,
    int $targetHeight,
    string $targetPath,
    string $format,
    int $quality
): bool {
    $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
    if (!$targetImage instanceof GdImage) {
        return false;
    }

    imagealphablending($targetImage, false);
    imagesavealpha($targetImage, true);
    $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
    if ($transparent !== false) {
        imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);
    }

    $resampled = imagecopyresampled(
        $targetImage,
        $sourceImage,
        0,
        0,
        0,
        0,
        $targetWidth,
        $targetHeight,
        $sourceWidth,
        $sourceHeight
    );

    if (!$resampled) {
        imagedestroy($targetImage);
        return false;
    }

    $written = false;
    if ($format === 'jpg' || $format === 'jpeg') {
        $written = imagejpeg($targetImage, $targetPath, $quality);
    } elseif ($format === 'png') {
        $compression = max(0, min(9, (int) round((100 - $quality) / 10)));
        $written = imagepng($targetImage, $targetPath, $compression);
    } elseif ($format === 'webp') {
        $written = imagewebp($targetImage, $targetPath, $quality);
    }

    imagedestroy($targetImage);

    if ($written) {
        @chmod($targetPath, 0644);
    }

    return $written;
}

/**
 * PHPStan analyse ce script dans la portée globale où `$options` peut aussi
 * désigner une valeur issue de `getopt()`. Réaffirmer ici la forme retournée
 * par `parse_options()` évite cette ambiguïté sans modifier le runtime.
 *
 * @var array{
 *   paths: array<int, string>,
 *   widths: array<int, int>,
 *   qualityJpeg: int,
 *   qualityWebp: int,
 *   force: bool,
 *   dryRun: bool
 * } $options
 */
$options = parse_options($argv);

if (!function_exists('imagecreatetruecolor') || !function_exists('imagecopyresampled') || !function_exists('imagewebp')) {
    fwrite(STDERR, "GD avec support WebP est requis pour générer les variantes responsive.\n");
    exit(1);
}

$sourceFiles = resolve_source_files($options['paths']);
if ($sourceFiles === []) {
    echo "Aucune image source trouvée.\n";
    exit(0);
}

echo sprintf(
    "Traitement de %d image(s) source(s) | widths=%s | dry-run=%s | force=%s\n",
    count($sourceFiles),
    implode(',', $options['widths']),
    $options['dryRun'] ? 'yes' : 'no',
    $options['force'] ? 'yes' : 'no'
);

$total = ['created' => 0, 'skipped' => 0, 'errors' => 0];

foreach ($sourceFiles as $sourceFile) {
    $publicPath = str_replace(ROOT_PATH . '/public', '', str_replace('\\', '/', $sourceFile));
    echo "-> {$publicPath}\n";
    $stats = generate_variants_for_file(
        $sourceFile,
        $options['widths'],
        $options['qualityJpeg'],
        $options['qualityWebp'],
        $options['force'],
        $options['dryRun']
    );

    $total['created'] += (int) $stats['created'];
    $total['skipped'] += (int) $stats['skipped'];
    $total['errors'] += (int) $stats['errors'];
}

echo sprintf(
    "Terminé | created=%d | skipped=%d | errors=%d\n",
    $total['created'],
    $total['skipped'],
    $total['errors']
);

exit($total['errors'] > 0 ? 1 : 0);
