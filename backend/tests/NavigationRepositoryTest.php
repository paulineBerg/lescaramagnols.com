<?php

declare(strict_types=1);

use Caramagnols\Navigation\NavigationRepository;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class NavigationRepositoryTest extends TestCase
{
    private string $tmpFile;
    private string $snapshotDir;

    protected function setUp(): void
    {
        $this->tmpFile = ROOT_PATH . '/var/navigation-repository-' . uniqid() . '.json';
        $this->snapshotDir = dirname($this->tmpFile) . '/snapshots';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }

        if (file_exists($this->tmpFile . '.bak')) {
            unlink($this->tmpFile . '.bak');
        }

        $snapshotPattern = $this->snapshotDir . '/' . pathinfo($this->tmpFile, PATHINFO_FILENAME) . '-*.json';
        foreach (glob($snapshotPattern) ?: [] as $snapshotFile) {
            @unlink($snapshotFile);
        }

        if (is_file($this->snapshotDir)) {
            @unlink($this->snapshotDir);
        }
    }

    public function testLoadCanonicalBuildsVersionedSchemaFromLegacyFallback(): void
    {
        $repository = new NavigationRepository($this->tmpFile);
        $canonical = $repository->loadCanonical([
            'menu1' => [['url' => 'https://example.test', 'title' => 'Réseau']],
            'banniere' => ['image' => '/banner.jpg', 'texte_key' => 'Bannière'],
            'menu2' => [['titre' => 'Accueil', 'chemin' => '/accueil']],
            'menu3' => [['titre' => 'Plan', 'chemin' => '/plan']],
            'menu_droit' => [['titre' => 'Droite', 'chemin' => '/droite']],
            'menu_gauche' => [['titre' => 'Gauche', 'chemin' => '/gauche']],
        ]);

        $this->assertSame(2, $canonical['meta']['version'] ?? null);
        $this->assertArrayHasKey('locations', $canonical);
        $this->assertArrayHasKey('primary', $canonical['locations']);
        $this->assertSame('Accueil', $canonical['locations']['primary'][0]['label']['text']);
        $this->assertSame('/droite', $canonical['locations']['sideRight'][0]['target']['route']);
    }

    public function testLoadLegacyConfigRehydratesTemplateFriendlyKeysFromCanonicalFile(): void
    {
        $canonical = [
            'meta' => ['version' => 2],
            'locations' => [
                'remonter' => [],
                'banner' => ['image' => '/banner.jpg', 'headline' => ['text' => 'Bannière']],
                'utility' => [],
                'primary' => [
                    [
                        'id' => 'primary-home',
                        'kind' => 'route',
                        'label' => ['text' => 'Accueil'],
                        'target' => ['route' => '/accueil', 'url' => null, 'pageSlug' => null],
                        'media' => [],
                        'accessibility' => ['alt' => 'Accueil', 'title' => 'Accueil'],
                        'children' => [],
                    ],
                ],
                'footer' => [],
                'sideRight' => [],
                'sideLeft' => [],
            ],
        ];

        file_put_contents($this->tmpFile, json_encode($canonical, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $repository = new NavigationRepository($this->tmpFile);
        $legacy = $repository->loadLegacyConfig();

        $this->assertSame('Accueil', $legacy['menu2'][0]['titre']);
        $this->assertSame('/accueil', $legacy['menu2'][0]['chemin']);
        $this->assertArrayHasKey('menuDroit', $legacy);
        $this->assertArrayHasKey('menuGauche', $legacy);
    }

    public function testSideMenusAreNormalizedAsContentCardsAndPreserveEditorialText(): void
    {
        $repository = new NavigationRepository($this->tmpFile);
        $canonical = $repository->loadCanonical([
            'menu1' => [],
            'banniere' => [],
            'menu2' => [],
            'menu3' => [],
            'menu_droit' => [
                [
                    'titre' => 'Par marque',
                    'texte' => 'Choisissez une famille de véhicules.',
                    'chemin' => '/auto-retro',
                    'image' => '/assets/images/card.jpg',
                ],
            ],
            'menu_gauche' => [],
        ]);

        $this->assertSame('content_card', $canonical['locations']['sideRight'][0]['kind']);
        $this->assertSame('Par marque', $canonical['locations']['sideRight'][0]['label']['text']);
        $this->assertSame(
            'Choisissez une famille de véhicules.',
            $canonical['locations']['sideRight'][0]['content']['text']
        );

        $legacy = NavigationRepository::canonicalToLegacy($canonical);

        $this->assertSame('Par marque', $legacy['menuDroit'][0]['titre']);
        $this->assertSame('Choisissez une famille de véhicules.', $legacy['menuDroit'][0]['texte']);
        $this->assertSame('/auto-retro', $legacy['menuDroit'][0]['chemin']);
    }

    public function testSaveCanonicalCreatesTimestampedSnapshotOfPreviousState(): void
    {
        $repository = new NavigationRepository($this->tmpFile);

        $this->assertTrue($repository->saveCanonical([
            'meta' => ['version' => 2],
            'locations' => [
                'remonter' => [],
                'banner' => [],
                'utility' => [],
                'primary' => [
                    [
                        'id' => 'primary-home',
                        'kind' => 'route',
                        'label' => ['text' => 'Accueil'],
                        'target' => ['route' => '/accueil', 'url' => null, 'pageSlug' => null, 'openInNewTab' => false],
                        'media' => [],
                        'content' => [],
                        'accessibility' => [],
                        'presentation' => [],
                        'children' => [],
                    ],
                ],
                'footer' => [],
                'sideRight' => [],
                'sideLeft' => [],
            ],
        ]));

        $this->assertTrue($repository->saveCanonical([
            'meta' => ['version' => 2],
            'locations' => [
                'remonter' => [],
                'banner' => [],
                'utility' => [],
                'primary' => [
                    [
                        'id' => 'primary-home',
                        'kind' => 'route',
                        'label' => ['text' => 'Nouvel accueil'],
                        'target' => ['route' => '/nouvel-accueil', 'url' => null, 'pageSlug' => null, 'openInNewTab' => false],
                        'media' => [],
                        'content' => [],
                        'accessibility' => [],
                        'presentation' => [],
                        'children' => [],
                    ],
                ],
                'footer' => [],
                'sideRight' => [],
                'sideLeft' => [],
            ],
        ]));

        $snapshots = glob($this->snapshotDir . '/' . pathinfo($this->tmpFile, PATHINFO_FILENAME) . '-*.json') ?: [];

        $this->assertCount(1, $snapshots);

        $snapshot = json_decode((string) file_get_contents($snapshots[0]), true);
        $this->assertIsArray($snapshot);
        $this->assertSame('Accueil', $snapshot['locations']['primary'][0]['label']['text'] ?? null);
        $this->assertSame('/accueil', $snapshot['locations']['primary'][0]['target']['route'] ?? null);
    }

    public function testSaveCanonicalStillWorksWhenSnapshotCannotBeWritten(): void
    {
        $repository = new NavigationRepository($this->tmpFile);

        $this->assertTrue($repository->saveCanonical([
            'meta' => ['version' => 2],
            'locations' => [
                'remonter' => [],
                'banner' => [],
                'utility' => [],
                'primary' => [
                    [
                        'id' => 'primary-home',
                        'kind' => 'route',
                        'label' => ['text' => 'Accueil'],
                        'target' => ['route' => '/accueil', 'url' => null, 'pageSlug' => null, 'openInNewTab' => false],
                        'media' => [],
                        'content' => [],
                        'accessibility' => [],
                        'presentation' => [],
                        'children' => [],
                    ],
                ],
                'footer' => [],
                'sideRight' => [],
                'sideLeft' => [],
            ],
        ]));

        @file_put_contents($this->snapshotDir, 'snapshot-disabled');

        $this->assertTrue($repository->saveCanonical([
            'meta' => ['version' => 2],
            'locations' => [
                'remonter' => [],
                'banner' => [],
                'utility' => [],
                'primary' => [
                    [
                        'id' => 'primary-home',
                        'kind' => 'route',
                        'label' => ['text' => 'Accueil V2'],
                        'target' => ['route' => '/accueil-v2', 'url' => null, 'pageSlug' => null, 'openInNewTab' => false],
                        'media' => [],
                        'content' => [],
                        'accessibility' => [],
                        'presentation' => [],
                        'children' => [],
                    ],
                ],
                'footer' => [],
                'sideRight' => [],
                'sideLeft' => [],
            ],
        ]));

        $saved = json_decode((string) file_get_contents($this->tmpFile), true);
        $this->assertIsArray($saved);
        $this->assertSame('Accueil V2', $saved['locations']['primary'][0]['label']['text'] ?? null);
        $this->assertSame('/accueil-v2', $saved['locations']['primary'][0]['target']['route'] ?? null);
    }
}
