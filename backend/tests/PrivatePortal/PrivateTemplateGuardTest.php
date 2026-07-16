<?php

declare(strict_types=1);

namespace Caramagnols\Tests\PrivatePortal;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class PrivateTemplateGuardTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function privateTemplates(): iterable
    {
        $rootPath = dirname(__DIR__, 2);
        $templatesPath = $rootPath . '/templates/private';
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($templatesPath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            yield str_replace($rootPath . '/', '', $path) => [$path];
        }
    }

    /**
     * @dataProvider privateTemplates
     */
    public function testPrivateTemplatesStayPresentationOnly(string $path): void
    {
        $contents = file_get_contents($path);
        self::assertIsString($contents);

        $relativePath = $this->relativePath($path);
        $forbiddenPatterns = [
            'inline <style> blocks' => '/<style\b/i',
            'inline style attributes' => '/\sstyle\s*=/i',
            'inline JavaScript event handlers' => '/\son[a-z]+\s*=/i',
            'non-button controls exposed as buttons' => '/<(?:a|div|span|tr|td|summary)\b[^>]*\brole\s*=\s*["\']button["\']/i',
            'SQL embedded in templates' => '/\b(?:SELECT|INSERT|UPDATE|DELETE)\s+/',
            'database access from templates' => '/\b(?:editorial_database|pdo\s*\(|mysqli_|db_query)\b/i',
            'repository instantiation in templates' => '/\bnew\s+[A-Za-z0-9_\\\\]*Repository\b/',
            'service instantiation in templates' => '/\bnew\s+[A-Za-z0-9_\\\\]*Service\b/',
            'write operations from templates' => '/->(?:create|update|delete|save|insert|ensure|purge|restore|backup|execute|query|prepare)\s*\(/',
        ];

        foreach ($forbiddenPatterns as $label => $pattern) {
            self::assertDoesNotMatchRegularExpression(
                $pattern,
                $contents,
                sprintf('%s contient une dette interdite C7: %s.', $relativePath, $label)
            );
        }

        $this->assertDialogOpenersAreButtons($relativePath, $contents);
    }

    private function assertDialogOpenersAreButtons(string $relativePath, string $contents): void
    {
        preg_match_all('/<([a-z0-9:-]+)\b[^>]*\bdata-private-dialog-open\b[^>]*>/i', $contents, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $tagName = strtolower($match[1]);
            self::assertSame(
                'button',
                $tagName,
                sprintf('%s utilise data-private-dialog-open sur <%s> au lieu de <button>.', $relativePath, $tagName)
            );
            self::assertMatchesRegularExpression(
                '/\btype\s*=\s*["\']button["\']/i',
                $match[0],
                sprintf('%s utilise data-private-dialog-open sans type="button".', $relativePath)
            );
        }
    }

    private function relativePath(string $path): string
    {
        $rootPath = dirname(__DIR__, 2);

        return str_replace($rootPath . '/', '', $path);
    }
}
