<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Markdown\Extensions\AstRestorer;

use LastDragon_ru\LaraASP\Documentator\Package;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Xml\XmlNodeRendererInterface;
use Override;

class Renderer implements NodeRendererInterface, XmlNodeRendererInterface {
    public function __construct() {
        // empty
    }

    #[Override]
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): mixed {
        return '';
    }

    #[Override]
    public function getXmlTagName(Node $node): string {
        return Package::Name.':ast-restorer';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getXmlAttributes(Node $node): array {
        return [];
    }
}
