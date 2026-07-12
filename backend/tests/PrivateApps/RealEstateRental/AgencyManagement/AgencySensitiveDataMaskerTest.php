<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import\AgencySensitiveDataMasker;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../core/bootstrap.php';

final class AgencySensitiveDataMaskerTest extends TestCase
{
    public function testMasksSensitiveAgencyDataBeforePreviewOrLogs(): void
    {
        $text = implode("\n", [
            "Code d'accès : QUINETJULIETTE",
            'Mot de passe : SECRET123',
            'IBAN : FR76 1910 6000 1800 0000 0000 087',
            'Numéro fiscal : 1828793756384',
            'SIRET : 44204542300013',
            'Email contact@example.test',
        ]);

        $masked = (new AgencySensitiveDataMasker())->mask($text);

        $this->assertStringContainsString("Code d'accès : [masque]", $masked);
        $this->assertStringContainsString('Mot de passe : [masque]', $masked);
        $this->assertStringContainsString('[iban masque]', $masked);
        $this->assertStringNotContainsString('1828793756384', $masked);
        $this->assertStringNotContainsString('44204542300013', $masked);
        $this->assertStringContainsString('[email masque]', $masked);
    }
}
