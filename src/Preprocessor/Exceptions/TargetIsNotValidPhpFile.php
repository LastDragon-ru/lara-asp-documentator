<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Preprocessor\Exceptions;

use Throwable;

use function sprintf;

class TargetIsNotValidPhpFile extends InstructionFailed {
    public function __construct(string $path, string $target, Throwable $previous = null) {
        parent::__construct(
            $path,
            $target,
            sprintf(
                'The `%s` is not a valid PHP file (in `%s`).',
                $target,
                $path,
            ),
            $previous,
        );
    }
}
