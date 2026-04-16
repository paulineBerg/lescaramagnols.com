<?php

declare(strict_types=1);

namespace Caramagnols\Editorial;

use Caramagnols\Content\PageRepository;
use Caramagnols\Content\SqlPageStore;
use Caramagnols\Navigation\NavigationRepository;
use Caramagnols\Navigation\SqlNavigationStore;

final class EditorialImportService
{
    public function __construct(
        private readonly SqlPageStore $pageStore,
        private readonly SqlNavigationStore $navigationStore
    ) {
    }

    /**
     * @param array<string, mixed> $navigationFallback
     * @param array{meta?: array<string, mixed>, pages?: array<int, array<string, mixed>>}|null $pageRegistry
     * @return array{success: bool, pages: int, menu_locations: int, error: string|null}
     */
    public function import(
        PageRepository $pageSource,
        NavigationRepository $navigationSource,
        array $navigationFallback = [],
        ?array $pageRegistry = null
    ): array {
        $registry = $pageRegistry ?? $pageSource->registry();
        $pages = is_array($registry['pages'] ?? null) ? $registry['pages'] : [];

        if (!$this->pageStore->replaceRegistry($registry)) {
            return [
                'success' => false,
                'pages' => 0,
                'menu_locations' => 0,
                'error' => 'Import SQL des pages impossible.',
            ];
        }

        $canonical = $navigationSource->loadCanonical($navigationFallback);
        if (!$this->navigationStore->saveCanonical($canonical)) {
            return [
                'success' => false,
                'pages' => count($pages),
                'menu_locations' => 0,
                'error' => 'Import SQL de la navigation impossible.',
            ];
        }

        $locations = is_array($canonical['locations'] ?? null) ? $canonical['locations'] : [];

        return [
            'success' => true,
            'pages' => count($pages),
            'menu_locations' => count($locations),
            'error' => null,
        ];
    }
}
