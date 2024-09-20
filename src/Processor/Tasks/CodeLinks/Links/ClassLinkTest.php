<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Processor\Tasks\CodeLinks\Links;

use LastDragon_ru\LaraASP\Documentator\Testing\Package\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 */
#[CoversClass(ClassLink::class)]
final class ClassLinkTest extends TestCase {
    public function testToString(): void {
        self::assertEquals('Class', (string) new ClassLink('Class'));
        self::assertEquals('App\\Class', (string) new ClassLink('App\\Class'));
        self::assertEquals('\\App\\Class', (string) new ClassLink('\\App\\Class'));
    }

    public function testGetTitle(): void {
        self::assertEquals('Class', (new ClassLink('Class'))->getTitle());
        self::assertEquals('Class', (new ClassLink('App\\Class'))->getTitle());
        self::assertEquals('Class', (new ClassLink('\\App\\Class'))->getTitle());
    }
}
