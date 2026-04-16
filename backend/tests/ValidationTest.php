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
        $this->assertSame('<strong>Hello</strong><br>World', $result['data']['content']);
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
