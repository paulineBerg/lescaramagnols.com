<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import;

final class AgencySensitiveDataMasker
{
    public function mask(string $text): string
    {
        $masked = $text;
        $masked = (string) preg_replace('/\b[A-Z]{2}\d{2}[A-Z0-9 ]{11,34}\b/u', '[iban masque]', $masked);
        $masked = (string) preg_replace('/([Mm]ot de passe\s*:\s*)\S+/u', '$1[masque]', $masked);
        $masked = (string) preg_replace('/([Cc]ode d[\'’]acc[eè]s\s*:\s*)\S+/u', '$1[masque]', $masked);
        $masked = (string) preg_replace('/([Nn]um[eé]ro fiscal\s*:?\s*)[0-9 ]{8,}/u', '$1[masque]', $masked);
        $masked = (string) preg_replace('/(\bSIRET\s*:?\s*)[0-9 ]{9,}/iu', '$1[masque]', $masked);
        $masked = (string) preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', '[email masque]', $masked);

        return $masked;
    }
}
