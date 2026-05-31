<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\Service;

use Caramagnols\Logging\AppEventLogger;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLifecycleRepository;

final class RentalPaymentRequestService
{
    /** @var array<int, string> */
    private const REQUESTABLE_RENT_STATUSES = ['pending', 'partial', 'late'];

    public function __construct(
        private readonly RentalLifecycleRepository $repository,
        private readonly string $subjectTemplate,
        private readonly string $bodyTemplate,
        private readonly string $signatureTemplate,
        private readonly mixed $mailSender = null
    ) {
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<string, mixed>|null
     */
    public function previewForRent(int $rentId, array $propertyIds): ?array
    {
        $rent = $this->repository->findRentDetailsById($rentId, $propertyIds);
        if (!is_array($rent) || !$this->isRequestableRent($rent)) {
            return null;
        }

        $recipientEmail = $this->normalizeEmail($rent['tenantEmail'] ?? null);
        $variables = $this->variables($rent, $recipientEmail);
        $subject = sanitize_text_field($this->renderTemplate($this->subjectTemplate, $variables), 180);
        $message = sanitize_text_field($this->renderTemplate($this->bodyTemplate, $variables), 6000);
        $signature = sanitize_text_field($this->renderTemplate($this->signatureTemplate, $variables), 1000);

        return $this->previewPayload($rent, $recipientEmail, $subject, $message, $signature);
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array{status:string, request: array<string, mixed>|null, preview: array<string, mixed>|null, recipient:string}
     */
    public function send(
        int $rentId,
        array $propertyIds,
        string $recipientEmail,
        string $subject,
        string $message,
        string $signature,
        int $actorPrivateUserId
    ): array {
        $preview = $this->previewForRent($rentId, $propertyIds);
        if (!is_array($preview) || $actorPrivateUserId <= 0) {
            return $this->result('invalid_rent', null, $preview, '');
        }

        $recipientEmail = $this->normalizeEmail($recipientEmail !== '' ? $recipientEmail : ($preview['recipientEmail'] ?? null));
        if ($recipientEmail === '') {
            return $this->result('invalid_email', null, $preview, '');
        }

        $subject = sanitize_text_field($subject !== '' ? $subject : (string) ($preview['subject'] ?? ''), 180);
        $message = sanitize_text_field($message !== '' ? $message : (string) ($preview['message'] ?? ''), 6000);
        $signature = sanitize_text_field($signature !== '' ? $signature : (string) ($preview['signature'] ?? ''), 1000);
        if ($subject === '' || $message === '') {
            return $this->result('invalid_content', null, $preview, $recipientEmail);
        }

        $snapshot = $this->snapshot($preview, $recipientEmail, $subject, $message, $signature, 'email');
        $idempotencyKey = $this->idempotencyKey($snapshot);
        $existing = $this->repository->findPaymentRequestByIdempotencyKey($idempotencyKey);
        if (is_array($existing)) {
            return $this->result(($existing['status'] ?? '') === 'sent' ? 'duplicate' : 'failed', $existing, $preview, $recipientEmail);
        }

        $sent = $this->sendMail($recipientEmail, $subject, $message, $signature);
        $request = $this->repository->createPaymentRequestSnapshot(
            (int) ($preview['rentId'] ?? 0),
            (int) ($preview['leaseId'] ?? 0),
            (int) ($preview['propertyId'] ?? 0),
            (int) ($preview['unitId'] ?? 0),
            $recipientEmail,
            $subject,
            $message,
            $signature,
            'email',
            $sent ? 'sent' : 'failed',
            $idempotencyKey,
            $snapshot,
            $actorPrivateUserId,
            $sent ? null : 'email_transport_failed'
        );

        return $this->result($sent ? 'sent' : 'failed', $request, $preview, $recipientEmail);
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array{status:string, request: array<string, mixed>|null, preview: array<string, mixed>|null, recipient:string}
     */
    public function recordPdfExport(
        int $rentId,
        array $propertyIds,
        string $recipientEmail,
        string $subject,
        string $message,
        string $signature,
        int $actorPrivateUserId
    ): array {
        $preview = $this->previewForRent($rentId, $propertyIds);
        if (!is_array($preview) || $actorPrivateUserId <= 0) {
            return $this->result('invalid_rent', null, $preview, '');
        }

        $recipientEmail = $this->normalizeEmail($recipientEmail !== '' ? $recipientEmail : ($preview['recipientEmail'] ?? null));
        if ($recipientEmail === '') {
            return $this->result('invalid_email', null, $preview, '');
        }

        $subject = sanitize_text_field($subject !== '' ? $subject : (string) ($preview['subject'] ?? ''), 180);
        $message = sanitize_text_field($message !== '' ? $message : (string) ($preview['message'] ?? ''), 6000);
        $signature = sanitize_text_field($signature !== '' ? $signature : (string) ($preview['signature'] ?? ''), 1000);
        if ($subject === '' || $message === '') {
            return $this->result('invalid_content', null, $preview, $recipientEmail);
        }

        $snapshot = $this->snapshot($preview, $recipientEmail, $subject, $message, $signature, 'pdf');
        $request = $this->repository->createPaymentRequestSnapshot(
            (int) ($preview['rentId'] ?? 0),
            (int) ($preview['leaseId'] ?? 0),
            (int) ($preview['propertyId'] ?? 0),
            (int) ($preview['unitId'] ?? 0),
            $recipientEmail,
            $subject,
            $message,
            $signature,
            'pdf',
            'exported',
            $this->idempotencyKey($snapshot),
            $snapshot,
            $actorPrivateUserId
        );

        return $this->result($request !== null ? 'exported' : 'failed', $request, $preview, $recipientEmail);
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed>|null $preview
     */
    public function pdf(array $request, ?array $preview = null): string
    {
        $snapshot = $this->decodeSnapshot($request['snapshotPayload'] ?? null);
        $source = $snapshot !== [] ? $snapshot : ($preview ?? []);
        $subject = (string) ($request['subject'] ?? $source['subject'] ?? 'Demande de paiement');
        $message = (string) ($request['body'] ?? $source['message'] ?? '');
        $signature = (string) ($request['signature'] ?? $source['signature'] ?? '');
        $text = sprintf(
            "%s\n\nLocataire: %s\nPeriode: %s\nBien: %s - %s\nMontant attendu: %s EUR\nMontant encaisse: %s EUR\nSolde demande: %s EUR\nEcheance: %s\n\n%s%s",
            $subject,
            (string) ($source['tenantName'] ?? ''),
            (string) ($source['periodLabel'] ?? ''),
            (string) ($source['propertyName'] ?? ''),
            (string) ($source['unitLabel'] ?? ''),
            (string) ($source['amountDue'] ?? ''),
            (string) ($source['amountPaid'] ?? ''),
            (string) ($source['balanceDue'] ?? ''),
            (string) ($source['dueDate'] ?? ''),
            $message,
            $signature !== '' ? "\n\n" . $signature : ''
        );

        return "%PDF-1.4\n% Caramagnols private rental payment request\n" . $text . "\n%%EOF\n";
    }

    /**
     * @param array<string, mixed> $rent
     */
    private function isRequestableRent(array $rent): bool
    {
        $status = is_string($rent['status'] ?? null) ? (string) $rent['status'] : '';
        $amountDue = is_numeric($rent['amountDue'] ?? null) ? (float) $rent['amountDue'] : 0.0;
        $amountPaid = is_numeric($rent['amountPaid'] ?? null) ? (float) $rent['amountPaid'] : 0.0;

        return in_array($status, self::REQUESTABLE_RENT_STATUSES, true) && max(0.0, $amountDue - $amountPaid) > 0.001;
    }

    /**
     * @param array<string, mixed> $rent
     * @return array<string, scalar|null>
     */
    private function variables(array $rent, string $recipientEmail): array
    {
        $amountDue = is_numeric($rent['amountDue'] ?? null) ? (float) $rent['amountDue'] : 0.0;
        $amountPaid = is_numeric($rent['amountPaid'] ?? null) ? (float) $rent['amountPaid'] : 0.0;
        $balanceDue = max(0.0, $amountDue - $amountPaid);

        return [
            'email' => $recipientEmail,
            'tenant_name' => is_string($rent['tenantName'] ?? null) ? (string) $rent['tenantName'] : 'Locataire',
            'property_name' => is_string($rent['propertyName'] ?? null) ? (string) $rent['propertyName'] : '',
            'property_address' => is_string($rent['propertyAddress'] ?? null) ? (string) $rent['propertyAddress'] : '',
            'unit_label' => is_string($rent['unitLabel'] ?? null) ? (string) $rent['unitLabel'] : '',
            'period' => $this->periodLabel($rent),
            'due_date' => is_string($rent['dueDate'] ?? null) ? (string) $rent['dueDate'] : '',
            'amount_due' => $this->formatAmount($amountDue),
            'amount_paid' => $this->formatAmount($amountPaid),
            'balance_due' => $this->formatAmount($balanceDue),
            'today' => date('d/m/Y'),
            'site_name' => function_exists('app_config') ? (string) app_config('site.name', 'Les Caramagnols') : 'Les Caramagnols',
            'reply_to' => function_exists('app_config') ? (string) app_config('private.mail.reply_to', 'private@lescaramagnols.com') : 'private@lescaramagnols.com',
        ];
    }

    /**
     * @param array<string, mixed> $rent
     * @return array<string, mixed>
     */
    private function previewPayload(array $rent, string $recipientEmail, string $subject, string $message, string $signature): array
    {
        $amountDue = is_numeric($rent['amountDue'] ?? null) ? (float) $rent['amountDue'] : 0.0;
        $amountPaid = is_numeric($rent['amountPaid'] ?? null) ? (float) $rent['amountPaid'] : 0.0;
        $balanceDue = max(0.0, $amountDue - $amountPaid);

        return [
            'rentId' => is_numeric($rent['id'] ?? null) ? (int) $rent['id'] : 0,
            'leaseId' => is_numeric($rent['rentalLeaseId'] ?? null) ? (int) $rent['rentalLeaseId'] : 0,
            'propertyId' => is_numeric($rent['rentalPropertyId'] ?? null) ? (int) $rent['rentalPropertyId'] : 0,
            'unitId' => is_numeric($rent['rentalUnitId'] ?? null) ? (int) $rent['rentalUnitId'] : 0,
            'tenantName' => is_string($rent['tenantName'] ?? null) ? (string) $rent['tenantName'] : '',
            'recipientEmail' => $recipientEmail,
            'propertyName' => is_string($rent['propertyName'] ?? null) ? (string) $rent['propertyName'] : '',
            'propertyAddress' => is_string($rent['propertyAddress'] ?? null) ? (string) $rent['propertyAddress'] : '',
            'unitLabel' => is_string($rent['unitLabel'] ?? null) ? (string) $rent['unitLabel'] : '',
            'periodLabel' => $this->periodLabel($rent),
            'dueDate' => is_string($rent['dueDate'] ?? null) ? (string) $rent['dueDate'] : '',
            'amountDue' => $this->formatAmount($amountDue),
            'amountPaid' => $this->formatAmount($amountPaid),
            'balanceDue' => $this->formatAmount($balanceDue),
            'subject' => $subject,
            'message' => $message,
            'signature' => $signature,
        ];
    }

    /**
     * @param array<string, mixed> $preview
     * @return array<string, mixed>
     */
    private function snapshot(array $preview, string $recipientEmail, string $subject, string $message, string $signature, string $channel): array
    {
        return [
            'rentId' => (int) ($preview['rentId'] ?? 0),
            'leaseId' => (int) ($preview['leaseId'] ?? 0),
            'propertyId' => (int) ($preview['propertyId'] ?? 0),
            'unitId' => (int) ($preview['unitId'] ?? 0),
            'tenantName' => (string) ($preview['tenantName'] ?? ''),
            'recipientEmailMasked' => AppEventLogger::maskIdentifier($recipientEmail),
            'propertyName' => (string) ($preview['propertyName'] ?? ''),
            'propertyAddress' => (string) ($preview['propertyAddress'] ?? ''),
            'unitLabel' => (string) ($preview['unitLabel'] ?? ''),
            'periodLabel' => (string) ($preview['periodLabel'] ?? ''),
            'dueDate' => (string) ($preview['dueDate'] ?? ''),
            'amountDue' => (string) ($preview['amountDue'] ?? ''),
            'amountPaid' => (string) ($preview['amountPaid'] ?? ''),
            'balanceDue' => (string) ($preview['balanceDue'] ?? ''),
            'subject' => $subject,
            'message' => $message,
            'signature' => $signature,
            'channel' => $channel,
            'snapshotAt' => date('c'),
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function idempotencyKey(array $snapshot): string
    {
        unset($snapshot['snapshotAt']);

        return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: serialize($snapshot));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSnapshot(mixed $payload): array
    {
        if (!is_string($payload) || trim($payload) === '') {
            return [];
        }

        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
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

    private function sendMail(string $recipientEmail, string $subject, string $message, string $signature): bool
    {
        if (!is_callable($this->mailSender)) {
            return false;
        }

        $html = '<p>' . nl2br(htmlspecialchars(trim($message . "\n\n" . $signature), ENT_QUOTES, 'UTF-8'), false) . '</p>';

        return (bool) ($this->mailSender)($recipientEmail, $subject, $html, []);
    }

    private function periodLabel(array $rent): string
    {
        $year = is_numeric($rent['periodYear'] ?? null) ? (int) $rent['periodYear'] : 0;
        $month = is_numeric($rent['periodMonth'] ?? null) ? (int) $rent['periodMonth'] : 0;
        if ($year <= 0 || $month <= 0) {
            return '';
        }

        return sprintf('%02d/%04d', $month, $year);
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function normalizeEmail(mixed $value): string
    {
        $email = strtolower(trim((string) $value));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : '';
    }

    /**
     * @param array<string, mixed>|null $request
     * @param array<string, mixed>|null $preview
     * @return array{status:string, request: array<string, mixed>|null, preview: array<string, mixed>|null, recipient:string}
     */
    private function result(string $status, ?array $request, ?array $preview, string $recipientEmail): array
    {
        return [
            'status' => $status,
            'request' => $request,
            'preview' => $preview,
            'recipient' => $recipientEmail,
        ];
    }
}
