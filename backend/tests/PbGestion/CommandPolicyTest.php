<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\PbGestion;

use Caramagnols\PbGestion\Command\CommandPolicy;
use PHPUnit\Framework\TestCase;

final class CommandPolicyTest extends TestCase
{
    public function testAllowsClosedCommandCatalog(): void
    {
        $policy = new CommandPolicy();

        $this->assertTrue($policy->isAllowed('scan.start'));
        $this->assertTrue($policy->validate('scan.start', [
            'network_token' => 'network-token-123456',
            'scan_mode' => 'active_limited',
        ])['ok']);
        $this->assertTrue($policy->validate('monitoring.resume', [])['ok']);
    }

    public function testRejectsRemoteExecutionAndSensitivePayloadKeys(): void
    {
        $policy = new CommandPolicy();

        $this->assertTrue($policy->isForbidden('shell.execute'));
        $this->assertFalse($policy->validate('shell.execute', ['command' => 'whoami'])['ok']);
        $this->assertFalse($policy->validate('scan.start', [
            'network_token' => 'network-token-123456',
            'scan_mode' => 'active_limited',
            'ip' => '192.168.1.1',
        ])['ok']);
        $this->assertFalse($policy->validate('module.enable', ['module' => 'network', 'url' => 'https://example.test'])['ok']);
    }

    public function testDetailsPrepareRequiresOpaqueUidAndKnownPurpose(): void
    {
        $policy = new CommandPolicy();

        $this->assertTrue($policy->validate('details.prepare', [
            'detail_uid' => str_repeat('a', 32),
            'purpose' => 'support',
        ])['ok']);
        $this->assertFalse($policy->validate('details.prepare', [
            'detail_uid' => 'bad',
            'purpose' => 'support',
        ])['ok']);
        $this->assertFalse($policy->validate('details.prepare', [
            'detail_uid' => str_repeat('b', 32),
            'purpose' => 'debug',
        ])['ok']);
    }

    public function testPhotoCommandsAreBoundedToAllowedRootsRelativeDirsAndSelectedFiles(): void
    {
        $policy = new CommandPolicy();

        $this->assertTrue($policy->validate('photo.folder.scan', [
            'root_uid' => 'photos-principales',
            'relative_dir' => '2026/vacances',
            'include_subdirectories' => true,
        ])['ok']);

        $this->assertTrue($policy->validate('photo.rename.preview', [
            'batch_uid' => str_repeat('a', 32),
            'root_uid' => 'photos-principales',
            'relative_dir' => '2026/vacances',
            'items' => ['IMG_0001.jpg', 'IMG_0002.HEIC'],
            'template' => [
                ['type' => 'text', 'value' => 'Vacances'],
                ['type' => 'city'],
                ['type' => 'date'],
                ['type' => 'counter'],
            ],
            'separator' => '_',
            'counter_digits' => 3,
            'sort_order' => 'chronological',
            'conflict_strategy' => 'block',
        ])['ok']);

        $this->assertFalse($policy->validate('photo.folder.scan', [
            'root_uid' => 'photos-principales',
            'relative_dir' => '../Windows',
        ])['ok']);
        $this->assertFalse($policy->validate('photo.rename.preview', [
            'batch_uid' => str_repeat('b', 32),
            'root_uid' => 'photos-principales',
            'relative_dir' => '2026',
            'items' => ['../secret.jpg'],
            'template' => [['type' => 'city']],
            'separator' => '-',
            'counter_digits' => 3,
            'sort_order' => 'name',
            'conflict_strategy' => 'block',
        ])['ok']);
        $this->assertFalse($policy->validate('photo.rename.execute', [
            'batch_uid' => str_repeat('c', 32),
            'preview_uid' => 'bad',
        ])['ok']);
    }
}
