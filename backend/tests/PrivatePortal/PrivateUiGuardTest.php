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
        self::assertStringContainsString('.private-main > .private-screen-notice', $stylesheet);
        self::assertStringContainsString('top: 4.35rem;', $stylesheet);
        self::assertStringContainsString(
            'grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr));',
            $stylesheet
        );
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

        self::assertSame(2, substr_count($layout, 'notice notice-success private-screen-notice" role="status"'));
        self::assertSame(2, substr_count($layout, 'notice notice-error private-screen-notice" role="alert"'));
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
        self::assertMatchesRegularExpression(
            '/@media \\(max-width: 720px\\)\\s*\\{.*?nav\\.admin-nav\\s*\\{[^}]*position:\\s*static;/s',
            $layout
        );
        self::assertMatchesRegularExpression(
            '/@media \\(max-width: 720px\\)\\s*\\{.*?\\.admin-content\\s*\\{[^}]*max-width:\\s*100%;/s',
            $layout
        );
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
