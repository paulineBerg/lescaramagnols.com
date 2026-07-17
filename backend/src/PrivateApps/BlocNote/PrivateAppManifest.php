<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\BlocNote;

final class PrivateAppManifest implements \Caramagnols\PrivatePortal\PrivateAppManifest
{
    public function migrationCode(): string
    {
        return 'blocnote';
    }

    public function moduleCode(): string
    {
        return 'blocnote';
    }

    public function moduleName(): string
    {
        return 'Bloc-note';
    }

    public function moduleDescription(): string
    {
        return 'Notes personnelles avec catégories et organisation par utilisateur.';
    }

    public function modulePermissionCode(): string
    {
        return 'blocnote';
    }

    public function migrationStatusCode(): string
    {
        return 'blocnote';
    }

    public function title(): string
    {
        return 'BlocNote';
    }

    public function order(): int
    {
        return 1;
    }

    /**
     * @return array<int, string>
     */
    public function routeNames(): array
    {
        return [
            'blocnote',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function tables(): array
    {
        return [
            'blocnote_notes',
            'blocnote_categories',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function contractClasses(): array
    {
        return [
            'Caramagnols\\PrivateApps\\BlocNote\\BlocNoteRepository',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function testClasses(): array
    {
        return [
            'BlocNoteControllerTest',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function auditEvents(): array
    {
        return [
            'private.blocnote.note.saved',
            'private.blocnote.note.deleted',
            'private.blocnote.category.saved',
            'private.blocnote.category.deleted',
            'private.module.access_denied',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function uiStates(): array
    {
        return ['empty', 'error', 'success'];
    }

    /**
     * @return array<int, string>
     */
    public function legacyRoutes(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function routePaths(): array
    {
        return [
            'blocnote' => 'blocnote',
        ];
    }

    /**
     * @return array{label: string, description: string, stat_code: string}
     */
    public function dashboardTileData(): array
    {
        return [
            'label' => 'Bloc-note',
            'description' => 'Vos notes personnelles organisées',
            'stat_code' => 'private.blocnote.note_count',
        ];
    }

    public function notes(): string
    {
        return 'Module extrait de PrivatePortal vers PrivateApps le 2026-07-17.';
    }
}
