<?php

$regionDefinition = is_array($regionDefinition ?? null) ? $regionDefinition : [];
$regionContent = (string) ($regionContent ?? '');
$tagName = is_string($regionDefinition['tag'] ?? null) ? $regionDefinition['tag'] : 'div';
$regionId = is_string($regionDefinition['id'] ?? null) ? $regionDefinition['id'] : '';
$regionClass = trim((string) ($regionDefinition['class'] ?? ''));
?>
<<?= $tagName ?>
    <?= $regionId !== '' ? 'id="' . htmlspecialchars($regionId, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
    <?= $regionClass !== '' ? 'class="' . htmlspecialchars($regionClass, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
>
    <?= $regionContent ?>
</<?= $tagName ?>>
