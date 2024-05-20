<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Preprocessor\Exceptions;

use LastDragon_ru\LaraASP\Documentator\Preprocessor\Context;
use Throwable;

use function sprintf;

class TargetExecFailed extends InstructionFailed {
    public function __construct(Context $context, Throwable $previous = null) {
        parent::__construct(
            $context,
            sprintf(
                'Failed to execute the `%s` command (in `%s`).',
                $context->target,
                $context->file->getRelativePath($context->root),
            ),
            $previous,
        );
    }
}
