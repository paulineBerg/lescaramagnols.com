<?php

declare(strict_types=1);

namespace Caramagnols\Content;

final class StandardPageLayout
{
    /**
     * @return array<int, array{id: string, slot: string, class?: string, tag?: string}>
     */
    public static function regions(): array
    {
        return [
            ['slot' => 'EditRegion1', 'id' => 'bloc-haut'],
            ['slot' => 'EditRegion2', 'id' => 'colonne-justifie-40'],
            ['slot' => 'EditRegion8', 'id' => 'colonne-justifie-25'],
            ['slot' => 'EditRegion3', 'id' => 'bloc-centre'],
            ['slot' => 'EditRegion4', 'id' => 'blocbascentre'],
            ['slot' => 'EditRegion5', 'id' => 'blocbaspetitgauche', 'class' => 'flottegauche'],
            ['slot' => 'EditRegion6', 'id' => 'blocbaspetitdroit', 'class' => 'flottedroite'],
            ['slot' => 'EditRegion7', 'id' => 'blocbaspetitcentre', 'class' => 'flottecentre'],
            // L'id "bloc-centre" est conservé ici pour éviter toute rupture CSS sur le legacy.
            ['slot' => 'EditRegion11', 'id' => 'bloc-centre'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function semanticSlots(): array
    {
        return [
            'hero' => 'EditRegion1',
            'intro' => 'EditRegion8',
            'aside' => 'EditRegion2',
            'body' => 'EditRegion3',
            'after_body' => 'EditRegion4',
            'left' => 'EditRegion5',
            'right' => 'EditRegion6',
            'bottom' => 'EditRegion7',
            'postscript' => 'EditRegion11',
            'footer' => 'EditRegion9',
            'extra' => 'EditRegion12',
        ];
    }
}
