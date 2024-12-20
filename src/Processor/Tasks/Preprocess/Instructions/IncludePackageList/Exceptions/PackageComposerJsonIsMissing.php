<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Processor\Tasks\Preprocess\Instructions\IncludePackageList\Exceptions;

use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\Directory;
use LastDragon_ru\LaraASP\Documentator\Processor\Tasks\Preprocess\Context;
use LastDragon_ru\LaraASP\Documentator\Processor\Tasks\Preprocess\Exceptions\InstructionFailed;
use Throwable;

use function sprintf;

/**
 * @deprecated %{VERSION}
 */
class PackageComposerJsonIsMissing extends InstructionFailed {
    public function __construct(
        Context $context,
        private readonly Directory $package,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $context,
            sprintf(
                "The package `%s` doesn't contain `composer.json` (in `%s`).",
                $context->root->getRelativePath($this->package),
                $context->root->getRelativePath($context->file),
            ),
            $previous,
        );
    }

    public function getPackage(): Directory {
        return $this->package;
    }
}
