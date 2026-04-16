<?php

declare(strict_types=1);

use Caramagnols\Logging\LoggerFactory;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class LoggerFactoryTest extends TestCase
{
    private string $logDir;
    private array $previousLoggingConfig = [];

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/caramagnols-logger-factory-' . bin2hex(random_bytes(6));
        mkdir($this->logDir, 0777, true);

        global $appConfig;
        $this->previousLoggingConfig = is_array($appConfig['logging'] ?? null) ? $appConfig['logging'] : [];
        $appConfig['logging'] = [
            'retention_files' => 3,
            'rotation_max_bytes' => 262144,
        ];
    }

    protected function tearDown(): void
    {
        global $appConfig;
        $appConfig['logging'] = $this->previousLoggingConfig;

        $files = glob($this->logDir . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }

        @rmdir($this->logDir);
    }

    public function testCreateRotatesOversizedLogWithoutBlockingWrites(): void
    {
        $logFile = $this->logDir . '/security.log';
        file_put_contents($logFile, str_repeat('x', 300000));

        $logger = (new LoggerFactory($this->logDir, 'test'))->create('security');
        $logger->info('rotation-check');

        $this->assertFileExists($this->logDir . '/security.log');
        $this->assertFileExists($this->logDir . '/security.log.1');

        $content = (string) file_get_contents($this->logDir . '/security.log');
        $this->assertStringContainsString('rotation-check', $content);
    }
}
