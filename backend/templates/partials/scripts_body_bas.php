<!-- LesCaramagnols -->
<!-- templates/scripts_body_bas.php -->
<script nonce="<?= htmlspecialchars($GLOBALS['csp_nonce'] ?? '') ?>">
document.addEventListener('DOMContentLoaded', () => {
    const lang = "<?= CURRENT_LANG ?>";

    // Evite un scan complet du DOM: on enrichit le lien seulement au moment du clic.
    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const link = event.target.closest('a[href^="/"]');
        if (!(link instanceof HTMLAnchorElement)) {
            return;
        }

        if (link.target === '_blank' || link.hasAttribute('download')) {
            return;
        }

        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
            return;
        }

        const href = link.getAttribute('href') ?? '';
        if (href === '' || href.startsWith('/search')) {
            return;
        }

        let url;
        try {
            url = new URL(href, window.location.origin);
        } catch (error) {
            return;
        }

        if (url.searchParams.has('lang')) {
            return;
        }

        url.searchParams.set('lang', lang);
        link.setAttribute('href', `${url.pathname}${url.search}${url.hash}`);
    }, { capture: true });
});
</script>
