<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\NetworkSecurity\Http;

use Caramagnols\Http\Request;
use Caramagnols\Http\Response;
use Caramagnols\LocalAgentPlatform\Http\LocalAgentPortalController;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\PbGestion\Persistence\PbGestionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use Caramagnols\PrivatePortal\Security\PrivateAuth;
use Caramagnols\PrivatePortal\Security\PrivatePortalSecurityGuard;

final class NetworkSecurityController
{
    private readonly LocalAgentPortalController $sharedController;

    /**
     * @param \Closure(string, array<string, mixed>): Response $render
     */
    public function __construct(
        PrivateAuth $auth,
        PrivatePortalSecurityGuard $securityGuard,
        PrivateUserRepository $privateUserRepository,
        PrivateModulePermissionRepository $modulePermissionRepository,
        PbGestionRepository $repository,
        \Closure $render,
        ?AppEventLogger $eventLogger = null
    ) {
        $this->sharedController = new LocalAgentPortalController(
            $auth,
            $securityGuard,
            $privateUserRepository,
            $modulePermissionRepository,
            $repository,
            $render,
            $eventLogger
        );
    }

    public function handle(string $page, Request $request): Response
    {
        return $this->sharedController->handle($page, $request);
    }
}
