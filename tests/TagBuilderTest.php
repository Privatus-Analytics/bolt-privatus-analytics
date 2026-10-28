<?php

declare(strict_types=1);

namespace Privatus\BoltAnalytics\Tests;

use PHPUnit\Framework\TestCase;
use Privatus\BoltAnalytics\TagBuilder;

final class TagBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('PRIVATUS_SITE_ID');
    }

    public function testFullTag(): void
    {
        self::assertSame(
            '<script defer src="https://privatusstats.com/js/pa.js" data-site="pa_YOURSITEID" data-modules="engage,auto,vitals,clicks,ask"></script>',
            TagBuilder::build(['site_id' => 'pa_YOURSITEID', 'modules' => ['engage', 'auto', 'vitals', 'clicks', 'ask']]),
        );
    }

    public function testMissingModulesLoadsEveryModule(): void
    {
        self::assertStringContainsString('data-modules="engage,auto,vitals,clicks,ask"', TagBuilder::build(['site_id' => 'pa_A1']));
    }

    public function testEmptyModulesLoadsNone(): void
    {
        self::assertStringContainsString('data-modules=""', TagBuilder::build(['site_id' => 'pa_A1', 'modules' => []]));
    }

    public function testModulesKeepAFixedOrderAndDropUnknownNames(): void
    {
        self::assertSame(['engage', 'clicks'], TagBuilder::modules(['modules' => ['clicks', 'evil"', 'engage']]));
        self::assertSame(['auto', 'ask'], TagBuilder::modules(['modules' => 'ask, auto']));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function badSiteIds(): iterable
    {
        yield 'empty' => [''];
        yield 'no prefix' => ['YOURSITEID'];
        yield 'quote' => ['pa_x" onload="alert(1)'];
        yield 'angle bracket' => ['pa_x<script>'];
        yield 'dash' => ['pa_ab-c'];
        yield 'not a string' => [['pa_A']];
        yield 'null' => [null];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badSiteIds')]
    public function testBadSiteIdPrintsNothing(mixed $siteId): void
    {
        self::assertSame('', TagBuilder::build(['site_id' => $siteId]));
    }

    public function testSiteIdIsTrimmed(): void
    {
        self::assertSame('pa_Abc', TagBuilder::siteId(['site_id' => '  pa_Abc ']));
    }

    public function testEnvironmentVariableIsUsedWhenConfigIsEmpty(): void
    {
        putenv('PRIVATUS_SITE_ID=pa_FromEnv');
        self::assertStringContainsString('data-site="pa_FromEnv"', TagBuilder::build(['site_id' => '']));
        putenv('PRIVATUS_SITE_ID=bad value');
        self::assertSame('', TagBuilder::build([]));
    }

    public function testDefaultConfigFileHasNoSiteIdAndEveryModule(): void
    {
        $yaml = (string) file_get_contents(__DIR__ . '/../config/config.yaml');
        self::assertStringContainsString("site_id: ''", $yaml);
        self::assertStringContainsString('modules: [engage, auto, vitals, clicks, ask]', $yaml);
    }
}
