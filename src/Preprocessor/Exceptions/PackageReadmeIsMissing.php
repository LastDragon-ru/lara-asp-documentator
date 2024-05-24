<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Preprocessor\Exceptions;

use LastDragon_ru\LaraASP\Documentator\Preprocessor\Context;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\Directory;
use Throwable;

use function sprintf;

class PackageReadmeIsMissing extends InstructionFailed {
    public function __construct(
        Context $context,
        private readonly Directory $package,
        Throwable $previous = null,
    ) {
        parent::__construct(
            $context,
            sprintf(
                "The package `%s` doesn't contain readme (in `%s`).",
                $this->package->getRelativePath($context->root),
                $context->file->getRelativePath($context->root),
            ),
            $previous,
        );
    }

    public function getPackage(): Directory {
        return $this->package;
    }
}
