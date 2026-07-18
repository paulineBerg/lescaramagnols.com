<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\PrivateApps\Documents;

use Caramagnols\PrivateApps\Documents\Service\DocumentPolicy;
use Caramagnols\PrivateApps\Documents\Service\DocumentValidationService;
use PHPUnit\Framework\TestCase;

final class DocumentValidationServiceTest extends TestCase
{
    private string $workDirectory = '';

    protected function setUp(): void
    {
        $this->workDirectory = sys_get_temp_dir() . '/doc-validation-' . bin2hex(random_bytes(6));
        mkdir($this->workDirectory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->workDirectory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->workDirectory);
    }

    private function service(): DocumentValidationService
    {
        return new DocumentValidationService(new DocumentPolicy());
    }

    private function writeFixture(string $name, string $content): string
    {
        $path = $this->workDirectory . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }

    public function testValidTextFileIsAccepted(): void
    {
        $path = $this->writeFixture('notes.txt', "Relevé de charges 2026\n");
        $result = $this->service()->validateFile($path, 'notes.txt');

        self::assertTrue($result->valid, $result->errorCode);
        self::assertSame('txt', $result->extension);
        self::assertSame('notes.txt', $result->originalName);
    }

    public function testValidPdfIsAccepted(): void
    {
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
        $path = $this->writeFixture('facture.pdf', $pdf);
        $result = $this->service()->validateFile($path, 'facture.pdf');

        self::assertTrue($result->valid, $result->errorCode);
        self::assertSame('application/pdf', $result->mimeType);
    }

    public function testEncryptedPdfIsRejected(): void
    {
        $pdf = "%PDF-1.4\ntrailer\n<< /Encrypt 5 0 R /Root 1 0 R >>\n%%EOF\n";
        $path = $this->writeFixture('protege.pdf', $pdf);
        $result = $this->service()->validateFile($path, 'protege.pdf');

        self::assertFalse($result->valid);
        self::assertSame('encrypted_document', $result->errorCode);
    }

    public function testForbiddenExtensionIsRejected(): void
    {
        $path = $this->writeFixture('script.php', "<?php echo 'x';\n");
        $result = $this->service()->validateFile($path, 'script.php');

        self::assertFalse($result->valid);
        self::assertSame('forbidden_extension', $result->errorCode);
    }

    public function testExtensionContentMismatchIsRejected(): void
    {
        // Contenu texte déguisé en PDF : signature magique absente.
        $path = $this->writeFixture('faux.pdf', "juste du texte\n");
        $result = $this->service()->validateFile($path, 'faux.pdf');

        self::assertFalse($result->valid);
        self::assertContains($result->errorCode, ['mime_mismatch', 'invalid_signature']);
    }

    public function testPngSignatureIsChecked(): void
    {
        $png = "\x89PNG\r\n\x1a\n" . base64_decode(
            'AAAADUlIRFIAAAABAAAAAQgGAAAAHxXEiQAAAA1JREFUeJxjYGBgYAAAAAUAAYebnaoAAAAASUVORK5CYII=',
            true
        );
        $path = $this->writeFixture('pixel.png', (string) $png);
        $result = $this->service()->validateFile($path, 'pixel.png');

        self::assertTrue($result->valid, $result->errorCode);
        self::assertSame('image/png', $result->mimeType);
    }

    public function testDocxWithMacroIsRejected(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('Extension zip absente.');
        }

        $path = $this->workDirectory . '/macro.docx';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types/>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><document/>');
        $zip->addFromString('word/vbaProject.bin', 'macro-binaire');
        $zip->close();

        $result = $this->service()->validateFile($path, 'macro.docx');

        self::assertFalse($result->valid);
        self::assertSame('macro_detected', $result->errorCode);
    }

    public function testDocxWithoutContentTypesIsRejected(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('Extension zip absente.');
        }

        $path = $this->workDirectory . '/invalide.docx';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('nimporte.txt', 'zip qui se fait passer pour un docx');
        $zip->close();

        $result = $this->service()->validateFile($path, 'invalide.docx');

        self::assertFalse($result->valid);
        self::assertSame('invalid_container_structure', $result->errorCode);
    }

    public function testBinaryContentInTextIsRejected(): void
    {
        $path = $this->writeFixture('binaire.txt', "texte\x00binaire");
        $result = $this->service()->validateFile($path, 'binaire.txt');

        self::assertFalse($result->valid);
        self::assertContains($result->errorCode, ['binary_content_in_text', 'mime_mismatch']);
    }

    public function testOriginalNameNormalization(): void
    {
        $service = $this->service();
        self::assertSame('facture eau.pdf', $service->normalizeOriginalName("facture\teau.pdf"));
        self::assertSame('', $service->normalizeOriginalName('..'));
        self::assertSame('a. .b.txt', $service->normalizeOriginalName('a...\\..b.txt'));
        self::assertSame('', $service->normalizeOriginalName(str_repeat('a', 300) . '.pdf'));
    }
}
