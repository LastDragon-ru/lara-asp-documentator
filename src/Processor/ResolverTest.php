<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Processor;

use ArrayIterator;
use Exception;
use IteratorAggregate;
use LastDragon_ru\LaraASP\Core\Path\DirectoryPath;
use LastDragon_ru\LaraASP\Core\Path\FilePath;
use LastDragon_ru\LaraASP\Documentator\Processor\Contracts\Dependency;
use LastDragon_ru\LaraASP\Documentator\Processor\Events\DependencyResolved;
use LastDragon_ru\LaraASP\Documentator\Processor\Events\DependencyResolvedResult;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\Directory;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\File;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\FileSystem;
use LastDragon_ru\LaraASP\Documentator\Testing\Package\TestCase;
use LastDragon_ru\LaraASP\Testing\Mockery\PropertiesMock;
use LastDragon_ru\LaraASP\Testing\Mockery\WithProperties;
use Mockery;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Traversable;

use function iterator_to_array;

/**
 * @internal
 */
#[CoversClass(Resolver::class)]
final class ResolverTest extends TestCase {
    public function testResolve(): void {
        $run        = static function (File $file, mixed $resolved): mixed {
            return $resolved;
        };
        $file       = Mockery::mock(File::class);
        $resolved   = Mockery::mock(File::class);
        $iterator   = Mockery::mock(Iterator::class);
        $dispatcher = Mockery::mock(Dispatcher::class);
        $filesystem = Mockery::mock(FileSystem::class);
        $dependency = Mockery::mock(Dependency::class);
        $dependency
            ->shouldReceive('__invoke')
            ->with($filesystem)
            ->once()
            ->andReturn($resolved);

        $resolver = Mockery::mock(Resolver::class, [$dispatcher, $iterator, $filesystem, $file, $run]);
        $resolver->shouldAllowMockingProtectedMethods();
        $resolver->makePartial();
        $resolver
            ->shouldReceive('notify')
            ->with($dependency, DependencyResolvedResult::Success)
            ->once()
            ->andReturns();

        self::assertSame($resolved, $resolver->resolve($dependency));
    }

    public function testResolveException(): void {
        $run        = static function (File $file, mixed $resolved): mixed {
            return $resolved;
        };
        $file       = Mockery::mock(File::class);
        $iterator   = Mockery::mock(Iterator::class);
        $exception  = new Exception();
        $dispatcher = Mockery::mock(Dispatcher::class);
        $filesystem = Mockery::mock(FileSystem::class);
        $dependency = Mockery::mock(Dependency::class);
        $dependency
            ->shouldReceive('__invoke')
            ->with($filesystem)
            ->once()
            ->andThrow($exception);

        $resolver = Mockery::mock(Resolver::class, [$dispatcher, $iterator, $filesystem, $file, $run]);
        $resolver->shouldAllowMockingProtectedMethods();
        $resolver->makePartial();
        $resolver
            ->shouldReceive('notify')
            ->with($dependency, DependencyResolvedResult::Failed)
            ->once()
            ->andReturns();

        self::expectExceptionObject($exception);

        $resolver->resolve($dependency);
    }

    public function testResolveTraversable(): void {
        $run        = static function (File $file, mixed $resolved): mixed {
            return $resolved;
        };
        $file       = Mockery::mock(File::class);
        $resolved   = Mockery::mock(Traversable::class);
        $iterator   = Mockery::mock(Iterator::class);
        $dispatcher = Mockery::mock(Dispatcher::class);
        $filesystem = Mockery::mock(FileSystem::class);
        $dependency = Mockery::mock(Dependency::class);
        $dependency
            ->shouldReceive('__invoke')
            ->with($filesystem)
            ->once()
            ->andReturn($resolved);

        $resolver = Mockery::mock(Resolver::class, [$dispatcher, $iterator, $filesystem, $file, $run]);
        $resolver->shouldAllowMockingProtectedMethods();
        $resolver->makePartial();
        $resolver
            ->shouldReceive('notify')
            ->with($dependency, DependencyResolvedResult::Success)
            ->once()
            ->andReturns();
        $resolver
            ->shouldReceive('iterate')
            ->with($dependency, $resolved)
            ->once()
            ->andReturn($resolved);

        self::assertSame($resolved, $resolver->resolve($dependency));
    }

    public function testIterate(): void {
        $run        = static function (File $file, mixed $resolved): mixed {
            return $resolved;
        };
        $file       = Mockery::mock(File::class);
        $aFile      = Mockery::mock(File::class);
        $bFile      = Mockery::mock(File::class);
        $files      = [1 => $aFile, 3 => $bFile];
        $resolved   = new ArrayIterator($files);
        $iterator   = Mockery::mock(Iterator::class);
        $dispatcher = Mockery::mock(Dispatcher::class);
        $filesystem = Mockery::mock(FileSystem::class);
        $dependency = Mockery::mock(Dependency::class);

        $resolver = Mockery::mock(Resolver::class, [$dispatcher, $iterator, $filesystem, $file, $run]);
        $resolver->shouldAllowMockingProtectedMethods();
        $resolver->makePartial();
        $resolver
            ->shouldReceive('notify')
            ->with($aFile, DependencyResolvedResult::Success)
            ->once()
            ->andReturns();
        $resolver
            ->shouldReceive('notify')
            ->with($bFile, DependencyResolvedResult::Success)
            ->once()
            ->andReturns();

        self::assertEquals(
            $files,
            iterator_to_array($resolver->iterate($dependency, $resolved)),
        );
    }

    public function testIterateException(): void {
        $run        = static function (File $file, mixed $resolved): mixed {
            return $resolved;
        };
        $file       = Mockery::mock(File::class);
        $exception  = new Exception();
        $resolved   = new class($exception) implements IteratorAggregate {
            public function __construct(
                protected Exception $exception,
            ) {
                // empty
            }

            #[Override]
            public function getIterator(): Traversable {
                throw $this->exception;
            }
        };
        $iterator   = Mockery::mock(Iterator::class);
        $dispatcher = Mockery::mock(Dispatcher::class);
        $filesystem = Mockery::mock(FileSystem::class);
        $dependency = Mockery::mock(Dependency::class);
        $resolver   = Mockery::mock(Resolver::class, [$dispatcher, $iterator, $filesystem, $file, $run]);
        $resolver->shouldAllowMockingProtectedMethods();
        $resolver->makePartial();
        $resolver
            ->shouldReceive('notify')
            ->with($dependency, DependencyResolvedResult::Failed)
            ->once()
            ->andReturn();

        self::expectExceptionObject($exception);

        iterator_to_array($resolver->iterate($dependency, $resolved));
    }

    public function testQueue(): void {
        $run      = static function (File $file, mixed $resolved): mixed {
            return $resolved;
        };
        $file     = Mockery::mock(File::class);
        $path     = new FilePath('path/to/a');
        $resolved = Mockery::mock(File::class)->shouldReceive('getPath')->once()->andReturn($path)->getMock();
        $iterator = Mockery::mock(Iterator::class);
        $iterator
            ->shouldReceive('push')
            ->with($path)
            ->once()
            ->andReturns();

        $dispatcher = Mockery::mock(Dispatcher::class);
        $filesystem = Mockery::mock(FileSystem::class);
        $dependency = Mockery::mock(Dependency::class);
        $dependency
            ->shouldReceive('__invoke')
            ->with($filesystem)
            ->once()
            ->andReturn($resolved);

        $resolver = Mockery::mock(Resolver::class, [$dispatcher, $iterator, $filesystem, $file, $run]);
        $resolver->shouldAllowMockingProtectedMethods();
        $resolver->makePartial();
        $resolver
            ->shouldReceive('notify')
            ->with($resolved, DependencyResolvedResult::Queued)
            ->once()
            ->andReturns();

        $resolver->queue($dependency);
    }

    public function testQueueTraversable(): void {
        $run      = static function (File $file, mixed $resolved): mixed {
            return $resolved;
        };
        $file     = Mockery::mock(File::class);
        $aPath    = new FilePath('path/to/a');
        $aFile    = Mockery::mock(File::class)->shouldReceive('getPath')->once()->andReturn($aPath)->getMock();
        $bPath    = new FilePath('path/to/b');
        $bFile    = Mockery::mock(File::class)->shouldReceive('getPath')->once()->andReturn($bPath)->getMock();
        $resolved = new ArrayIterator([$aFile, $bFile]);
        $iterator = Mockery::mock(Iterator::class);
        $iterator
            ->shouldReceive('push')
            ->with($aPath)
            ->once()
            ->andReturns();
        $iterator
            ->shouldReceive('push')
            ->with($bPath)
            ->once()
            ->andReturns();

        $dispatcher = Mockery::mock(Dispatcher::class);
        $filesystem = Mockery::mock(FileSystem::class);
        $dependency = Mockery::mock(Dependency::class);
        $dependency
            ->shouldReceive('__invoke')
            ->with($filesystem)
            ->once()
            ->andReturn($resolved);

        $resolver = Mockery::mock(Resolver::class, [$dispatcher, $iterator, $filesystem, $file, $run]);
        $resolver->shouldAllowMockingProtectedMethods();
        $resolver->makePartial();
        $resolver
            ->shouldReceive('notify')
            ->with($dependency, DependencyResolvedResult::Success)
            ->once()
            ->andReturns();
        $resolver
            ->shouldReceive('notify')
            ->with($aFile, DependencyResolvedResult::Queued)
            ->once()
            ->andReturns();
        $resolver
            ->shouldReceive('notify')
            ->with($bFile, DependencyResolvedResult::Queued)
            ->once()
            ->andReturns();

        $resolver->queue($dependency);
    }

    public function testCheck(): void {
        $exception = new Exception();
        $resolver  = new class($exception) extends Resolver {
            /** @noinspection PhpMissingParentConstructorInspection */
            public function __construct(Exception $exception) {
                $this->exception = $exception;
            }

            public function getException(): ?Exception {
                return $this->exception;
            }
        };

        $thrown = null;

        try {
            $resolver->check();
        } catch (Exception $e) {
            $thrown = $e;
        }

        self::assertSame($exception, $thrown);
        self::assertNull($resolver->getException());

        $resolver->check();
    }

    public function testNotify(): void {
        $filesystem = Mockery::mock(FileSystem::class);
        $filesystem
            ->shouldReceive('getPathname')
            ->twice()
            ->andReturnUsing(
                static function (Directory|DirectoryPath|File|FilePath $path): string {
                    return (string) $path;
                },
            );

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher
            ->shouldReceive('notify')
            ->once()
            ->with(
                Mockery::isEqual(
                    new DependencyResolved(
                        'path/to/dependency',
                        DependencyResolvedResult::Success,
                    ),
                ),
            )
            ->andReturn();
        $dispatcher
            ->shouldReceive('notify')
            ->once()
            ->with(
                Mockery::isEqual(
                    new DependencyResolved(
                        'path/to/file',
                        DependencyResolvedResult::Missed,
                    ),
                ),
            )
            ->andReturn();

        $resolver = Mockery::mock(Resolver::class, new WithProperties(), PropertiesMock::class);
        $resolver->shouldAllowMockingProtectedMethods();
        $resolver->makePartial();
        $resolver
            ->shouldUseProperty('fs')
            ->value($filesystem);
        $resolver
            ->shouldUseProperty('dispatcher')
            ->value($dispatcher);

        $file = Mockery::mock(File::class);
        $file
            ->shouldReceive('__toString')
            ->once()
            ->andReturn(new FilePath('path/to/file'));

        $dependency = Mockery::mock(Dependency::class);
        $dependency
            ->shouldReceive('getPath')
            ->with($filesystem)
            ->once()
            ->andReturn(
                new FilePath('path/to/dependency'),
            );

        $resolver->notify($file, DependencyResolvedResult::Missed);
        $resolver->notify($dependency, DependencyResolvedResult::Success);
    }
}
