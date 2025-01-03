<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Markdown\Mutations\Footnote;

use LastDragon_ru\LaraASP\Documentator\Markdown\Contracts\Markdown;
use LastDragon_ru\LaraASP\Documentator\Testing\Package\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 */
#[CoversClass(Remove::class)]
final class RemoveTest extends TestCase {
    public function testInvoke(): void {
        $content = <<<'MARKDOWN'
            # Header[^1]

            Text text text[^2] text text [^1] text text text [^2] text text text
            text text[^1] text text text [^2] text text text [^3] text[^bignote].

            [^1]: footnote 1

            Text text text[^2].

            [^2]: footnote 2

            [^4]: footnote 4

            [^bignote]: Text text text text text text text text text text text
                text text text text text text text text text text text text text
                text.

                Text text text text text text text text text text text text text
                text text text text text text text text text text text text text
                text.
            MARKDOWN;

        $markdown = $this->app()->make(Markdown::class);
        $document = $markdown->parse($content);
        $actual   = (string) $document->mutate(new Remove());

        self::assertEquals(
            <<<'MARKDOWN'
            # Header

            Text text text text text  text text text  text text text
            text text text text text  text text text [^3] text.

            Text text text.

            [^4]: footnote 4

            MARKDOWN,
            $actual,
        );
    }
}