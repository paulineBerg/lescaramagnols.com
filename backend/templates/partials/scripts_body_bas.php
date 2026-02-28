<!-- LesCaramagnols -->
<!-- templates/scripts_body_bas.php -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const lang = "<?= CURRENT_LANG ?>";

    // Ajoute ?lang=xx aux liens internes qui n’en ont pas
    document.querySelectorAll('a[href^="/"]:not([href*="?lang="])').forEach(link => {
        const href = link.getAttribute('href');
        if (!href.startsWith('/site/search')) { // ne pas polluer le champ recherche
            const sep = href.includes('?') ? '&' : '?';
            link.setAttribute('href', href + sep + 'lang=' + lang);
        }
    });
});
</script>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "name": "Les Caramagnols",
      "url": "https://www.lescaramagnols.com",
	  "logo": "https://www.lescaramagnols.com/images/paulineetnoel.jpg",
      "foundingDate": "2024-01-01",
      "founder": {
        "@type": "Person",
        "name": "Pauline Bergon"
      }
    },
    {
      "@type": "CreativeWork",
      "name": "Les Caramagnols",
      "url": "https://www.lescaramagnols.com",
      "description": "Plongez dans notre univers auto-rétro et découvrez nos voitures anciennes dans le cadre enchanteur du Golfe de Saint-Tropez.",
      "keywords": "Golfe de Saint-Tropez, voitures anciennes, auto rétro, collection, villages pittoresques, passion automobile, simca, panhard, renault, austin, mini, dyna, aronde, twingo",
      "sameAs": [
        "https://www.facebook.com/lescaramagnols",
        "https://www.instagram.com/paulineetnoel"
      ],
	  "publisher": {
	  "@type": "Organization",
	  "name": "Les Caramagnols",
	  "url": "https://www.lescaramagnols.com"
      },
      "author": {
        "@type": "Person",
        "name": "Pauline Bergon"
      },
	  "potentialAction": {
      "@type": "SearchAction",
      "target": "https://www.lescaramagnols.com/?s={search_term_string}",
      "query-input": "required name=search_term_string"
      }	  
    }
  ]
}
</script>
