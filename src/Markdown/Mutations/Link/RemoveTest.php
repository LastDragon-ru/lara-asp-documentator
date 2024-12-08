<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Markdown\Mutations\Link;

use LastDragon_ru\LaraASP\Core\Path\FilePath;
use LastDragon_ru\LaraASP\Documentator\Markdown\Document;
use LastDragon_ru\LaraASP\Documentator\Markdown\Editor;
use LastDragon_ru\LaraASP\Documentator\Testing\Package\TestCase;
use League\CommonMark\Node\Block\Document as DocumentNode;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;

use function array_key_first;
use function array_values;

/**
 * @internal
 */
#[CoversClass(Remove::class)]
final class RemoveTest extends TestCase {
    public function testInvoke(): void {
        $markdown = <<<'MARKDOWN'
            # Header

            Text text [link](https://example.com) text text [`link`][link] text
            text text [self][self] text text [self](#fragment) text text text
            text text ![image][image] text text ![image](#fragment).

            [self]: #fragment
            [link]: ./path/to/file.md
            [image]: ./#fragment

            # Special

            ## Inside Quote

            > Text text [self][self] text text [self](#fragment) text text text

            ## Inside Table

            | Header                  |  [Header][link]               |
            |-------------------------|-------------------------------|
            | Cell [link][self] cell. | Cell `\|` \\| ![table][image] |
            | Cell                    | Cell cell [table][self].      |
            MARKDOWN;
        $document = new class($markdown, new FilePath('path/to/file.md')) extends Document {
            #[Override]
            public function getNode(): DocumentNode {
                return parent::getNode();
            }

            /**
             * @inheritDoc
             */
            #[Override]
            public function getLines(): array {
                return parent::getLines();
            }
        };
        $lines    = $document->getLines();
        $offset   = (int) array_key_first($lines);
        $mutation = new Remove();
        $changes  = $mutation($document);
        $actual   = (string) (new Editor(array_values($lines), $offset))->mutate($changes);

        self::assertEquals(
            <<<'MARKDOWN'
            # Header

            Text text link text text `link` text
            text text self text text self text text text
            text text ![image][image] text text ![image](#fragment).

            [self]: #fragment
            [link]: ./path/to/file.md
            [image]: ./#fragment

            # Special

            ## Inside Quote

            > Text text self text text self text text text

            ## Inside Table

            | Header                  |  Header               |
            |-------------------------|-------------------------------|
            | Cell link cell. | Cell `\|` \\| ![table][image] |
            | Cell                    | Cell cell table.      |
            MARKDOWN,
            $actual,
        );
    }
}
