<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Processor\Exceptions;

use LastDragon_ru\LaraASP\Documentator\Processor\Contracts\Task;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\File;
use Throwable;

use function sprintf;

class TaskFailed extends ProcessorError {
    public function __construct(
        protected readonly File $target,
        protected readonly Task $task,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'The `%s` task failed for `%s` file.',
                $this->task::class,
                $this->target,
            ),
            $previous,
        );
    }

    public function getTarget(): File {
        return $this->target;
    }

    public function getTask(): Task {
        return $this->task;
    }
}
