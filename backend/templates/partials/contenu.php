
<?php

use Caramagnols\Content\StandardPageLayout;

foreach (StandardPageLayout::regions() as $regionDefinition) {
    $regionContent = (string) ($blocks[$regionDefinition['slot']] ?? '');
    include __DIR__ . '/content_region.php';
}
