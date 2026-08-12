<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal;

final class PrivateModuleRegistry
{
    /**
     * @return array<int, array<string, string>>
     */
    public function allModules(): array
    {
        return [
            [
                'code' => 'dashboard',
                'name' => 'Tableau de bord privé',
                'description' => 'Accès au tableau de bord principal de l’espace privé.',
            ],
            [
                'code' => 'documents',
                'name' => 'Documents',
                'description' => 'Accès au centre de stockage privé.',
            ],
            [
                'code' => 'blocnote',
                'name' => 'Bloc-note',
                'description' => 'Notes privées, catégories et suivi personnel.',
            ],
            [
                'code' => 'discussions',
                'name' => 'Discussions',
                'description' => 'Espace d’échanges privé.',
            ],
            [
                'code' => 'real_estate_rental',
                'name' => 'Locations immobilières',
                'description' => 'Gestion privée des biens, lots et membres locatifs.',
            ],
            [
                'code' => 'tax_declaration_helper',
                'name' => 'Aide impôts',
                'description' => 'Aide annuelle non officielle à la préparation des données fiscales privées.',
            ],
            [
                'code' => 'web_development',
                'name' => 'Web development',
                'description' => 'Gestion des projets web statiques et de leurs previsualisations privees.',
            ],
            [
                'code' => 'pbgestion',
                'name' => 'Sécurité réseau',
                'description' => 'Agents locaux, couverture, alertes, sauvegardes et syntheses de securite.',
            ],
        ];
    }

    public function moduleCode(string $code): ?array
    {
        $normalized = strtolower(trim($code));
        if ($normalized === '') {
            return null;
        }

        foreach ($this->allModules() as $module) {
            if (($module['code'] ?? '') === $normalized) {
                return $module;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function moduleCodes(): array
    {
        return array_values(
            array_filter(
                array_map(
                    static fn (array $module): string => is_string($module['code'] ?? null) ? trim((string) $module['code']) : '',
                    $this->allModules()
                ),
                static fn (string $code): bool => $code !== ''
            )
        );
    }
}
