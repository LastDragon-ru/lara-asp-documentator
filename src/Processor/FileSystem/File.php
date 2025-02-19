<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Processor\FileSystem;

use InvalidArgumentException;
use LastDragon_ru\LaraASP\Core\Path\FilePath;
use LastDragon_ru\LaraASP\Documentator\Processor\Metadata\Metadata;

use function is_file;
use function sprintf;

/**
 * @extends Entry<FilePath>
 */
class File extends Entry {
    public function __construct(
        private readonly Metadata $metadata,
        FilePath $path,
    ) {
        parent::__construct($path);

        if (!is_file((string) $this->path)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The `%s` is not a file.',
                    $this->path,
                ),
            );
        }
    }

    /**
     * @return ?non-empty-string
     */
    public function getExtension(): ?string {
        return $this->path->getExtension();
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $metadata
     *
     * @return T
     */
    public function as(string $metadata): object {
        return $this->metadata->get($this, $metadata);
    }
}
