<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Processor;

use Exception;
use LastDragon_ru\LaraASP\Documentator\Processor\Contracts\Task;
use LastDragon_ru\LaraASP\Documentator\Processor\Events\FileFinished;
use LastDragon_ru\LaraASP\Documentator\Processor\Events\FileFinishedResult;
use LastDragon_ru\LaraASP\Documentator\Processor\Events\FileStarted;
use LastDragon_ru\LaraASP\Documentator\Processor\Events\TaskFinished;
use LastDragon_ru\LaraASP\Documentator\Processor\Events\TaskFinishedResult;
use LastDragon_ru\LaraASP\Documentator\Processor\Events\TaskStarted;
use LastDragon_ru\LaraASP\Documentator\Processor\Exceptions\DependencyCircularDependency;
use LastDragon_ru\LaraASP\Documentator\Processor\Exceptions\DependencyUnavailable;
use LastDragon_ru\LaraASP\Documentator\Processor\Exceptions\ProcessorError;
use LastDragon_ru\LaraASP\Documentator\Processor\Exceptions\TaskFailed;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\Directory;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\File;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\FileHook;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\FileReal;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\FileSystem;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\Globs;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\Hook;

use function array_values;
use function end;

/**
 * @internal
 */
class Executor {
    private readonly Resolver $resolver;

    /**
     * @var array<string, true>
     */
    private array $processed = [];

    /**
     * @var array<string, File>
     */
    private array $stack = [];

    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly Tasks $tasks,
        private readonly FileSystem $fs,
        private readonly Iterator $iterator,
        private readonly Globs $exclude,
    ) {
        $this->resolver = new Resolver($this->dispatcher, $this->fs, $this->onResolve(...), $this->onQueue(...));
    }

    public function run(): void {
        // Before?
        $before = $this->fs->getFile(Hook::Before);

        if (!$this->isSkipped($before)) {
            $this->file($before);
        }

        // Files
        foreach ($this->iterator as $file) {
            $this->file($file);
        }

        // After
        $after = $this->fs->getFile(Hook::After);

        if (!$this->isSkipped($after)) {
            $this->file($after);
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
            // The $file cannot be processed if it is skipped, so we can return
            // it safely in this case.
            if (!$this->isSkipped($file)) {
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
        $tasks              = $this->tasks->get(...$this->extensions($file));
        $this->stack[$path] = $file;

        try {
            $this->fs->begin();

            foreach ($tasks as $task) {
                $this->task($file, $task);

                $this->resolver->check();
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
                $task($this->resolver, $file);

                $this->resolver->check();
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

    private function onResolve(Directory|File $resolved): void {
        // Skipped?
        if ($resolved instanceof File && $this->isSkipped($resolved)) {
            return;
        }

        // The `:before` hook cannot use files that will be processed because
        // the hook should be run before any of the tasks.
        $file         = end($this->stack);
        $isBeforeHook = $file instanceof FileHook && $file->hook === Hook::Before;

        if ($isBeforeHook && $resolved instanceof File) {
            throw new DependencyUnavailable();
        }

        // Process
        if (!$isBeforeHook && $file !== $resolved && $resolved instanceof FileReal) {
            $this->file($resolved);
        }
    }

    private function onQueue(File $resolved): void {
        // Hook?
        if ($resolved instanceof FileHook) {
            throw new DependencyUnavailable();
        }

        // Skipped?
        if ($this->isSkipped($resolved)) {
            return;
        }

        // Queue
        $this->iterator->push($resolved->getPath());
    }

    private function isSkipped(File $file): bool {
        // Tasks?
        if (!$this->tasks->has(...$this->extensions($file))) {
            return true;
        }

        // Outside?
        if (!$this->fs->input->isInside($file->getPath())) {
            return true;
        }

        // Excluded?
        $path     = $this->fs->input->getRelativePath($file->getPath());
        $excluded = $this->exclude->isMatch($path);

        if ($excluded) {
            return true;
        }

        // Return
        return false;
    }

    /**
     * @return list<string>
     */
    private function extensions(File $file): array {
        $extensions = [];
        $extension  = $file->getExtension();

        if ($extension !== null) {
            $extensions[] = $extension;
        }

        if ($file instanceof FileReal) {
            $extensions[] = '*';
            $extensions[] = Hook::Each->value;
        }

        return $extensions;
    }
}
