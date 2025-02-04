<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Markdown\Mutations\Document;

use LastDragon_ru\LaraASP\Core\Path\FilePath;
use LastDragon_ru\LaraASP\Documentator\Editor\Coordinate;
use LastDragon_ru\LaraASP\Documentator\Markdown\Contracts\Mutation;
use LastDragon_ru\LaraASP\Documentator\Markdown\Data\Content;
use LastDragon_ru\LaraASP\Documentator\Markdown\Data\Location;
use LastDragon_ru\LaraASP\Documentator\Markdown\Data\Reference;
use LastDragon_ru\LaraASP\Documentator\Markdown\Document;
use LastDragon_ru\LaraASP\Documentator\Markdown\Extensions\Reference\Node as ReferenceNode;
use LastDragon_ru\LaraASP\Documentator\Markdown\Utils;
use League\CommonMark\Extension\CommonMark\Node\Inline\AbstractWebResource;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Node;
use Override;

use function mb_ltrim;
use function mb_rtrim;
use function mb_strlen;
use function mb_substr;
use function mb_trim;
use function parse_url;
use function preg_match;
use function preg_quote;
use function rawurldecode;

use const PHP_URL_PATH;

/**
 * Changes path and updates all relative links.
 *
 * Please note that links may/will be reformatted (because there is no
 * information about their original form)
 */
readonly class Move implements Mutation {
    public function __construct(
        protected FilePath $path,
    ) {
        // empty
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function __invoke(Document $document): iterable {
        // Just in case
        yield from [];

        // No path?
        $docPath = $document->path;

        if ($docPath === null) {
            $document->path = $this->path->getNormalizedPath();

            return;
        }

        // Same?
        $newPath = $docPath->getPath($this->path);

        if ($docPath->isEqual($newPath)) {
            return;
        }

        // Update
        foreach ($this->nodes($document) as $node) {
            // Changes
            $location = Location::get($node);
            $text     = null;

            if ($node instanceof Link || $node instanceof Image) {
                $content      = Content::get($node);
                $location     = $location->withOffset(($content->offset - $location->offset) + (int) $content->length + 1);
                $origin       = mb_trim($document->getText($location));
                $titleValue   = (string) $node->getTitle();
                $titleWrapper = mb_substr(mb_rtrim(mb_substr($origin, 0, -1)), -1, 1);
                $title        = Utils::getLinkTitle($node, $titleValue, $titleWrapper);
                $targetValue  = $this->target($document, $docPath, $newPath, $node->getUrl());
                $targetWrap   = mb_substr(mb_ltrim(mb_ltrim($origin, '(')), 0, 1) === '<';
                $target       = Utils::getLinkTarget($node, $targetValue, $targetWrap);
                $text         = $title !== '' ? "({$target} {$title})" : "({$target})";
            } elseif ($node instanceof ReferenceNode) {
                $origin       = mb_trim($document->getText($location));
                $label        = $node->getLabel();
                $titleValue   = $node->getTitle();
                $titleWrapper = mb_substr($origin, -1, 1);
                $title        = Utils::getLinkTitle($node, $titleValue, $titleWrapper);
                $targetValue  = $this->target($document, $docPath, $newPath, $node->getDestination());
                $targetWrap   = (bool) preg_match('/^\['.preg_quote($node->getLabel(), '/').']:\s+</u', $origin);
                $target       = Utils::getLinkTarget($node, $targetValue, $targetWrap);
                $text         = mb_trim("[{$label}]: {$target} {$title}");

                if ($location->startLine !== $location->endLine) {
                    $padding = $location->internalPadding ?? $location->startLinePadding;
                    $last    = $document->getText([
                        new Coordinate(
                            $location->endLine,
                            $padding,
                            $location->length,
                            $padding,
                        ),
                    ]);

                    if ($last === '') {
                        $text .= "\n";
                    }
                }
            } else {
                // skipped
            }

            if ($text !== null) {
                yield [$location, $text];
            }
        }

        // Set
        $document->path = $newPath;
    }

    /**
     * @return iterable<mixed, Node>
     */
    private function nodes(Document $document): iterable {
        // Just in case
        yield from [];

        // Search
        foreach ($document->node->iterator() as $node) {
            $url = null;

            if ($node instanceof AbstractWebResource && Reference::get($node) === null) {
                $url = rawurldecode($node->getUrl());
            } elseif ($node instanceof ReferenceNode) {
                $url = rawurldecode($node->getDestination());
            } else {
                // empty
            }

            if ($url !== null && Utils::isPathRelative($url)) {
                yield $node;
            }
        }
    }

    private function target(Document $document, FilePath $docPath, FilePath $newPath, string $target): string {
        $target = rawurldecode($target);

        if (Utils::isPathToSelf($document, $target)) {
            $path   = (string) parse_url($target, PHP_URL_PATH);
            $target = mb_substr($target, mb_strlen($path));
            $target = $target !== '' ? $target : '#';
        } else {
            $target = $docPath->getFilePath($target);
            $target = $newPath->getRelativePath($target);
        }

        return (string) $target;
    }
}
