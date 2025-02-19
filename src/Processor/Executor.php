<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Processor;

use Exception;
use Generator;
use Iterator;
use LastDragon_ru\LaraASP\Documentator\Processor\Contracts\Dependency;
use LastDragon_ru\LaraASP\Documentator\Processor\Contracts\Task;
use LastDragon_ru\LaraASP\Documentator\Processor\Events\DependencyResolved;
use LastDragon_ru\LaraASP\Documentator\Processor\Events\DependencyResolvedResult;
use LastDragon_ru\LaraASP\Documentator\Processor\Events\FileFinished;
use LastDragon_ru\LaraASP\Documentator\Processor\Events\FileFinishedResult;
use LastDragon_ru\LaraASP\Documentator\Processor\Events\FileStarted;
use LastDragon_ru\LaraASP\Documentator\Processor\Events\TaskFinished;
use LastDragon_ru\LaraASP\Documentator\Processor\Events\TaskFinishedResult;
use LastDragon_ru\LaraASP\Documentator\Processor\Events\TaskStarted;
use LastDragon_ru\LaraASP\Documentator\Processor\Exceptions\DependencyCircularDependency;
use LastDragon_ru\LaraASP\Documentator\Processor\Exceptions\DependencyUnresolvable;
use LastDragon_ru\LaraASP\Documentator\Processor\Exceptions\ProcessorError;
use LastDragon_ru\LaraASP\Documentator\Processor\Exceptions\TaskFailed;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\Directory;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\File;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\FileSystem;
use Traversable;

use function array_values;

/**
 * @internal
 */
class Executor {
    /**
     * @var array<string, true>
     */
    private array $processed = [];

    /**
     * @var array<string, File>
     */
    private array $stack = [];

    public function __construct(
        private readonly FileSystem $fs,
        /**
         * @var array<array-key, string>
         */
        private readonly array $exclude,
        private readonly Tasks $tasks,
        private readonly Dispatcher $dispatcher,
        /**
         * @var Iterator<array-key, File>
         */
        private readonly Iterator $iterator,
    ) {
        // empty
    }

    public function run(): void {
        while ($this->iterator->valid()) {
            $file = $this->iterator->current();

            $this->iterator->next();

            $this->file($file);
        }
    }

    private function file(File $file): void {
        // Processed?
        $path = (string) $file;

        if (isset($this->processed[$path])) {
            return;
        }

        // Circular?
        if (isset($this->stack[$path])) {
            // The $file cannot be changed if it is placed outside the output
            // directory, so we can return it safely in this case.
            if ($this->fs->output->isInside($file->getPath())) {
                $this->dispatcher->notify(new FileStarted($this->fs->getPathname($file)));
                $this->dispatcher->notify(new FileFinished(FileFinishedResult::Failed));

                throw new DependencyCircularDependency($file, array_values($this->stack));
            } else {
                return;
            }
        }

        // Event
        $this->dispatcher->notify(new FileStarted($this->fs->getPathname($file)));

        // Skipped?
        if ($this->isSkipped($file)) {
            $this->dispatcher->notify(new FileFinished(FileFinishedResult::Skipped));

            $this->processed[$path] = true;

            return;
        }

        // Process
        $tasks              = $this->tasks->get($file->getExtension(), '*');
        $this->stack[$path] = $file;

        try {
            $this->fs->begin();

            foreach ($tasks as $task) {
                $this->task($file, $task);
            }

            $this->fs->commit();
        } catch (Exception $exception) {
            $this->dispatcher->notify(new FileFinished(FileFinishedResult::Failed));

            throw $exception;
        } finally {
            $this->processed[$path] = true;
        }

        // Event
        $this->dispatcher->notify(new FileFinished(FileFinishedResult::Success));

        // Reset
        unset($this->stack[$path]);
    }

    private function task(File $file, Task $task): void {
        $this->dispatcher->notify(new TaskStarted($task::class));

        try {
            try {
                // Run
                $result    = false;
                $generator = $task($file);

                // Dependencies?
                if ($generator instanceof Generator) {
                    while ($generator->valid()) {
                        $dependency = $generator->current();
                        $resolved   = $this->resolve($dependency);

                        $generator->send($resolved);
                    }

                    $result = $generator->getReturn();
                } else {
                    $result = $generator;
                }

                if ($result !== true) {
                    throw new TaskFailed($file, $task);
                }
            } catch (ProcessorError $exception) {
                throw $exception;
            } catch (Exception $exception) {
                throw new TaskFailed($file, $task, $exception);
            }

            $this->dispatcher->notify(new TaskFinished(TaskFinishedResult::Success));
        } catch (Exception $exception) {
            $this->dispatcher->notify(new TaskFinished(TaskFinishedResult::Failed));

            throw $exception;
        }
    }

    /**
     * @param Dependency<*> $dependency
     *
     * @return Traversable<mixed, Directory|File>|Directory|File|null
     */
    private function resolve(Dependency $dependency): Traversable|Directory|File|null {
        try {
            $resolved = $dependency($this->fs);
            $resolved = $this->dependency($dependency, $resolved);
            $resolved = $resolved instanceof Traversable
                ? new ExecutorTraversable($dependency, $resolved, $this->dependency(...))
                : $resolved;
        } catch (DependencyUnresolvable $exception) {
            $this->dispatcher->notify(
                new DependencyResolved(
                    $this->fs->getPathname($exception->getDependency()->getPath($this->fs)),
                    DependencyResolvedResult::Missed,
                ),
            );

            throw $exception;
        } catch (Exception $exception) {
            $this->dispatcher->notify(
                new DependencyResolved(
                    $this->fs->getPathname($dependency->getPath($this->fs)),
                    DependencyResolvedResult::Failed,
                ),
            );

            throw $exception;
        }

        return $resolved;
    }

    /**
     * @template T
     *
     * @param Dependency<*> $dependency
     * @param T $resolved
     *
     * @return T
     */
    private function dependency(Dependency $dependency, mixed $resolved): mixed {
        // Event
        $path   = $resolved instanceof File || $resolved instanceof Directory
            ? $resolved
            : $dependency->getPath($this->fs);
        $result = $resolved !== null
            ? DependencyResolvedResult::Success
            : DependencyResolvedResult::Null;

        $this->dispatcher->notify(
            new DependencyResolved($this->fs->getPathname($path), $result),
        );

        // Process
        if ($resolved instanceof File) {
            $this->file($resolved);
        }

        // Return
        return $resolved;
    }

    private function isSkipped(File $file): bool {
        // Tasks?
        if (!$this->tasks->has($file->getExtension(), '*')) {
            return true;
        }

        // Outside?
        if (!$this->fs->input->isInside($file->getPath())) {
            return true;
        }

        // Excluded?
        $path     = $this->fs->input->getRelativePath($file->getPath());
        $excluded = false;

        foreach ($this->exclude as $regexp) {
            if ($path->isMatch($regexp)) {
                $excluded = true;
                break;
            }
        }

        if ($excluded) {
            return true;
        }

        // Return
        return false;
    }
}
