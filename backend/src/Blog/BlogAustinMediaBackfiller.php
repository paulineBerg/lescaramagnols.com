<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

final class BlogAustinMediaBackfiller
{
    /** @var array<string, array{feature: array{src: string, width: int, height: int}, body: array{src: string, width: int, height: int}}> */
    private const PROFILES = [
        'mini_origin' => [
            'feature' => ['src' => '/assets/images/autoretro/austin/1959_mini.jpg', 'width' => 400, 'height' => 224],
            'body' => ['src' => '/assets/images/autoretro/austin/1963_MkI_Mini.jpg', 'width' => 400, 'height' => 300],
        ],
        'mini_sport' => [
            'feature' => ['src' => '/assets/images/autoretro/austin/Mini_Cooper_1964.jpg', 'width' => 400, 'height' => 289],
            'body' => ['src' => '/assets/images/autoretro/austin/Mini_cooper-knightsbridge.jpg', 'width' => 400, 'height' => 297],
        ],
        'mini_evolution' => [
            'feature' => ['src' => '/assets/images/autoretro/austin/1963_MkI_Mini.jpg', 'width' => 400, 'height' => 300],
            'body' => ['src' => '/assets/images/autoretro/austin/Mini_Clubman_Estate.jpg', 'width' => 400, 'height' => 208],
        ],
        'mini_culture' => [
            'feature' => ['src' => '/assets/images/autoretro/austin/aronde_et_mini_en_expo.jpg', 'width' => 400, 'height' => 266],
            'body' => ['src' => '/assets/images/autoretro/austin/mini_mayfair.jpg', 'width' => 400, 'height' => 267],
        ],
        'mini_modern' => [
            'feature' => ['src' => '/assets/images/autoretro/austin/Mini_Cooper_Cabriolet_Sidewalk.jpg', 'width' => 400, 'height' => 278],
            'body' => ['src' => '/assets/images/autoretro/austin/Mini_Clubman_Estate.jpg', 'width' => 400, 'height' => 208],
        ],
        'austin_early' => [
            'feature' => ['src' => '/uploads/editorial/media/2026/04/herbert-austin-1905.jpg', 'width' => 972, 'height' => 1236],
            'body' => ['src' => '/uploads/editorial/media/2026/04/austin-25-30-1906-wikimedia.jpg', 'width' => 470, 'height' => 404],
        ],
        'austin_seven' => [
            'feature' => ['src' => '/uploads/editorial/media/2026/04/austin-seven-1922-wikimedia.jpg', 'width' => 650, 'height' => 545],
            'body' => ['src' => '/assets/images/autoretro/austin/austin_7hp.jpg', 'width' => 400, 'height' => 337],
        ],
        'austin_industry' => [
            'feature' => ['src' => '/assets/images/autoretro/austin/bmc.jpg', 'width' => 383, 'height' => 249],
            'body' => ['src' => '/assets/images/autoretro/austin/austin_logo.jpg', 'width' => 200, 'height' => 258],
        ],
        'austin_general' => [
            'feature' => ['src' => '/assets/images/autoretro/austin/austin_logo.jpg', 'width' => 200, 'height' => 258],
            'body' => ['src' => '/assets/images/autoretro/austin/austin_7hp.jpg', 'width' => 400, 'height' => 337],
        ],
    ];

    /** @var array<string, array<string, array{alt: string, title: string, caption: string}>> */
    private const COPY = [
        'mini_origin' => [
            'fr' => ['alt' => 'Mini de première génération présentée en 1959', 'title' => 'Mini de 1959', 'caption' => 'La petite carrosserie de 1959 concentre le moteur transversal, la traction avant et quatre vraies places dans un peu plus de trois mètres.'],
            'en' => ['alt' => 'First-generation Mini introduced in 1959', 'title' => '1959 Mini', 'caption' => 'The 1959 body combines a transverse engine, front-wheel drive and four usable seats in just over three metres.'],
            'de' => ['alt' => 'Mini der ersten Generation aus dem Jahr 1959', 'title' => 'Mini von 1959', 'caption' => 'Die Karosserie von 1959 verbindet Quermotor, Frontantrieb und vier nutzbare Plätze auf wenig mehr als drei Metern.'],
        ],
        'mini_sport' => [
            'fr' => ['alt' => 'Mini Cooper de compétition de 1964', 'title' => 'Mini Cooper de 1964', 'caption' => 'La préparation Cooper transforme l’agilité de la Mini en véritable aptitude à la compétition.'],
            'en' => ['alt' => '1964 competition Mini Cooper', 'title' => '1964 Mini Cooper', 'caption' => 'Cooper development turned the Mini’s agility into genuine competition ability.'],
            'de' => ['alt' => 'Mini Cooper im Wettbewerb von 1964', 'title' => 'Mini Cooper von 1964', 'caption' => 'Die Cooper-Entwicklung machte aus der Handlichkeit des Mini eine echte Wettbewerbsstärke.'],
        ],
        'mini_evolution' => [
            'fr' => ['alt' => 'Mini Mk I classique vue de trois quarts', 'title' => 'Mini Mk I classique', 'caption' => 'La silhouette de la Mk I fournit le point de départ pour comparer les évolutions ultérieures de la Mini classique.'],
            'en' => ['alt' => 'Classic Mini Mk I seen from the front', 'title' => 'Classic Mini Mk I', 'caption' => 'The Mk I shape provides the starting point for comparing later developments of the classic Mini.'],
            'de' => ['alt' => 'Klassischer Mini Mk I in Vorderansicht', 'title' => 'Klassischer Mini Mk I', 'caption' => 'Die Form des Mk I bildet den Ausgangspunkt für den Vergleich späterer Entwicklungen des klassischen Mini.'],
        ],
        'mini_culture' => [
            'fr' => ['alt' => 'Mini classique exposée avec une Simca Aronde', 'title' => 'Mini classique en exposition', 'caption' => 'En exposition comme sur la route, la Mini reste immédiatement reconnaissable et traverse les générations de collectionneurs.'],
            'en' => ['alt' => 'Classic Mini displayed beside a Simca Aronde', 'title' => 'Classic Mini on display', 'caption' => 'On display or on the road, the Mini remains instantly recognisable across generations of enthusiasts.'],
            'de' => ['alt' => 'Klassischer Mini neben einem Simca Aronde in einer Ausstellung', 'title' => 'Klassischer Mini in einer Ausstellung', 'caption' => 'In der Ausstellung wie auf der Straße bleibt der Mini über Generationen hinweg sofort erkennbar.'],
        ],
        'mini_modern' => [
            'fr' => ['alt' => 'Mini Cooper Cabriolet de génération moderne', 'title' => 'Mini Cooper moderne', 'caption' => 'La Mini moderne reprend un nom et des signes familiers dans une automobile de dimensions et de conception différentes.'],
            'en' => ['alt' => 'Modern-generation Mini Cooper Convertible', 'title' => 'Modern Mini Cooper', 'caption' => 'The modern Mini carries familiar names and cues into a car with different dimensions and engineering.'],
            'de' => ['alt' => 'Mini Cooper Cabriolet der modernen Generation', 'title' => 'Moderner Mini Cooper', 'caption' => 'Der moderne Mini überträgt bekannte Namen und Zeichen auf ein technisch und räumlich anderes Auto.'],
        ],
        'austin_early' => [
            'fr' => ['alt' => 'Portrait d’Herbert Austin en 1905', 'title' => 'Herbert Austin en 1905', 'caption' => 'Herbert Austin donne son nom à l’entreprise créée à Longbridge au début du XXe siècle.'],
            'en' => ['alt' => 'Portrait of Herbert Austin in 1905', 'title' => 'Herbert Austin in 1905', 'caption' => 'Herbert Austin gave his name to the company established at Longbridge in the early twentieth century.'],
            'de' => ['alt' => 'Porträt von Herbert Austin im Jahr 1905', 'title' => 'Herbert Austin 1905', 'caption' => 'Herbert Austin gab dem zu Beginn des 20. Jahrhunderts in Longbridge gegründeten Unternehmen seinen Namen.'],
        ],
        'austin_seven' => [
            'fr' => ['alt' => 'Austin Seven de 1922', 'title' => 'Austin Seven de 1922', 'caption' => 'La Seven de 1922 joue un rôle central dans l’accès britannique à une automobile petite et abordable.'],
            'en' => ['alt' => '1922 Austin Seven', 'title' => '1922 Austin Seven', 'caption' => 'The 1922 Seven played a central part in British access to a small and affordable motor car.'],
            'de' => ['alt' => 'Austin Seven von 1922', 'title' => 'Austin Seven von 1922', 'caption' => 'Der Seven von 1922 spielte eine zentrale Rolle beim britischen Zugang zu einem kleinen und erschwinglichen Automobil.'],
        ],
        'austin_industry' => [
            'fr' => ['alt' => 'Affiche des marques de la British Motor Corporation', 'title' => 'Marques de la BMC', 'caption' => 'Le regroupement Austin, Morris, MG, Riley et Wolseley explique une grande partie des pièces et modèles partagés.'],
            'en' => ['alt' => 'British Motor Corporation brand display', 'title' => 'BMC brands', 'caption' => 'The Austin, Morris, MG, Riley and Wolseley grouping explains many of the shared models and components.'],
            'de' => ['alt' => 'Darstellung der Marken der British Motor Corporation', 'title' => 'Marken der BMC', 'caption' => 'Der Verbund von Austin, Morris, MG, Riley und Wolseley erklärt zahlreiche gemeinsame Modelle und Bauteile.'],
        ],
        'austin_general' => [
            'fr' => ['alt' => 'Évolution des emblèmes Austin', 'title' => 'Emblèmes Austin', 'caption' => 'Les emblèmes Austin changent avec les périodes ; ils situent une marque dont la gamme a couvert des automobiles très différentes.'],
            'en' => ['alt' => 'Development of Austin badges', 'title' => 'Austin badges', 'caption' => 'Austin badges changed over time across a marque that produced very different kinds of car.'],
            'de' => ['alt' => 'Entwicklung der Austin-Embleme', 'title' => 'Austin-Embleme', 'caption' => 'Die Austin-Embleme veränderten sich über die Jahrzehnte einer Marke mit sehr unterschiedlichen Fahrzeugen.'],
        ],
    ];

    /**
     * @param array<string, mixed> $article
     * @return array{article: array<string, mixed>, changed: bool}
     */
    public function backfill(array $article): array
    {
        $pageSlug = (string) ($article['page_slug'] ?? '');
        if (!in_array($pageSlug, ['auto-retro-austin-aventure-mini-austin', 'auto-retro-austin-histoire-de-austin'], true)) {
            return ['article' => $article, 'changed' => false];
        }

        $profile = $this->profile((string) ($article['slug'] ?? ''), $pageSlug);
        $language = strtolower(trim((string) ($article['lang'] ?? '')));
        $copy = self::COPY[$profile][$language] ?? self::COPY[$profile]['fr'];
        $media = self::PROFILES[$profile];
        $changed = false;

        $featured = is_array($article['featured_image'] ?? null) ? $article['featured_image'] : [];
        if (trim((string) ($featured['src'] ?? '')) === '') {
            $article['featured_image'] = $media['feature'] + $copy;
            $changed = true;
        }

        $content = (string) ($article['content'] ?? '');
        if (preg_match('/<img\b/i', $content) !== 1) {
            $bodyCopy = $this->bodyCopy($profile, $language);
            $body = $media['body'];
            $figure = sprintf(
                '<figure><img src="%s" alt="%s" title="%s" width="%d" height="%d" loading="lazy" decoding="async" fetchpriority="low" /><figcaption>%s</figcaption></figure>',
                $body['src'],
                htmlspecialchars($bodyCopy['alt'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'),
                htmlspecialchars($bodyCopy['title'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'),
                $body['width'],
                $body['height'],
                htmlspecialchars($bodyCopy['caption'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8')
            );
            $content = preg_replace('/<\/p>/i', '</p>' . "\n" . $figure, $content, 1) ?? ($figure . "\n" . $content);
            $article['content'] = $content;
            $changed = true;
        }

        return ['article' => $article, 'changed' => $changed];
    }

    private function profile(string $slug, string $pageSlug): string
    {
        if ($pageSlug === 'auto-retro-austin-aventure-mini-austin') {
            if (preg_match('/(?:cooper|rallye|competition|victoires|redoutable)/', $slug) === 1) {
                return 'mini_sport';
            }
            if (preg_match('/(?:cinema|culture|symbole|icone|conducteurs|accueil-public)/', $slug) === 1) {
                return 'mini_culture';
            }
            if (str_contains($slug, 'transition-mini-moderne')) {
                return 'mini_modern';
            }
            if (preg_match('/(?:creee-1959|issigonis|premieres-versions)/', $slug) === 1) {
                return 'mini_origin';
            }

            return 'mini_evolution';
        }
        if (str_contains($slug, 'seven')) {
            return 'austin_seven';
        }
        if (preg_match('/(?:histoire-complete|grandes-periodes)/', $slug) === 1) {
            return 'austin_early';
        }
        if (preg_match('/(?:industrie|disparu|british-leyland|marques-liees|longbridge|evolution-austin)/', $slug) === 1) {
            return 'austin_industry';
        }

        return 'austin_general';
    }

    /**
     * @return array{alt: string, title: string, caption: string}
     */
    private function bodyCopy(string $profile, string $language): array
    {
        $copy = self::COPY[$profile][$language] ?? self::COPY[$profile]['fr'];
        $suffix = match ($language) {
            'en' => ' — contextual illustration',
            'de' => ' — Abbildung zum historischen Zusammenhang',
            default => ' — illustration de contexte',
        };

        return [
            'alt' => $copy['alt'] . $suffix,
            'title' => $copy['title'],
            'caption' => $copy['caption'],
        ];
    }
}
