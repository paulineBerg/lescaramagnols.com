<?php // templates/partials/footer.php ?>
<?php
$footerNoticeText = null;
$footerNotice = is_array($navigationViewModel['footerNotice'] ?? null) ? $navigationViewModel['footerNotice'] : [];
if (is_string($footerNotice['text'] ?? null) && trim((string) $footerNotice['text']) !== '') {
    $footerNoticeText = trim((string) $footerNotice['text']);
} else {
    $fallback = t('TXT_PiedPageModele');
    $footerNoticeText = is_string($fallback) ? $fallback : '';
}
?>
<p class="piedpage-modele"><?= htmlspecialchars($footerNoticeText, ENT_QUOTES, 'UTF-8') ?></p>

<footer>&copy; <?= date('Y') ?> <?php echo htmlspecialchars(t('TXT_SITE_BRAND'), ENT_QUOTES, 'UTF-8'); ?></footer>
