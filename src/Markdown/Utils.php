<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Markdown;

use LastDragon_ru\LaraASP\Documentator\Markdown\Data\Data;
use LastDragon_ru\LaraASP\Documentator\Markdown\Data\Lines;
use LastDragon_ru\LaraASP\Documentator\Markdown\Data\Padding;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Node;
use League\CommonMark\Util\UrlEncoder;

use function mb_strpos;
use function preg_match;
use function sprintf;
use function str_contains;
use function strtr;
use function trim;

/**
 * @internal
 */
class Utils {
    public static function getDocument(Node $node): ?Document {
        return self::getParent($node, Document::class);
    }

    public static function getContainer(Node $node): ?AbstractBlock {
        return self::getParent($node, AbstractBlock::class);
    }

    /**
     * @template T of Node
     *
     * @param class-string<T> $class
     *
     * @return ?T
     */
    public static function getChild(Node $node, string $class): ?Node {
        $object = null;

        foreach ($node->children() as $child) {
            if ($child instanceof $class) {
                $object = $child;
                break;
            }
        }

        return $object;
    }

    public static function getPosition(Node $node): int {
        $position = 0;

        while ($node = $node->previous()) {
            $position++;
        }

        return $position;
    }

    /**
     * Detect block padding. We are expecting that all lines inside the block
     * have the same padding.
     */
    public static function getPadding(Node $node, ?int $line, ?string $start): ?int {
        // Container?
        $container = self::getContainer($node);

        if ($container === null) {
            return null;
        }

        // Known?
        $padding = Data::get($container, Padding::class);

        if ($padding !== null) {
            return $padding;
        }

        // Possible?
        if ($line === null || $start === null) {
            return null;
        }

        // Document?
        $document = self::getDocument($container);

        if ($document === null) {
            return null;
        }

        // Detect
        $padding = mb_strpos(self::getLine($document, $line) ?? '', $start);

        if ($padding === false) {
            return null;
        }

        // Cache
        Data::set($container, new Padding($padding));

        // Return
        return $padding;
    }

    public static function getLine(Document $document, int $line): ?string {
        $lines = Data::get($document, Lines::class) ?? [];
        $line  = $lines[$line] ?? null;

        return $line;
    }

    public static function getLink(
        string $format,
        string $label,
        string $target,
        string $title,
        ?bool $wrapTarget,
        ?string $titleWrapper,
    ): string {
        $label  = self::getLinkLabel($label);
        $title  = self::getLinkTitle($title, $titleWrapper);
        $target = self::getLinkTarget($target, $wrapTarget);
        $link   = trim(sprintf($format, $label, $target, $title));

        return $link;
    }

    private static function getLinkLabel(string $label): string {
        return strtr($label, ['[' => '\\\\[', ']' => '\\\\]']);
    }

    private static function getLinkTarget(string $target, ?bool $wrap): string {
        return ($wrap ?? preg_match('/\s/u', $target))
            ? '<'.strtr($target, ['<' => '\\<', '>' => '\\>']).'>'
            : UrlEncoder::unescapeAndEncode($target);
    }

    private static function getLinkTitle(string $title, ?string $wrapper = null): string {
        if (!$title) {
            return '';
        }

        $wrappers = [
            ')' => ['(' => '\\(', ')' => '\\)'],
            '"' => ['"' => '\\"'],
            "'" => ["'" => "\\'"],
        ];
        $wrapper  = match (true) {
            isset($wrappers[$wrapper]) => $wrapper,
            !str_contains($title, '"') => '"',
            !str_contains($title, "'") => "'",
            default                    => ')',
        };
        $title = match ($wrapper) {
            '"'     => '"'.strtr($title, $wrappers['"']).'"',
            "'"     => "'".strtr($title, $wrappers['"'])."'",
            default => '('.strtr($title, $wrappers[')']).')',
        };

        return $title;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return ?T
     */
    private static function getParent(Node $node, string $class): ?object {
        $parent = null;

        do {
            if ($node instanceof $class) {
                $parent = $node;
                break;
            }

            $node = $node->parent();
        } while ($node);

        return $parent;
    }
}
