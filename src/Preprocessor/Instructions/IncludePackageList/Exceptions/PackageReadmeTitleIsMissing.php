<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Preprocessor\Instructions\IncludePackageList\Exceptions;

use LastDragon_ru\LaraASP\Documentator\Preprocessor\Context;
use LastDragon_ru\LaraASP\Documentator\Preprocessor\Exceptions\InstructionFailed;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\Directory;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\File;
use Throwable;

use function sprintf;

class PackageReadmeTitleIsMissing extends InstructionFailed {
    public function __construct(
        Context $context,
        private readonly Directory $package,
        private readonly File $readme,
        Throwable $previous = null,
    ) {
        parent::__construct(
            $context,
            sprintf(
                "The package `%s` readme file `%s` doesn't contain `# Header` (in `%s`).",
                $this->package->getRelativePath($context->root),
                $this->readme->getRelativePath($context->root),
                $context->file->getRelativePath($context->root),
            ),
            $previous,
        );
    }

    public function getPackage(): Directory {
        return $this->package;
    }

    public function getReadme(): File {
        return $this->readme;
    }
}
