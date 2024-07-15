<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Processor\Dependencies;

use LastDragon_ru\LaraASP\Documentator\Processor\Contracts\Dependency;
use LastDragon_ru\LaraASP\Documentator\Processor\Exceptions\DependencyNotFound;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\Directory;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\File;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\FileSystem;
use Override;

/**
 * @implements Dependency<Directory>
 */
class DirectoryReference extends Base implements Dependency {
    public function __construct(
        protected readonly Directory|string $reference,
    ) {
        parent::__construct();
    }

    #[Override]
    public function __invoke(FileSystem $fs, Directory $root, File $file): mixed {
        // Already?
        if ($this->reference instanceof Directory) {
            return $this->reference;
        }

        // Create
        $resolved = $fs->getDirectory($root, $this->getPath($file));

        if ($resolved === null) {
            throw new DependencyNotFound($root, $file, $this);
        }

        return $resolved;
    }

    #[Override]
    public function __toString(): string {
        return match (true) {
            $this->reference instanceof Directory => $this->reference->getPath(),
            default                               => $this->reference,
        };
    }
}
