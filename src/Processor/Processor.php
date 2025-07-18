<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Processor;

use Closure;
use Exception;
use LastDragon_ru\LaraASP\Core\Application\ContainerResolver;
use LastDragon_ru\LaraASP\Core\Path\DirectoryPath;
use LastDragon_ru\LaraASP\Core\Path\FilePath;
use LastDragon_ru\LaraASP\Documentator\Processor\Contracts\MetadataResolver;
use LastDragon_ru\LaraASP\Documentator\Processor\Contracts\Task;
use LastDragon_ru\LaraASP\Documentator\Processor\Events\Event;
use LastDragon_ru\LaraASP\Documentator\Processor\Events\ProcessingFinished;
use LastDragon_ru\LaraASP\Documentator\Processor\Events\ProcessingFinishedResult;
use LastDragon_ru\LaraASP\Documentator\Processor\Events\ProcessingStarted;
use LastDragon_ru\LaraASP\Documentator\Processor\Exceptions\ProcessingFailed;
use LastDragon_ru\LaraASP\Documentator\Processor\Exceptions\ProcessorError;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\Adapter;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\Adapters\SymfonyAdapter;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\FileSystem;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\Globs;
use LastDragon_ru\LaraASP\Documentator\Processor\Metadata\Metadata;

use function array_map;
use function array_merge;
use function array_values;

/**
 * Perform one or more task on the file(s).
 */
class Processor {
    private readonly Tasks        $tasks;
    private readonly Metadata     $metadata;
    protected readonly Dispatcher $dispatcher;

    /**
     * @var list<string>
     */
    private array      $exclude = [];
    protected ?Adapter $adapter = null;

    public function __construct(
        protected readonly ContainerResolver $container,
    ) {
        $this->tasks      = new Tasks($container);
        $this->metadata   = new Metadata($container);
        $this->dispatcher = new Dispatcher();
    }

    /**
     * @internal
     * @return list<Task>
     */
    public function getTasks(): array {
        return $this->tasks->getInstances();
    }

    /**
     * The first added tasks have a bigger priority.
     *
     * @template T of Task
     *
     * @param T|class-string<T> $task
     */
    public function addTask(Task|string $task, ?int $priority = null): static {
        $this->tasks->add($task, $priority);

        return $this;
    }

    /**
     * @template T of Task
     *
     * @param T|class-string<T> $task
     */
    public function removeTask(Task|string $task): static {
        $this->tasks->remove($task);

        return $this;
    }

    /**
     * The last added resolvers have a bigger priority.
     *
     * @template V of object
     * @template R of MetadataResolver<V>
     *
     * @param R|class-string<R> $metadata
     */
    public function addMetadata(MetadataResolver|string $metadata, ?int $priority = null): static {
        $this->metadata->addResolver($metadata, $priority);

        return $this;
    }

    /**
     * @template V of object
     * @template R of MetadataResolver<V>
     *
     * @param R|class-string<R> $metadata
     */
    public function removeMetadata(MetadataResolver|string $metadata): static {
        $this->metadata->removeResolver($metadata);

        return $this;
    }

    /**
     * @param array<array-key, string>|string $exclude glob(s) to exclude.
     */
    public function exclude(array|string $exclude): static {
        $this->exclude = array_merge($this->exclude, array_values((array) $exclude));

        return $this;
    }

    /**
     * @param Closure(Event): void $listener
     */
    public function addListener(Closure $listener): static {
        $this->dispatcher->attach($listener);

        return $this;
    }

    public function setAdapter(?Adapter $adapter): static {
        $this->adapter = $adapter;

        return $this;
    }

    public function run(DirectoryPath|FilePath $input, ?DirectoryPath $output = null): void {
        // Prepare
        $depth = match (true) {
            $input instanceof FilePath => 0,
            default                    => null,
        };
        $extensions = match (true) {
            $input instanceof FilePath => $input->getName(),
            !$this->tasks->has('*')    => array_map(static fn ($e) => "*.{$e}", $this->tasks->getKeys()),
            default                    => null,
        };
        $exclude   = $this->exclude;
        $directory = $input->getDirectoryPath('.');

        // If `$output` specified and inside `$input` we should not process it.
        if ($output !== null) {
            if (!$directory->isEqual($output) && $directory->isInside($output)) {
                $exclude[] = ((string) $directory->getRelativePath($output)).'/**'; // fixme(documentator): escape glob pattern?
            }
        } else {
            $output = $directory;
        }

        // Start
        try {
            $this->dispatcher->notify(new ProcessingStarted());

            try {
                $this->execute($directory, $output, $extensions, $exclude, $depth);
            } catch (ProcessorError $exception) {
                throw $exception;
            } catch (Exception $exception) {
                throw new ProcessingFailed($exception);
            }

            $this->dispatcher->notify(new ProcessingFinished(ProcessingFinishedResult::Success));
        } catch (Exception $exception) {
            $this->dispatcher->notify(new ProcessingFinished(ProcessingFinishedResult::Failed));

            throw $exception;
        }
    }

    /**
     * @param list<string>|string|null $include
     * @param list<string>             $exclude
     */
    protected function execute(
        DirectoryPath $input,
        DirectoryPath $output,
        array|string|null $include,
        array $exclude,
        ?int $depth,
    ): void {
        $adapter    = $this->adapter ?? new SymfonyAdapter();
        $filesystem = new FileSystem($this->dispatcher, $this->metadata, $adapter, $input, $output);
        $iterator   = new Iterator($filesystem, $filesystem->getFilesIterator($input, $include, $exclude, $depth));
        $executor   = new Executor($this->dispatcher, $this->tasks, $filesystem, $iterator, new Globs($exclude));

        $executor->run();
    }
}
