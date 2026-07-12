<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal;

interface PrivateAppManifest
{
    public function migrationCode(): string;

    public function moduleCode(): string;

    public function moduleName(): string;

    public function moduleDescription(): string;

    public function modulePermissionCode(): string;

    public function migrationStatusCode(): string;

    public function title(): string;

    public function order(): int;

    /**
     * @return array<int, string>
     */
    public function routeNames(): array;

    /**
     * @return array<int, string>
     */
    public function tables(): array;

    /**
     * @return array<int, string>
     */
    public function contractClasses(): array;

    /**
     * @return array<int, string>
     */
    public function testClasses(): array;

    /**
     * @return array<int, string>
     */
    public function auditEvents(): array;

    /**
     * @return array<int, string>
     */
    public function uiStates(): array;

    /**
     * @return array<int, string>
     */
    public function legacyRoutes(): array;

    public function notes(): string;
}
