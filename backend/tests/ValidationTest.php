<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class ValidationTest extends TestCase
{
    public function testSanitizeCommentPayload(): void
    {
        $payload = [
            'author' => "  <b>John Doe</b>\n",
            'email' => 'John.DOE@example.com',
            'content' => "<script>alert('xss')</script><strong>Hello</strong><br>World"
        ];

        $result = sanitize_comment_payload($payload);

        $this->assertSame([], $result['errors']);
        $this->assertSame('John Doe', $result['data']['author']);
        $this->assertSame('john.doe@example.com', $result['data']['email']);
        $this->assertSame('HelloWorld', $result['data']['content']);
    }

    public function testSanitizeCommentPayloadNeutralizesDangerousHtmlTagsAndAttributes(): void
    {
        $payload = [
            'author' => '<em>Admin</em>',
            'email' => 'reader@example.com',
            'content' => "<script>alert('x')</script>\n<a href='https://example.com' onclick='steal()'>Lien</a>\n"
                . "<span class='x'>Texte</span><img src='x' onerror='alert(1)'>\n"
                . "<div style='color:red'>Bloc</div><br><style>body{}</style>\n"
                . 'Final',
        ];

        $result = sanitize_comment_payload($payload);

        $this->assertSame([], $result['errors']);
        $this->assertSame("Lien\nTexte\nBloc\nFinal", $result['data']['content']);
    }

    public function testSanitizeCommentPayloadRejectsInvalidEmail(): void
    {
        $result = sanitize_comment_payload([
            'author' => 'Jane',
            'email' => 'invalid-email',
            'content' => 'Hi'
        ]);

        $this->assertContains((string) t('TXT_BLOG_DISCUSSION_ERROR_EMAIL_INVALID'), $result['errors']);
    }

    public function testSanitizeTranslationArrayKeepsAllowedTags(): void
    {
        $input = [
            'intro' => "<h2>Title</h2><strong>Bold</strong><script>alert('x')</script>",
        ];

        $sanitized = sanitize_translation_array($input);

        $this->assertSame(
            "<h2>Title</h2><strong>Bold</strong>",
            $sanitized['intro']
        );
    }
}
