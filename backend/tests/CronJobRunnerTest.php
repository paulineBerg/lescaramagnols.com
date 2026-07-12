<?php

declare(strict_types=1);

use Caramagnols\Cron\CronJobRunner;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class CronJobRunnerTest extends TestCase
{
    private string $tmpRoot;

    protected function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/caramagnols-cron-runner-test-' . bin2hex(random_bytes(6));
        mkdir($this->tmpRoot . '/core/tools', 0777, true);
        mkdir($this->tmpRoot . '/locks', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectoryRecursively($this->tmpRoot);
    }

    public function testRunnerKeepsProcessExitCode(): void
    {
        $scriptPath = $this->tmpRoot . '/core/tools/check_env.php';
        file_put_contents($scriptPath, "<?php\nexit(7);\n");

        $runner = new CronJobRunner($this->tmpRoot, PHP_BINARY, $this->tmpRoot . '/locks');
        $result = $runner->run([
            'code' => 'check_env_exit',
            'name' => 'Check env exit',
            'script_path' => 'core/tools/check_env.php',
            'arguments' => [
                'args' => [],
            ],
            'timeout_seconds' => 5,
        ]);

        $this->assertSame('failed', $result['status']);
        $this->assertSame(7, $result['exit_code']);
        $this->assertSame('Code retour 7.', $result['message']);
    }

    private function removeDirectoryRecursively(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectoryRecursively($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
