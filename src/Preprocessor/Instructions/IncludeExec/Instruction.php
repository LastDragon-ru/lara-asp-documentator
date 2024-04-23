<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Preprocessor\Instructions\IncludeExec;

use Exception;
use Illuminate\Process\Factory;
use LastDragon_ru\LaraASP\Documentator\Preprocessor\Contracts\ProcessableInstruction;
use LastDragon_ru\LaraASP\Documentator\Preprocessor\Exceptions\TargetExecFailed;
use Override;

use function dirname;
use function trim;

class Instruction implements ProcessableInstruction {
    public function __construct(
        protected readonly Factory $factory,
    ) {
        // empty
    }

    #[Override]
    public static function getName(): string {
        return 'include:exec';
    }

    #[Override]
    public static function getDescription(): string {
        return 'Executes the `<target>` and returns result.';
    }

    #[Override]
    public static function getTargetDescription(): ?string {
        return 'Path to the executable.';
    }

    #[Override]
    public function process(string $path, string $target): string {
        try {
            return trim($this->factory->newPendingProcess()->path(dirname($path))->run($target)->throw()->output());
        } catch (Exception $exception) {
            throw new TargetExecFailed($path, $target, $exception);
        }
    }
}
