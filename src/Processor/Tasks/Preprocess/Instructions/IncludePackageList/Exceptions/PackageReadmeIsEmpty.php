<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Processor\Tasks\Preprocess\Instructions\IncludePackageList\Exceptions;

use LastDragon_ru\LaraASP\Documentator\Processor\Tasks\Preprocess\Context;
use LastDragon_ru\LaraASP\Documentator\Processor\Tasks\Preprocess\Exceptions\InstructionFailed;
use LastDragon_ru\LaraASP\Documentator\Processor\Tasks\Preprocess\Instructions\IncludePackageList\Parameters;
use Throwable;

use function sprintf;

/**
 * @deprecated 8.0.0
 */
class PackageReadmeIsEmpty extends InstructionFailed {
    public function __construct(
        Context $context,
        Parameters $parameters,
        protected readonly string $package,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $context,
            $parameters,
            sprintf(
                'The package `%s`/`%s/ readme file is empty or not readable (`%s` line).',
                $parameters->target,
                $this->package,
                $context->node->getStartLine() ?? 'unknown',
            ),
            $previous,
        );
    }

    public function getPackage(): string {
        return $this->package;
    }
}
