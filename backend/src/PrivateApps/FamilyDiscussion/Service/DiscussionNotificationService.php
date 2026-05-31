<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\FamilyDiscussion\Service;

use Caramagnols\PrivateApps\FamilyDiscussion\Repository\DiscussionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;

final class DiscussionNotificationService
{
    /**
     * @param \Closure(string, string, string, int): bool $mailSender
     */
    public function __construct(
        private readonly DiscussionRepository $repository,
        private readonly PrivateUserRepository $userRepository,
        private readonly \Closure $mailSender
    ) {
    }

    /**
     * @param array<string, mixed> $message
     * @return array{sent:int,skipped:int,failed:int}
     */
    public function notifyNewMessage(array $message, int $actorId): array
    {
        $conversationId = is_numeric($message['conversationId'] ?? null) ? (int) $message['conversationId'] : 0;
        if ($conversationId <= 0 || $actorId <= 0) {
            return ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;
        foreach ($this->repository->listConversationMembers($conversationId) as $member) {
            $recipientId = is_numeric($member['privateUserId'] ?? null) ? (int) $member['privateUserId'] : 0;
            if ($recipientId <= 0 || $recipientId === $actorId) {
                ++$skipped;
                continue;
            }

            $preference = $this->repository->notificationPreferenceForUser($conversationId, $recipientId);
            if (($preference['mode'] ?? 'notify') !== 'notify') {
                ++$skipped;
                continue;
            }

            $user = $this->userRepository->findById($recipientId);
            $email = is_string($user['email'] ?? null) ? strtolower(trim((string) $user['email'])) : '';
            $status = is_string($user['status'] ?? null) ? strtolower(trim((string) $user['status'])) : '';
            if ($email === '' || $status !== 'active' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                ++$skipped;
                continue;
            }

            $variables = $this->variables($email, $conversationId);
            $subject = $this->renderTemplate(
                $this->privateMailTemplate('discussion_message_subject', 'Nouveau message dans les discussions famille'),
                $variables
            );
            $body = $this->renderTemplate(
                $this->privateMailTemplate(
                    'discussion_message_body',
                    "Bonjour,\n\nUn nouveau message est disponible dans une conversation privée {{site_name}}.\n\nOuvrir la conversation : {{conversation_url}}\n\nPour toute question, vous pouvez écrire à {{reply_to}}."
                ),
                $variables
            );

            $delivered = $this->send($email, $this->sanitizeSubject($subject), $this->plainTextToHtml($body), $recipientId);
            if ($delivered) {
                ++$sent;
            } else {
                ++$failed;
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped, 'failed' => $failed];
    }

    /**
     * @return array<string, scalar|null>
     */
    private function variables(string $email, int $conversationId): array
    {
        $conversationUrl = function_exists('private_portal_url')
            ? private_portal_url('discussion_index') . '/' . $conversationId
            : '/private/discussions/' . $conversationId;
        $loginUrl = function_exists('private_portal_url') ? private_portal_url('login') : '/private/login';

        return [
            'conversation_url' => function_exists('app_url') ? app_url($conversationUrl) : $conversationUrl,
            'email' => $email,
            'login_url' => function_exists('app_url') ? app_url($loginUrl) : $loginUrl,
            'private_url' => function_exists('app_url') ? app_url($loginUrl) : $loginUrl,
            'reply_to' => (string) app_config('private.mail.reply_to', 'private@lescaramagnols.com'),
            'site_name' => (string) app_config('site.name', 'Les Caramagnols'),
            'today' => date('d/m/Y'),
        ];
    }

    private function privateMailTemplate(string $key, string $fallback): string
    {
        $template = app_config('private.mail.templates.' . $key, $fallback);

        return is_scalar($template) ? (string) $template : $fallback;
    }

    /**
     * @param array<string, scalar|null> $variables
     */
    private function renderTemplate(string $template, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{{' . $key . '}}'] = (string) $value;
        }

        return strtr($template, $replacements);
    }

    private function sanitizeSubject(string $subject): string
    {
        return sanitize_text_field($subject, 180);
    }

    private function plainTextToHtml(string $message): string
    {
        return '<p>' . str_replace("\n", '<br>', htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>';
    }

    private function send(string $email, string $subject, string $html, int $recipientUserId): bool
    {
        try {
            return (bool) ($this->mailSender)($email, $subject, $html, $recipientUserId);
        } catch (\Throwable) {
            return false;
        }
    }
}
