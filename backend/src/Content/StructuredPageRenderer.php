<?php

declare(strict_types=1);

namespace Caramagnols\Content;

use Caramagnols\Http\PublicUrlNormalizer;

final class StructuredPageRenderer
{
    private const ALLOWED_HTML_TAGS = [
        'strong',
        'em',
        'b',
        'i',
        'br',
        'ul',
        'ol',
        'li',
        'p',
        'div',
        'span',
        'h1',
        'h2',
        'h3',
        'h4',
        'img',
        'figure',
        'figcaption',
        'video',
        'source',
        'a',
        'blockquote',
        'code',
        'iframe',
    ];

    /**
     * @var array<int, string>
     */
    private const IFRAME_YOUTUBE_HOSTS = [
        'youtube.com',
        'www.youtube.com',
        'm.youtube.com',
        'youtube-nocookie.com',
        'www.youtube-nocookie.com',
    ];

    /**
     * @var array<int, string>
     */
    private const IFRAME_ALLOWED_REFERRER_POLICIES = [
        'no-referrer',
        'no-referrer-when-downgrade',
        'origin',
        'origin-when-cross-origin',
        'same-origin',
        'strict-origin',
        'strict-origin-when-cross-origin',
        'unsafe-url',
    ];

    /**
     * @param array<string, mixed> $regions
     * @return array<string, string>
     */
    public function renderRegions(array $regions, ?string $pageRoute = null): array
    {
        $blocks = [];

        foreach ($regions as $regionName => $value) {
            $slot = StandardPageLayout::semanticSlots()[$regionName] ?? null;
            if ($slot === null) {
                continue;
            }

            $blocks[$slot] = $this->renderRegionValue($value, $pageRoute);
        }

        return $blocks;
    }

    private function renderRegionValue(mixed $value, ?string $pageRoute = null): string
    {
        if (is_string($value)) {
            return $this->sanitizeRichText($value, $pageRoute);
        }

        if (!is_array($value)) {
            return '';
        }

        if (array_is_list($value)) {
            $html = '';

            foreach ($value as $item) {
                $html .= $this->renderRegionValue($item, $pageRoute);
            }

            return $html;
        }

        if (isset($value['component']) && is_string($value['component'])) {
            return $this->renderComponent($value, $pageRoute);
        }

        if (isset($value['html']) && is_string($value['html'])) {
            return $this->sanitizeRichText($value['html'], $pageRoute);
        }

        return '';
    }

    /**
     * @param array<string, mixed> $component
     */
    private function renderComponent(array $component, ?string $pageRoute = null): string
    {
        return match ($component['component']) {
            'heading' => $this->renderHeading($component),
            'facts' => $this->renderFacts($component),
            'rich_text' => $this->renderRichTextComponent($component, $pageRoute),
            'contact_form' => $this->renderContactForm($component),
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $component
     */
    private function renderHeading(array $component): string
    {
        $title = trim((string) ($component['title'] ?? ''));
        $subtitle = trim((string) ($component['subtitle'] ?? ''));
        $lead = trim((string) ($component['lead'] ?? $component['text'] ?? ''));
        $image = is_array($component['image'] ?? null) ? $component['image'] : null;

        if ($title === '' && $subtitle === '' && $lead === '' && $image === null) {
            return '';
        }

        $html = '<div class="content-heading">';

        if ($image !== null) {
            $imageHtml = $this->renderImage($image, true);
            if ($imageHtml !== '') {
                $html .= '<div class="content-heading-media">' . $imageHtml . '</div>';
            }
        }

        $html .= '<div class="content-heading-text">';

        if ($title !== '') {
            $html .= '<h1>' . $this->escape($title) . '</h1>';
        }

        if ($subtitle !== '') {
            $html .= '<p class="content-heading-subtitle">' . $this->escape($subtitle) . '</p>';
        }

        if ($lead !== '') {
            $html .= '<div class="content-heading-lead"><p>' . $this->escape($lead) . '</p></div>';
        }

        $html .= '</div></div>';

        return $html;
    }

    /**
     * @param array<string, mixed> $component
     */
    private function renderFacts(array $component): string
    {
        $items = is_array($component['items'] ?? null) ? $component['items'] : [];
        if ($items === []) {
            return '';
        }

        $title = trim((string) ($component['title'] ?? ''));
        $html = '<div class="content-callout">';

        if ($title !== '') {
            $html .= '<h2 class="content-callout-title">' . $this->escape($title) . '</h2>';
        }

        $html .= '<dl class="content-facts">';

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));
            $value = trim((string) ($item['value'] ?? ''));

            if ($label === '' || $value === '') {
                continue;
            }

            $html .= '<div class="content-facts-item">';
            $html .= '<dt>' . $this->escape($label) . '</dt>';
            $html .= '<dd>' . $this->sanitizeRichText($value) . '</dd>';
            $html .= '</div>';
        }

        $html .= '</dl></div>';

        return $html;
    }

    /**
     * @param array<string, mixed> $component
     */
    private function renderRichTextComponent(array $component, ?string $pageRoute = null): string
    {
        $html = (string) ($component['html'] ?? '');
        if ($html === '') {
            return '';
        }

        return $this->sanitizeRichText($html, $pageRoute);
    }

    /**
     * @param array<string, mixed> $component
     */
    private function renderContactForm(array $component): string
    {
        $texts = $this->contactFormTexts($component);
        $recipient = trim((string) ($component['recipient'] ?? 'contact@lescaramagnols.com'));
        $subject = trim((string) ($component['subject'] ?? 'Nouveau message de contact - Les Caramagnols'));
        $csrfScope = 'contact_form_block_' . hash('sha256', $recipient . '|' . $subject);
        $requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $sent = false;
        $error = null;
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));

        if ($requestMethod === 'POST' && (string) ($_POST['contact_form_submit'] ?? '') === '1') {
            $submittedToken = is_string($_POST['contact_form_token'] ?? null) ? $_POST['contact_form_token'] : null;

            if (!csrf_validate($submittedToken, $csrfScope, true)) {
                $error = $texts['invalid_token'];
            } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') {
                $error = $texts['required_fields'];
            } else {
                require_once ROOT_PATH . '/core/mailer.php';

                $htmlMessage = '<p><strong>Nom:</strong> ' . $this->escape($name) . '</p>'
                    . '<p><strong>Email:</strong> ' . $this->escape($email) . '</p>'
                    . '<p><strong>Message:</strong><br>' . nl2br($this->escape($message)) . '</p>';

                $sent = send_notification_email($recipient, $subject, $htmlMessage);

                if (!$sent) {
                    $error = $texts['send_error'];
                } else {
                    $name = '';
                    $email = '';
                    $message = '';
                }
            }
        }

        $csrfToken = csrf_token($csrfScope);

        $html = '<div class="contact-form-block">';

        if ($sent) {
            $html .= '<div class="alert alert-success">' . $this->escape($texts['success']) . '</div>';
        } elseif ($error !== null) {
            $html .= '<div class="alert alert-danger">' . $this->escape($error) . '</div>';
        }

        $html .= '<form method="post" class="contact-form-block-form">';
        $html .= '<input type="hidden" name="contact_form_submit" value="1">';
        $html .= '<input type="hidden" name="contact_form_token" value="'
            . $this->escapeAttribute($csrfToken) . '">';
        $html .= '<div class="mb-3">';
        $html .= '<label>' . $this->escape($texts['name_label']) . '</label>';
        $html .= '<input name="name" class="form-control" required value="' . $this->escapeAttribute($name) . '">';
        $html .= '</div>';
        $html .= '<div class="mb-3">';
        $html .= '<label>' . $this->escape($texts['email_label']) . '</label>';
        $html .= '<input name="email" type="email" class="form-control" required value="'
            . $this->escapeAttribute($email) . '">';
        $html .= '</div>';
        $html .= '<div class="mb-3">';
        $html .= '<label>' . $this->escape($texts['message_label']) . '</label>';
        $html .= '<textarea name="message" class="form-control" rows="5" required>'
            . $this->escape($message) . '</textarea>';
        $html .= '</div>';
        $html .= '<button class="btn btn-primary">' . $this->escape($texts['submit_label']) . '</button>';
        $html .= '</form></div>';

        return $html;
    }

    /**
     * @param array<string, mixed> $image
     */
    private function renderImage(array $image, bool $isCritical = false): string
    {
        $src = $this->sanitizeImageSrc((string) ($image['src'] ?? ''));
        if ($src === '') {
            return '';
        }

        $alt = trim((string) ($image['alt'] ?? ''));
        $title = trim((string) ($image['title'] ?? $alt));
        $width = isset($image['width']) ? max(1, (int) $image['width']) : null;
        $height = isset($image['height']) ? max(1, (int) $image['height']) : null;

        $attributes = [
            'src="' . $this->escapeAttribute($src) . '"',
            'alt="' . $this->escapeAttribute($alt) . '"',
            'loading="' . ($isCritical ? 'eager' : 'lazy') . '"',
            'decoding="async"',
            'fetchpriority="' . ($isCritical ? 'high' : 'low') . '"',
        ];

        if ($title !== '') {
            $attributes[] = 'title="' . $this->escapeAttribute($title) . '"';
        }

        if ($width !== null) {
            $attributes[] = 'width="' . $width . '"';
        }

        if ($height !== null) {
            $attributes[] = 'height="' . $height . '"';
        }

        return '<img ' . implode(' ', $attributes) . '>';
    }

    private function sanitizeRichText(string $html, ?string $pageRoute = null): string
    {
        $sanitized = trim($html);

        if (function_exists('sanitize_text_field')) {
            $sanitized = sanitize_text_field($html, null, self::ALLOWED_HTML_TAGS);
        }

        $sanitized = PublicUrlNormalizer::rewriteHtmlFragment($sanitized, $pageRoute);

        return $this->sanitizeIframeEmbeds($sanitized);
    }

    private function sanitizeIframeEmbeds(string $html): string
    {
        if (stripos($html, '<iframe') === false) {
            return $html;
        }

        $sanitized = preg_replace_callback(
            '/<iframe\b([^>]*)>(?:\s*<\/iframe>)?/i',
            fn (array $matches): string => $this->sanitizeIframeTag((string) $matches[1]),
            $html
        );

        return is_string($sanitized) ? $sanitized : $html;
    }

    private function sanitizeIframeTag(string $rawAttributes): string
    {
        $attributes = $this->parseHtmlAttributes($rawAttributes);
        $src = $this->sanitizeIframeSrc((string) ($attributes['src'] ?? ''));
        if ($src === '') {
            return '';
        }

        $tagAttributes = [
            'src="' . $this->escapeAttribute($src) . '"',
            'loading="' . $this->escapeAttribute($this->sanitizeIframeLoading((string) ($attributes['loading'] ?? ''))) . '"',
            'referrerpolicy="' . $this->escapeAttribute($this->sanitizeIframeReferrerPolicy((string) ($attributes['referrerpolicy'] ?? ''))) . '"',
        ];

        $title = trim((string) ($attributes['title'] ?? ''));
        if ($title !== '') {
            $tagAttributes[] = 'title="' . $this->escapeAttribute($title) . '"';
        }

        $allow = $this->sanitizeIframeAllow((string) ($attributes['allow'] ?? ''));
        if ($allow !== '') {
            $tagAttributes[] = 'allow="' . $this->escapeAttribute($allow) . '"';
        }

        $width = trim((string) ($attributes['width'] ?? ''));
        if ($width !== '' && preg_match('/^[1-9][0-9]{0,4}$/', $width) === 1) {
            $tagAttributes[] = 'width="' . $width . '"';
        }

        $height = trim((string) ($attributes['height'] ?? ''));
        if ($height !== '' && preg_match('/^[1-9][0-9]{0,4}$/', $height) === 1) {
            $tagAttributes[] = 'height="' . $height . '"';
        }

        $frameBorder = trim((string) ($attributes['frameborder'] ?? ''));
        if ($frameBorder !== '' && preg_match('/^[01]$/', $frameBorder) === 1) {
            $tagAttributes[] = 'frameborder="' . $frameBorder . '"';
        }

        if (array_key_exists('allowfullscreen', $attributes)) {
            $tagAttributes[] = 'allowfullscreen';
        }

        return '<iframe ' . implode(' ', $tagAttributes) . '></iframe>';
    }

    /**
     * @return array<string, string>
     */
    private function parseHtmlAttributes(string $rawAttributes): array
    {
        $attributes = [];
        preg_match_all(
            '/([a-zA-Z_:][a-zA-Z0-9:._-]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?/',
            $rawAttributes,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $name = strtolower((string) $match[1]);
            if ($name === '' || array_key_exists($name, $attributes)) {
                continue;
            }

            $value = '';
            if (isset($match[2]) && $match[2] !== '') {
                $value = (string) $match[2];
            } elseif (isset($match[3]) && $match[3] !== '') {
                $value = (string) $match[3];
            } elseif (isset($match[4]) && $match[4] !== '') {
                $value = (string) $match[4];
            }

            $attributes[$name] = trim($value);
        }

        return $attributes;
    }

    private function sanitizeIframeSrc(string $src): string
    {
        $src = trim(html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($src === '') {
            return '';
        }

        if (str_starts_with($src, '//')) {
            $src = 'https:' . $src;
        }

        if (filter_var($src, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        $parts = parse_url($src);
        if (!is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        $query = isset($parts['query']) && is_string($parts['query']) ? '?' . $parts['query'] : '';

        if ($scheme !== 'https' || $host === '' || $path === '') {
            return '';
        }

        if (!in_array($host, self::IFRAME_YOUTUBE_HOSTS, true)) {
            return '';
        }

        if (preg_match('#^/embed/[A-Za-z0-9_-]+$#', $path) !== 1) {
            return '';
        }

        // Canonicalise vers youtube-nocookie pour rester coherent avec la CSP.
        return 'https://www.youtube-nocookie.com' . $path . $query;
    }

    private function sanitizeIframeAllow(string $allow): string
    {
        $allow = strtolower(trim((string) preg_replace('/[^a-z0-9;\-\s]/i', '', $allow)));
        if ($allow === '') {
            return '';
        }

        $allowedTokens = [
            'accelerometer',
            'autoplay',
            'clipboard-write',
            'encrypted-media',
            'gyroscope',
            'picture-in-picture',
            'web-share',
            'fullscreen',
        ];

        $tokens = [];
        foreach (explode(';', $allow) as $token) {
            $token = trim($token);
            if ($token === '' || !in_array($token, $allowedTokens, true)) {
                continue;
            }

            $tokens[$token] = true;
        }

        return implode('; ', array_keys($tokens));
    }

    private function sanitizeIframeLoading(string $loading): string
    {
        $loading = strtolower(trim($loading));

        return in_array($loading, ['lazy', 'eager'], true) ? $loading : 'lazy';
    }

    private function sanitizeIframeReferrerPolicy(string $policy): string
    {
        $policy = strtolower(trim($policy));

        return in_array($policy, self::IFRAME_ALLOWED_REFERRER_POLICIES, true)
            ? $policy
            : 'strict-origin-when-cross-origin';
    }

    private function sanitizeImageSrc(string $src): string
    {
        return PublicUrlNormalizer::normalizeImageSource($src);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param array<string, mixed> $component
     * @return array<string, string>
     */
    private function contactFormTexts(array $component): array
    {
        $defaults = [
            'name_label' => 'Nom',
            'email_label' => 'Email',
            'message_label' => 'Message',
            'submit_label' => 'Envoyer',
            'success' => 'Message envoyé avec succès.',
            'invalid_token' => 'Token invalide.',
            'required_fields' => 'Tous les champs sont obligatoires.',
            'send_error' => 'Erreur lors de l’envoi du message.',
        ];

        $texts = is_array($component['texts'] ?? null) ? $component['texts'] : [];

        foreach ($defaults as $key => $defaultValue) {
            $candidate = trim((string) ($texts[$key] ?? ''));
            $defaults[$key] = $candidate !== '' ? $candidate : $defaultValue;
        }

        return $defaults;
    }

    private function escapeAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
