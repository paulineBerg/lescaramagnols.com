<?php

declare(strict_types=1);

use Caramagnols\Admin\AdminRecoveryService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class AdminRecoveryServiceTest extends TestCase
{
    private string $overridePath;

    protected function setUp(): void
    {
        $this->overridePath = ROOT_PATH . '/var/admin-recovery-test-' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($this->overridePath, "<?php\n\nreturn [\n    'identifier' => 'admin@example.com',\n    'password_hash' => '" . password_hash('InitialPassword1!', PASSWORD_DEFAULT) . "',\n    'totp_enabled' => true,\n    'totp_secret' => 'JBSWY3DPEHPK3PXP',\n];\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->overridePath);
    }

    public function testGenerateCreatesTenUniqueKeys(): void
    {
        $service = new AdminRecoveryService($this->overridePath);
        $keys = $service->generatePlainKeys();

        self::assertCount(10, $keys);
        self::assertCount(10, array_unique(array_column($keys, 'key')));
        foreach ($keys as $entry) {
            self::assertMatchesRegularExpression('/^CAR-REC(?:-[A-Z2-9]{4}){8}$/', $entry['key']);
        }
    }

    public function testInstallAndRecoverConsumesOneKeyAndDisablesTotp(): void
    {
        $service = new AdminRecoveryService($this->overridePath);
        $keys = $service->generatePlainKeys();

        self::assertSame(10, $service->installPlainKeys($keys));
        self::assertTrue($service->hasUsableRecoveryKey());

        $result = $service->recover('admin@example.com', $keys[0]['key'], 'NewPassword123!');
        self::assertTrue($result['success'], $result['error']);

        $override = require $this->overridePath;
        self::assertIsArray($override);
        self::assertFalse((bool) ($override['totp_enabled'] ?? true));
        self::assertTrue(password_verify('NewPassword123!', (string) ($override['password_hash'] ?? '')));
        self::assertNotEmpty($override['recovery_keys'][0]['used_at'] ?? '');

        $reused = $service->recover('admin@example.com', $keys[0]['key'], 'OtherPassword123!');
        self::assertFalse($reused['success']);
    }
}
