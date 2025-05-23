<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Markdown\Contracts;

use LastDragon_ru\LaraASP\Core\Path\FilePath;
use LastDragon_ru\LaraASP\Documentator\Markdown\DocumentImpl;
use League\CommonMark\Node\Block\Document as DocumentNode;
use League\CommonMark\Node\Node;
use Stringable;

/**
 * @property-read DocumentNode $node
 * @property ?FilePath         $path
 *
 * @phpstan-require-extends DocumentImpl
 */
interface Document extends Stringable {
    /**
     * @param Mutation<covariant Node>|iterable<mixed, Mutation<covariant Node>> ...$mutations
     */
    public function mutate(Mutation|iterable ...$mutations): self;
}
