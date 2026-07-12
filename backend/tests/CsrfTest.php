<?php

declare(strict_types=1);

use Caramagnols\Http\Request;
use Caramagnols\Security\Csrf;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class CsrfTest extends TestCase
{
    public function testCsrfTokenAndValidation(): void
    {
        $token = Csrf::token('test-scope');

        $request = new Request(
            ['REQUEST_METHOD' => 'POST'],
            [],
            ['_csrf' => $token],
            [],
            [],
            []
        );

        $this->assertTrue(Csrf::validate($request, 'test-scope'));
    }
}
