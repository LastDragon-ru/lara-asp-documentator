<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Processor\Tasks\CodeLinks\Links;

use LastDragon_ru\LaraASP\Documentator\Composer\Package;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\Directory;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\File;
use LastDragon_ru\LaraASP\Documentator\Processor\Metadata\PhpClassComment;
use LastDragon_ru\LaraASP\Documentator\Processor\Tasks\CodeLinks\Contracts\Link;
use LastDragon_ru\LaraASP\Documentator\Processor\Tasks\CodeLinks\LinkTarget;
use LastDragon_ru\LaraASP\Documentator\Utils\PhpDoc;
use Override;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;

use function ltrim;
use function mb_strrpos;
use function mb_substr;

abstract class Base implements Link {
    public function __construct(
        protected readonly PhpClassComment $comment,
        public readonly string $class,
    ) {
        // empty
    }

    #[Override]
    public function getTitle(): ?string {
        $title = $this->un((string) $this);
        $title = $title !== '' ? $title : null;

        return $title;
    }

    #[Override]
    public function isSimilar(Link $link): bool {
        // Self?
        if ($link === $this) {
            return false;
        }

        // Base?
        if ($link instanceof self) {
            if ($link->class === $this->class) {
                return false;
            }

            if ($this->un($link->class) === $this->un($this->class)) {
                return true;
            }
        }

        // Same title?
        if ($link->getTitle() !== null && $link->getTitle() === $this->getTitle()) {
            return true;
        }

        // Else
        return (string) $link === (string) $this;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getSource(Directory $root, File $file, Package $package): array|string|null {
        return $package->resolve($this->class);
    }

    #[Override]
    public function getTarget(Directory $root, File $file, File $source): ?LinkTarget {
        // Class?
        $comment = $source->getMetadata($this->comment);

        if ($comment === null) {
            return null;
        }

        if ((string) $comment->class->namespacedName !== ltrim($this->class, '\\')) {
            return null;
        }

        // Resolve
        $path       = $source->getRelativePath($file);
        $node       = $this->getTargetNode($comment->class);
        $deprecated = $comment->comment->isDeprecated();
        $target     = $this->target($path, $node, $deprecated);

        // Return
        return $target;
    }

    abstract protected function getTargetNode(ClassLike $class): ?Node;

    private function target(string $path, ?Node $node, bool $deprecated): ?LinkTarget {
        if ($node === null) {
            return null;
        }

        $comment    = $node->getDocComment();
        $endLine    = null;
        $startLine  = null;
        $deprecated = $deprecated || (new PhpDoc($comment?->getText()))->isDeprecated();

        if (!($node instanceof ClassLike)) {
            $endLine   = $node->getEndLine();
            $endLine   = $endLine >= 0 ? $endLine : null;
            $startLine = $comment?->getStartLine() ?? $node->getStartLine();
            $startLine = $startLine >= 0 ? $startLine : null;
        }

        return new LinkTarget($path, $deprecated, $startLine, $endLine);
    }

    private function un(string $class): string {
        $class    = ltrim($class, '\\');
        $position = mb_strrpos($class, '\\');

        if ($position !== false) {
            $class = mb_substr($class, $position + 1);
        }

        return $class;
    }
}