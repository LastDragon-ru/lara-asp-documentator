<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Markdown\Mutations;

use LastDragon_ru\LaraASP\Documentator\Markdown\Contracts\Mutation;
use LastDragon_ru\LaraASP\Documentator\Markdown\Data\Data;
use LastDragon_ru\LaraASP\Documentator\Markdown\Data\Offset;
use LastDragon_ru\LaraASP\Documentator\Markdown\Document;
use LastDragon_ru\LaraASP\Documentator\Markdown\Nodes\Reference\Block as Reference;
use LastDragon_ru\LaraASP\Documentator\Markdown\Utils;
use League\CommonMark\Extension\CommonMark\Node\Inline\AbstractWebResource;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Block\Document as DocumentNode;
use Override;

use function rawurldecode;

/**
 * Inlines all references.
 */
class ReferencesInline implements Mutation {
    public function __construct() {
        // empty
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function __invoke(Document $document, DocumentNode $node): iterable {
        // Just in case
        yield from [];

        // Process
        $references = $this->getReferences($node);

        foreach ($references as $reference) {
            // Location?
            $location = Utils::getLocation($reference);

            if ($location === null) {
                continue;
            }

            // Change
            $text = null;

            if ($reference instanceof Link || $reference instanceof Image) {
                $offset   = Data::get($reference, Offset::class);
                $location = $offset !== null ? Utils::getOffsetLocation($location, $offset) : null;

                if ($location !== null) {
                    $title  = Utils::getLinkTitle($reference, (string) $reference->getTitle());
                    $target = Utils::getLinkTarget($reference, rawurldecode($reference->getUrl()));
                    $text   = $title !== '' ? "({$target} {$title})" : "({$target})";
                }
            } elseif ($reference instanceof Reference) {
                $text = '';
            } else {
                // skipped
            }

            if ($location !== null && $text !== null) {
                yield [$location, $text !== '' ? $text : null];
            }
        }
    }

    /**
     * @return list<AbstractWebResource|Reference>
     */
    protected function getReferences(DocumentNode $node): array {
        $references = [];

        foreach ($node->iterator() as $child) {
            if ($child instanceof AbstractWebResource && Utils::isReference($child)) {
                $references[] = $child;
            } elseif ($child instanceof Reference) {
                $references[] = $child;
            } else {
                // empty
            }
        }

        return $references;
    }
}
