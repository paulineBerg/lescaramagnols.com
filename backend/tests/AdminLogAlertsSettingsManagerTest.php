<?php

declare(strict_types=1);

use Caramagnols\Admin\Settings\AdminLogAlertsSettingsManager;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class AdminLogAlertsSettingsManagerTest extends TestCase
{
    public function testConfiguredFallsBackToAlertsWhenInvalidValue(): void
    {
        $manager = new AdminLogAlertsSettingsManager('alerts');

        $configured = $manager->configured('invalid');

        $this->assertSame('alerts', $configured['notifyOn']);
    }

    public function testFormKeepsFallbackWhenPayloadMissing(): void
    {
        $manager = new AdminLogAlertsSettingsManager('alerts');

        $form = $manager->form([], ['notifyOn' => 'always']);

        $this->assertSame('always', $form['notifyOn']);
    }

    public function testNormalizeConfigRejectsInvalidMode(): void
    {
        $manager = new AdminLogAlertsSettingsManager('alerts');

        $normalized = $manager->normalizeConfig(['notifyOn' => 'off']);

        $this->assertSame([], $normalized['data']);
        $this->assertNotNull($normalized['error']);
    }
}

