<?php

declare(strict_types=1);

namespace Caramagnols\PbGestion\Protocol;

final class AgentErrorCodes
{
    public const REQUEST_REJECTED = 'request_rejected';
    public const JSON_INVALID = 'json_invalid';
    public const PAYLOAD_TOO_LARGE = 'payload_too_large';
    public const AGENT_UNKNOWN = 'agent_unknown';
    public const AGENT_REVOKED = 'agent_revoked';
    public const SIGNATURE_INVALID = 'signature_invalid';
    public const TIMESTAMP_OUT_OF_WINDOW = 'timestamp_out_of_window';
    public const SEQUENCE_REPLAY = 'sequence_replay';
    public const REQUEST_REPLAY = 'request_replay';
    public const COMMAND_UNKNOWN = 'command_unknown';
    public const COMMAND_PAYLOAD_INVALID = 'command_payload_invalid';
    public const COMMAND_EXPIRED = 'command_expired';
    public const ENROLLMENT_INVALID = 'enrollment_invalid';
    public const ENROLLMENT_EXPIRED = 'enrollment_expired';
    public const NETWORK_NOT_TRUSTED = 'network_not_trusted';
    public const COLLECTOR_EPOCH_STALE = 'collector_epoch_stale';

    /**
     * @return array<string, string>
     */
    public static function publicMessages(): array
    {
        return [
            self::REQUEST_REJECTED => 'La requete agent a ete refusee.',
            self::JSON_INVALID => 'Le corps JSON est invalide.',
            self::PAYLOAD_TOO_LARGE => 'Le corps de requete depasse la limite autorisee.',
            self::AGENT_UNKNOWN => 'Agent inconnu.',
            self::AGENT_REVOKED => 'Agent revoque.',
            self::SIGNATURE_INVALID => 'Signature invalide.',
            self::TIMESTAMP_OUT_OF_WINDOW => 'Horodatage hors fenetre.',
            self::SEQUENCE_REPLAY => 'Sequence agent invalide.',
            self::REQUEST_REPLAY => 'Requete deja recue.',
            self::COMMAND_UNKNOWN => 'Commande inconnue.',
            self::COMMAND_PAYLOAD_INVALID => 'Payload de commande invalide.',
            self::COMMAND_EXPIRED => 'Commande expiree.',
            self::ENROLLMENT_INVALID => 'Appairage invalide.',
            self::ENROLLMENT_EXPIRED => 'Code d appairage expire.',
            self::NETWORK_NOT_TRUSTED => 'Reseau non approuve.',
            self::COLLECTOR_EPOCH_STALE => 'Synthese collecteur obsolete.',
        ];
    }
}
