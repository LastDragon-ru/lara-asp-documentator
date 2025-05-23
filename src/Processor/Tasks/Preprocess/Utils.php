<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Processor\Tasks\Preprocess;

use LastDragon_ru\LaraASP\Documentator\Markdown\Contracts\Document;
use LastDragon_ru\LaraASP\Documentator\Markdown\Extensions\Generated\Node as GeneratedNode;
use LastDragon_ru\LaraASP\Documentator\Markdown\Extensions\Reference\Node as ReferenceNode;
use LastDragon_ru\LaraASP\Documentator\Markdown\Utils as MarkdownUtils;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\File;
use LastDragon_ru\LaraASP\Documentator\Utils\Text;
use League\CommonMark\Node\Node;

use function uniqid;

/**
 * @internal
 */
class Utils {
    public static function isInstruction(Node $node, Instructions $instructions): bool {
        return $node instanceof ReferenceNode
            && MarkdownUtils::getParent($node, GeneratedNode::class) === null
            && $instructions->has($node->getLabel());
    }

    public static function getSeed(Context $context, Document|File $file): string {
        $path = $file instanceof Document ? $file->path : $file;
        $path = $path !== null ? (string) $context->file->getRelativePath($path) : '';
        $path = $path !== '' ? $path : uniqid(self::class); // @phpstan-ignore disallowed.function
        $path = Text::hash($path);

        return $path;
    }
}
