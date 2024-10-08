<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Markdown\Nodes\Locator;

use LastDragon_ru\LaraASP\Documentator\Markdown\Extension;
use LastDragon_ru\LaraASP\Documentator\Markdown\Nodes\Reference\Block as ReferenceNode;
use LastDragon_ru\LaraASP\Documentator\Markdown\Nodes\Reference\Renderer as ReferenceRenderer;
use LastDragon_ru\LaraASP\Documentator\Markdown\Nodes\RendererWrapper;
use LastDragon_ru\LaraASP\Documentator\Testing\Package\TestCase;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\CodeRenderer;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\ImageRenderer;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\LinkRenderer;
use League\CommonMark\Extension\Footnote\Node\Footnote;
use League\CommonMark\Extension\Footnote\Node\FootnoteRef;
use League\CommonMark\Extension\Footnote\Renderer\FootnoteRefRenderer;
use League\CommonMark\Extension\Footnote\Renderer\FootnoteRenderer;
use League\CommonMark\Extension\Table\TableCell;
use League\CommonMark\Extension\Table\TableCellRenderer;
use League\CommonMark\Extension\Table\TableRow;
use League\CommonMark\Extension\Table\TableRowRenderer;
use League\CommonMark\Extension\Table\TableSection;
use League\CommonMark\Extension\Table\TableSectionRenderer;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Xml\XmlRenderer;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 */
#[CoversClass(Parser::class)]
final class ParserTest extends TestCase {
    public function testParse(): void {
        $converter   = new GithubFlavoredMarkdownConverter();
        $environment = $converter->getEnvironment()
            ->addExtension(new Extension())
            ->addRenderer(Link::class, new RendererWrapper(new LinkRenderer()))
            ->addRenderer(Image::class, new RendererWrapper(new ImageRenderer()))
            ->addRenderer(Footnote::class, new RendererWrapper(new FootnoteRenderer()))
            ->addRenderer(FootnoteRef::class, new RendererWrapper(new FootnoteRefRenderer()))
            ->addRenderer(TableSection::class, new RendererWrapper(new TableSectionRenderer()))
            ->addRenderer(TableRow::class, new RendererWrapper(new TableRowRenderer()))
            ->addRenderer(TableCell::class, new RendererWrapper(new TableCellRenderer()))
            ->addRenderer(ReferenceNode::class, new RendererWrapper(new ReferenceRenderer()))
            ->addRenderer(Code::class, new RendererWrapper(new CodeRenderer()));

        $parser   = new MarkdownParser($environment);
        $document = $parser->parse(self::getTestData()->content('~document.md'));
        $renderer = new XmlRenderer($environment);

        self::assertXmlStringEqualsXmlString(
            self::getTestData()->content('~expected.xml'),
            $renderer->renderDocument($document)->getContent(),
        );
    }
}
