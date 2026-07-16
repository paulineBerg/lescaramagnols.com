<?php

declare(strict_types=1);

use Caramagnols\Admin\Navigation\NavigationBuilderActionParser;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class NavigationBuilderActionParserTest extends TestCase
{
    public function testParseReturnsSaveActionWhenPayloadIsEmpty(): void
    {
        $parser = new NavigationBuilderActionParser();

        $action = $parser->parse('');

        $this->assertSame('save', $action['name']);
        $this->assertSame('', $action['target']);
        $this->assertSame('', $action['extra']);
    }

    public function testParseSplitsActionTargetAndExtra(): void
    {
        $parser = new NavigationBuilderActionParser();

        $action = $parser->parse('append@primary@route');

        $this->assertSame('append', $action['name']);
        $this->assertSame('primary', $action['target']);
        $this->assertSame('route', $action['extra']);
    }
}
