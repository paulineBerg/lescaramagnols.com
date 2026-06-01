<?php

declare(strict_types=1);

namespace Caramagnols\Tests\PrivatePortal;

use PHPUnit\Framework\TestCase;

final class PrivateUiGuardTest extends TestCase
{
    public function testPrivateStylesDefineResponsiveUiContract(): void
    {
        $stylesheet = $this->readRepoFile('frontend/src/scss/private.scss');

        self::assertStringContainsString('overflow-x: hidden;', $stylesheet);
        self::assertMatchesRegularExpression('/\\.private-nav\\s*\\{[^}]*position:\\s*fixed;/s', $stylesheet);
        self::assertMatchesRegularExpression(
            '/\\.private-content\\s*\\{[^}]*margin-left:\\s*var\\(--private-nav-width\\);/s',
            $stylesheet
        );
        self::assertMatchesRegularExpression(
            '/\\.private-content\\s*\\{[^}]*max-width:\\s*calc\\(100% - var\\(--private-nav-width\\)\\);/s',
            $stylesheet
        );
        self::assertStringContainsString('.private-screen-notice', $stylesheet);
        self::assertStringContainsString('top: 4.35rem;', $stylesheet);
        self::assertStringContainsString(
            'grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr));',
            $stylesheet
        );
        self::assertStringContainsString('.private-module-dashboard', $stylesheet);
        self::assertStringContainsString('.private-list-filter-grid', $stylesheet);
        self::assertStringContainsString('.private-module-nav a.active', $stylesheet);
        self::assertStringContainsString('.private-nav-toggle', $stylesheet);
        self::assertStringContainsString('.private-nav-collapsed .private-nav', $stylesheet);
        self::assertStringContainsString('.private-nav-collapsed .private-content', $stylesheet);
        self::assertStringContainsString('background: #fff;', $stylesheet);
        self::assertMatchesRegularExpression(
            '/@media \\(width <= 900px\\)\\s*\\{.*?\\.private-nav\\s*\\{[^}]*position:\\s*static;/s',
            $stylesheet
        );
        self::assertMatchesRegularExpression(
            '/@media \\(width <= 900px\\)\\s*\\{.*?\\.private-content\\s*\\{[^}]*margin-left:\\s*0;/s',
            $stylesheet
        );
        self::assertMatchesRegularExpression(
            '/@media \\(width <= 900px\\)\\s*\\{.*?table\\s*\\{[^}]*overflow-x:\\s*auto;/s',
            $stylesheet
        );
    }

    public function testPrivateLayoutExposesVisibleStatusMessages(): void
    {
        $layout = $this->readRepoFile('backend/templates/private/layout.php');

        self::assertSame(1, substr_count($layout, 'notice notice-success private-screen-notice" role="status"'));
        self::assertSame(1, substr_count($layout, 'notice notice-error private-screen-notice" role="alert"'));
        self::assertStringNotContainsString('<main class="private-main">
            <?php if ($noticeText !== null)', $layout);
        self::assertGreaterThan(
            strpos($layout, "TXT_PRIVATE_NAV_TAX"),
            strpos($layout, "TXT_PRIVATE_SETTINGS_NAV"),
            'Le menu Paramètres doit rester le dernier item privé.'
        );
        self::assertMatchesRegularExpression(
            '/TXT_PRIVATE_NAV_DISCUSSIONS.*TXT_PRIVATE_DASHBOARD_DOCUMENTS_TITLE/s',
            $layout,
            'Le menu Documents doit venir apres Discussions.'
        );
        self::assertStringContainsString('data-private-filter-scope', $layout);
        self::assertStringContainsString('data-private-filter-row', $layout);
        self::assertStringContainsString('data-private-filter-empty', $layout);
        self::assertStringContainsString("field instanceof HTMLSelectElement && key !== 'text'", $layout);
        self::assertStringContainsString('caramagnols.private.filters.', $layout);
        self::assertStringContainsString('sessionStorage.setItem(storageKey', $layout);
        self::assertStringContainsString('data-private-nav-toggle', $layout);
        self::assertStringContainsString('type="button"', $layout);
        self::assertStringContainsString('caramagnols.private.navCollapsed', $layout);
        self::assertStringContainsString('privateSessionPingUrl', $layout);
        self::assertStringContainsString('sessionPingUrl', $layout);
        self::assertStringContainsString('sessionCsrfToken', $layout);
        self::assertStringContainsString('initSessionKeepAlive();', $layout);
        self::assertStringContainsString('data-private-auto-submit', $layout);
        self::assertStringContainsString('form.requestSubmit(submitter);', $layout);
        self::assertStringContainsString('data-private-dialog-auto-open', $layout);
    }

    public function testAgencyReviewLineFeedbackStaysNearReviewedLineWithoutAutoValidation(): void
    {
        $template = $this->readRepoFile('backend/templates/private/modules/real-estate-rental/agency-review.php');
        $stylesheet = $this->readRepoFile('frontend/src/scss/private.scss');

        self::assertStringContainsString('agencyReviewLineFeedbackId', $template);
        self::assertStringContainsString('agency-review-line-feedback', $template);
        self::assertStringNotContainsString('data-private-auto-submit="validate_line"', $template);
        self::assertStringContainsString('manual_fiscal_review_confirmed', $template);
        self::assertStringContainsString('bulk_update_lines', $template);
        self::assertStringContainsString('line_action[', $template);
        self::assertStringContainsString('lines[<?php echo $h($lineId); ?>][mapped_category]', $template);
        self::assertStringContainsString('.agency-review-line-feedback', $stylesheet);
        self::assertStringContainsString('scroll-margin-top: 6rem;', $stylesheet);
        self::assertStringContainsString('.agency-review-bulk-actions', $stylesheet);
    }

    public function testAgencyImportsExposeAgencyAndMappingControls(): void
    {
        $template = $this->readRepoFile('backend/templates/private/modules/real-estate-rental/agencies.php');
        $importsTemplate = $this->readRepoFile('backend/templates/private/modules/real-estate-rental/agency-imports.php');
        $nav = $this->readRepoFile('backend/templates/private/modules/real-estate-rental/_nav.php');

        self::assertStringContainsString('Créer une agence', $template);
        self::assertStringContainsString('Correspondances par agence', $template);
        self::assertStringContainsString('value="update_agency"', $template);
        self::assertStringContainsString('value="delete_agency"', $template);
        self::assertStringContainsString('advisor_name', $template);
        self::assertStringContainsString('postal_address', $template);
        self::assertStringContainsString('create_agency_unit_mapping', $template);
        self::assertStringContainsString('delete_agency_unit_mapping', $template);
        self::assertStringContainsString('Texte détecté dans le document', $template);
        self::assertStringContainsString('$url(\'agencies\', \'rental_agencies\')', $nav);
        self::assertStringContainsString('Gérer les agences', $importsTemplate);
        self::assertStringNotContainsString('$currentTab === \'agencies\'', $importsTemplate);
    }

    public function testPrivateSettingsExposeSmtpConfiguration(): void
    {
        $template = $this->readRepoFile('backend/templates/private/settings.php');

        self::assertStringContainsString('TXT_PRIVATE_SETTINGS_SMTP_TAB', $template);
        self::assertStringContainsString('name="smtp_host"', $template);
        self::assertStringContainsString('name="smtp_password"', $template);
        self::assertStringContainsString('name="send_test"', $template);
        self::assertStringContainsString('private-password-toggle', $template);
        self::assertStringContainsString(" ? '*****' : ''", $template);
        self::assertStringContainsString('notice notice-success private-screen-notice', $template);
        self::assertStringContainsString('notice notice-error private-screen-notice', $template);
        self::assertStringContainsString('private-settings-smtp-required-dialog', $template);
        self::assertStringContainsString('data-private-dialog-auto-open="1"', $template);
    }

    public function testRentalLeaseRentAndPaymentTemplatesExposeOperationalShortcuts(): void
    {
        $leases = $this->readRepoFile('backend/templates/private/modules/real-estate-rental/leases.php');
        $rents = $this->readRepoFile('backend/templates/private/modules/real-estate-rental/rents.php');
        $payments = $this->readRepoFile('backend/templates/private/modules/real-estate-rental/payments.php');

        self::assertStringContainsString('download_lease', $leases);
        self::assertStringContainsString('Réajustement annuel', $leases);
        self::assertStringContainsString('name="adjustment_month"', $leases);

        self::assertStringContainsString('period_month_picker', $rents);
        self::assertStringContainsString('data-rental-extra-lines', $rents);
        self::assertStringContainsString('Ajouter une ligne diverse', $rents);
        self::assertStringContainsString('?rent_id=', $rents);
        self::assertStringContainsString('rental-rent-actions', $rents);

        self::assertStringContainsString('download_receipt', $payments);
        self::assertStringContainsString('data-rental-payment-auto-open', $payments);
        self::assertStringContainsString('data-due-date=', $payments);
    }

    public function testRentalDashboardNavigationDoesNotExposeSubmenu(): void
    {
        $template = $this->readRepoFile('backend/templates/private/modules/real-estate-rental/_nav.php');

        self::assertStringContainsString('default => []', $template);
        self::assertStringContainsString('<?php if ($subItems !== []): ?>', $template);
    }

    public function testRentalPersonalNavigationDoesNotExposePropertyMembersMenu(): void
    {
        $template = $this->readRepoFile('backend/templates/private/modules/real-estate-rental/_nav.php');

        self::assertStringNotContainsString('Accès aux propriétés', $template);
        self::assertStringNotContainsString('rental_property_members', $template);
    }

    public function testFamilyDiscussionInterfaceKeepsKeyboardAndFocusContracts(): void
    {
        $template = $this->readRepoFile('backend/templates/private/modules/family-discussion/conversation.php');
        $stylesheet = $this->readRepoFile('frontend/src/scss/private-discussion.scss');

        self::assertStringContainsString('aria-labelledby="private-discussion-thread-title"', $template);
        self::assertStringContainsString('data-discussion-load-before', $template);
        self::assertMatchesRegularExpression(
            '/<button[^>]+type="button"[^>]+data-discussion-load-before/s',
            $template
        );
        self::assertStringContainsString('data-discussion-file-preview aria-live="polite"', $template);
        self::assertStringContainsString('data-discussion-submit-status role="status" aria-live="polite"', $template);
        self::assertStringContainsString('data-discussion-revoke-device', $template);
        self::assertMatchesRegularExpression(
            '/<button[^>]+type="button"[^>]+data-discussion-revoke-device/s',
            $template
        );
        self::assertStringContainsString('data-discussion-emoji-toggle', $template);
        self::assertStringContainsString('aria-controls="discussion-emoji-picker"', $template);
        self::assertStringContainsString('data-discussion-emoji-picker', $template);
        self::assertStringContainsString('private-discussion-textbar-actions', $template);
        self::assertSame(1, substr_count($template, 'data-discussion-submit-button'));
        self::assertLessThan(
            strpos($template, 'data-discussion-submit-button'),
            strpos($template, 'data-discussion-emoji-toggle'),
            'Le bouton Envoyer doit suivre le bouton Emojis dans la barre du message.'
        );
        self::assertStringContainsString('>Fil actif</a>', $template);
        self::assertStringNotContainsString('>Conversation</a>', $template);
        self::assertGreaterThan(
            strpos($template, 'data-discussion-crypto-status'),
            strpos($template, 'private-discussion-composer'),
            'Le bloc chiffrement local doit rester dans la colonne gauche, avant le compositeur.'
        );
        self::assertMatchesRegularExpression(
            '/<button[^>]+type="button"[^>]+data-discussion-emoji-toggle/s',
            $template
        );
        self::assertStringContainsString('.private-discussion-conversation-list a:focus-visible', $stylesheet);
        self::assertStringContainsString('.private-discussion-delete-button:focus-visible', $stylesheet);
        self::assertStringContainsString('.private-discussion-emoji-choice:focus-visible', $stylesheet);
        self::assertStringContainsString('.private-discussion-emoji-picker[hidden]', $stylesheet);
        self::assertStringContainsString('.private-discussion-textbar-actions', $stylesheet);
        self::assertStringContainsString('.private-discussion-send-button', $stylesheet);
        self::assertMatchesRegularExpression(
            '/\\.private-discussion-message\\s*\\{[^}]*align-self:\\s*stretch;[^}]*max-width:\\s*100%;[^}]*width:\\s*100%;/s',
            $stylesheet
        );
        self::assertStringContainsString('max-width: 100%;', $stylesheet);
        self::assertStringContainsString('@media (width <= 900px)', $stylesheet);
    }

    public function testAdminLayoutKeepsBackOfficeNavigationAndMessagesStable(): void
    {
        $layout = $this->readRepoFile('backend/templates/admin/layout.php');

        self::assertMatchesRegularExpression('/nav\\.admin-nav\\s*\\{[^}]*position:\\s*fixed;/s', $layout);
        self::assertMatchesRegularExpression(
            '/\\.admin-content\\s*\\{[^}]*margin-left:\\s*var\\(--admin-nav-width\\);/s',
            $layout
        );
        self::assertMatchesRegularExpression(
            '/\\.admin-content\\s*\\{[^}]*max-width:\\s*calc\\(100% - var\\(--admin-nav-width\\)\\);/s',
            $layout
        );
        self::assertStringContainsString('main.admin-main > .notice.notice-success', $layout);
        self::assertStringContainsString('data-admin-nav-toggle', $layout);
        self::assertStringContainsString('body.admin-nav-collapsed nav.admin-nav', $layout);
        self::assertStringContainsString('caramagnols.admin.navCollapsed', $layout);
        self::assertMatchesRegularExpression(
            '/@media \\(max-width: 720px\\)\\s*\\{.*?nav\\.admin-nav\\s*\\{[^}]*position:\\s*static;/s',
            $layout
        );
        self::assertMatchesRegularExpression(
            '/@media \\(max-width: 720px\\)\\s*\\{.*?\\.admin-content\\s*\\{[^}]*max-width:\\s*100%;/s',
            $layout
        );
        self::assertStringContainsString('data-admin-totp-generate', $layout);
        self::assertStringContainsString('window.crypto.getRandomValues(bytes);', $layout);
    }

    public function testAdminPrivateMemberDestructiveDialogUsesCentralButtonHandlers(): void
    {
        $template = $this->readRepoFile('backend/templates/admin/private_members_list.php');

        self::assertStringNotContainsString('onclick=', $template);
        self::assertStringContainsString('class="button-small button-danger"', $template);
        self::assertStringContainsString('type="button"', $template);
        self::assertStringContainsString('data-admin-private-delete-open=', $template);
        self::assertMatchesRegularExpression(
            '/<button\\s+class="button-small button-muted"\\s+type="button"\\s+data-admin-close-dialog/s',
            $template
        );
        self::assertStringContainsString('private_member_delete_confirm" value="1"', $template);
    }

    private function readRepoFile(string $relativePath): string
    {
        $path = dirname(__DIR__, 3) . '/' . $relativePath;
        $contents = file_get_contents($path);
        self::assertIsString($contents, sprintf('Unable to read %s', $relativePath));

        return $contents;
    }
}
