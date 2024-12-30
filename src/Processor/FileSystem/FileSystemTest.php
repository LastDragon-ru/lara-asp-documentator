<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Processor\FileSystem;

use Exception;
use LastDragon_ru\LaraASP\Core\Path\DirectoryPath;
use LastDragon_ru\LaraASP\Core\Path\FilePath;
use LastDragon_ru\LaraASP\Documentator\Processor\Exceptions\DirectoryNotFound;
use LastDragon_ru\LaraASP\Documentator\Processor\Exceptions\FileCreateFailed;
use LastDragon_ru\LaraASP\Documentator\Processor\Exceptions\FileNotFound;
use LastDragon_ru\LaraASP\Documentator\Processor\Exceptions\FileNotWritable;
use LastDragon_ru\LaraASP\Documentator\Processor\Metadata\Content;
use LastDragon_ru\LaraASP\Documentator\Testing\Package\TestCase;
use LastDragon_ru\LaraASP\Documentator\Testing\Package\WithProcessor;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;

use function array_map;
use function basename;
use function iterator_to_array;

/**
 * @internal
 */
#[CoversClass(FileSystem::class)]
final class FileSystemTest extends TestCase {
    use WithProcessor;

    public function testGetFile(): void {
        $fs           = $this->getFileSystem(__DIR__);
        $path         = (new FilePath(self::getTestData()->path('c.txt')))->getNormalizedPath();
        $file         = $fs->getFile($path);
        $readonly     = $fs->getFile(__FILE__);
        $relative     = $fs->getFile(basename(__FILE__));
        $internal     = $fs->getFile(self::getTestData()->path('c.html'));
        $external     = $fs->getFile('../Processor.php');
        $fromFilePath = $fs->getFile($path);

        self::assertEquals(
            (string) (new FilePath(__FILE__))->getNormalizedPath(),
            (string) $readonly,
        );

        self::assertEquals(
            (string) (new FilePath(__FILE__))->getNormalizedPath(),
            (string) $relative,
        );

        self::assertEquals(
            (string) (new FilePath(self::getTestData()->path('c.html')))->getNormalizedPath(),
            (string) $internal,
        );

        self::assertEquals(
            (string) (new FilePath(__FILE__))->getFilePath('../Processor.php'),
            (string) $external,
        );

        self::assertEquals($file->getPath(), $fromFilePath->getPath());
        self::assertEquals(
            (string) (new FilePath(self::getTestData()->path('c.txt')))->getNormalizedPath(),
            (string) $fromFilePath,
        );
    }

    public function testGetFileNotFound(): void {
        self::expectException(FileNotFound::class);

        $this->getFileSystem(__DIR__)->getFile('not found');
    }

    public function testGetDirectory(): void {
        // Prepare
        $fs = $this->getFileSystem(__DIR__.'/..');

        // Self
        self::assertSame(
            $fs->getDirectory('.'),
            $fs->getDirectory(''),
        );

        // Readonly
        $readonly = $fs->getDirectory(__DIR__);

        self::assertEquals(
            (string) (new DirectoryPath(__DIR__))->getNormalizedPath(),
            (string) $readonly,
        );

        // Relative
        $relative = $fs->getDirectory(basename(__DIR__));

        self::assertEquals(
            (string) (new DirectoryPath(__DIR__))->getNormalizedPath(),
            (string) $relative,
        );

        // Internal
        $internalPath = self::getTestData()->path('a');
        $internal     = $fs->getDirectory($internalPath);

        self::assertEquals($internalPath, (string) $internal);

        // External
        $external = $fs->getDirectory('../Testing');

        self::assertEquals(
            (string) (new DirectoryPath(__DIR__))->getDirectoryPath('../../Testing'),
            (string) $external,
        );

        // From FilePath
        $filePath     = (new FilePath(self::getTestData()->path('c.html')))->getNormalizedPath();
        $fromFilePath = $fs->getDirectory($filePath);

        self::assertEquals(
            (string) (new DirectoryPath(self::getTestData()->path('')))->getNormalizedPath(),
            (string) $fromFilePath,
        );

        // From DirectoryPath
        $directoryPath     = (new DirectoryPath(self::getTestData()->path('a/a')))->getNormalizedPath();
        $fromDirectoryPath = $fs->getDirectory($directoryPath);

        self::assertEquals((string) $directoryPath, (string) $fromDirectoryPath);
    }

    public function testGetDirectoryNotFound(): void {
        self::expectException(DirectoryNotFound::class);

        $this->getFileSystem(__DIR__)->getDirectory('not found');
    }

    public function testGetFilesIterator(): void {
        $input      = (new DirectoryPath(self::getTestData()->path('')))->getNormalizedPath();
        $filesystem = $this->getFileSystem($input);
        $directory  = $filesystem->getDirectory($input);
        $map        = static function (File $file) use ($directory): string {
            return (string) $directory->getRelativePath($file);
        };

        self::assertEquals(
            [
                'a/a.html',
                'a/a.txt',
                'a/a/aa.txt',
                'a/b/ab.txt',
                'b/a/ba.txt',
                'b/b.html',
                'b/b.txt',
                'b/b/bb.txt',
                'c.html',
                'c.txt',
            ],
            array_map($map, iterator_to_array($filesystem->getFilesIterator($directory))),
        );

        self::assertEquals(
            [
                'a/a.html',
                'b/b.html',
                'c.html',
            ],
            array_map($map, iterator_to_array($filesystem->getFilesIterator($directory, '*.html'))),
        );

        self::assertEquals(
            [
                'c.html',
                'c.txt',
            ],
            array_map($map, iterator_to_array($filesystem->getFilesIterator($directory, depth: 0))),
        );

        self::assertEquals(
            [
                'c.html',
            ],
            array_map($map, iterator_to_array($filesystem->getFilesIterator($directory, '*.html', 0))),
        );

        self::assertEquals(
            [
                'a/a.html',
                'b/b.html',
                'c.html',
            ],
            array_map($map, iterator_to_array($filesystem->getFilesIterator($directory, exclude: ['#.*?\.txt$#']))),
        );
    }

    public function testGetDirectoriesIterator(): void {
        $input      = (new DirectoryPath(self::getTestData()->path('')))->getNormalizedPath();
        $filesystem = $this->getFileSystem($input);
        $directory  = $filesystem->getDirectory($input);
        $map        = static function (Directory $dir) use ($directory): string {
            return (string) $directory->getRelativePath($dir);
        };

        self::assertEquals(
            [
                'a',
                'a/a',
                'a/b',
                'b',
                'b/a',
                'b/b',
            ],
            array_map($map, iterator_to_array($filesystem->getDirectoriesIterator($directory))),
        );

        self::assertEquals(
            [
                'a',
                'b',
            ],
            array_map($map, iterator_to_array($filesystem->getDirectoriesIterator($directory, depth: 0))),
        );

        self::assertEquals(
            [
                'a',
                'b',
                'b/a',
                'b/b',
            ],
            array_map(
                $map,
                iterator_to_array($filesystem->getDirectoriesIterator($directory, exclude: '#^a/[^/]*?$#')),
            ),
        );

        self::assertEquals(
            [
                'a',
                'a/b',
                'b',
                'b/b',
            ],
            array_map(
                $map,
                iterator_to_array($filesystem->getDirectoriesIterator($directory, exclude: '#^[^/]*?/a$#')),
            ),
        );
    }

    public function testWriteFile(): void {
        $input      = (new DirectoryPath(self::getTestData()->path('')))->getNormalizedPath();
        $path       = $input->getFilePath('file.md');
        $file       = Mockery::mock(File::class);
        $content    = 'content';
        $metadata   = Mockery::mock(MetadataStorage::class);
        $filesystem = Mockery::mock(FileSystem::class, [$metadata, $input, $input]);
        $filesystem->shouldAllowMockingProtectedMethods();
        $filesystem->makePartial();
        $filesystem
            ->shouldReceive('save')
            ->never();
        $filesystem
            ->shouldReceive('change')
            ->with($file, $content)
            ->once()
            ->andReturns();

        $metadata
            ->shouldReceive('reset')
            ->with($file)
            ->once()
            ->andReturns();
        $metadata
            ->shouldReceive('has')
            ->with($file, Content::class)
            ->once()
            ->andReturn(false);
        $metadata
            ->shouldReceive('set')
            ->with($file, Content::class, $content)
            ->once()
            ->andReturns();

        $file
            ->shouldReceive('getPath')
            ->once()
            ->andReturn($path);

        $filesystem->write($file, $content);
    }

    public function testWriteFileNoChanges(): void {
        $input      = (new DirectoryPath(self::getTestData()->path('')))->getNormalizedPath();
        $path       = $input->getFilePath('file.md');
        $file       = Mockery::mock(File::class);
        $content    = 'content';
        $metadata   = Mockery::mock(MetadataStorage::class);
        $filesystem = Mockery::mock(FileSystem::class, [$metadata, $input, $input]);
        $filesystem->shouldAllowMockingProtectedMethods();
        $filesystem->makePartial();
        $filesystem
            ->shouldReceive('save')
            ->never();
        $filesystem
            ->shouldReceive('change')
            ->with($file, $content)
            ->never();

        $metadata
            ->shouldReceive('reset')
            ->with($file)
            ->never();
        $metadata
            ->shouldReceive('has')
            ->with($file, Content::class)
            ->once()
            ->andReturn(true);
        $metadata
            ->shouldReceive('get')
            ->with($file, Content::class)
            ->once()
            ->andReturn($content);
        $metadata
            ->shouldReceive('set')
            ->with($file, Content::class, $content)
            ->never();

        $file
            ->shouldReceive('getPath')
            ->once()
            ->andReturn($path);

        $filesystem->write($file, $content);
    }

    public function testWriteCreate(): void {
        $input      = (new DirectoryPath(self::getTestData()->path('')))->getNormalizedPath();
        $path       = $input->getFilePath('file.md');
        $file       = Mockery::mock(File::class);
        $content    = 'content';
        $metadata   = Mockery::mock(MetadataStorage::class);
        $filesystem = Mockery::mock(FileSystem::class, [$metadata, $input, $input]);
        $filesystem->shouldAllowMockingProtectedMethods();
        $filesystem->makePartial();
        $filesystem
            ->shouldReceive('isFile')
            ->with($path)
            ->once()
            ->andReturn(false);
        $filesystem
            ->shouldReceive('getFile')
            ->with($path)
            ->once()
            ->andReturn($file);
        $filesystem
            ->shouldReceive('save')
            ->with($path, $content)
            ->once()
            ->andReturns();
        $filesystem
            ->shouldReceive('change')
            ->never();

        $metadata
            ->shouldReceive('reset')
            ->with($file)
            ->once()
            ->andReturns();
        $metadata
            ->shouldReceive('has')
            ->with($file, Content::class)
            ->once()
            ->andReturn(false);
        $metadata
            ->shouldReceive('set')
            ->with($file, Content::class, $content)
            ->once()
            ->andReturns();

        $filesystem->write($path, $content);
    }

    public function testWriteCreateFailed(): void {
        self::expectException(FileCreateFailed::class);

        $input      = (new DirectoryPath(self::getTestData()->path('')))->getNormalizedPath();
        $path       = $input->getFilePath('file.md');
        $content    = 'content';
        $metadata   = Mockery::mock(MetadataStorage::class);
        $filesystem = Mockery::mock(FileSystem::class, [$metadata, $input, $input]);
        $filesystem->shouldAllowMockingProtectedMethods();
        $filesystem->makePartial();
        $filesystem
            ->shouldReceive('isFile')
            ->with($path)
            ->once()
            ->andReturn(false);
        $filesystem
            ->shouldReceive('save')
            ->with($path, $content)
            ->once()
            ->andThrow(Exception::class);
        $filesystem
            ->shouldReceive('change')
            ->never();

        $filesystem->write($path, $content);
    }

    public function testWriteOutsideOutput(): void {
        self::expectException(FileNotWritable::class);

        $path = (new DirectoryPath(self::getTestData()->path('')))->getNormalizedPath();
        $fs   = $this->getFileSystem($path);
        $file = $fs->getFile(__FILE__);

        $fs->write($file, 'outside output');
    }

    public function testCache(): void {
        $fs        = $this->getFileSystem(__DIR__);
        $file      = $fs->getFile(__FILE__);
        $directory = $fs->getDirectory(__DIR__);

        self::assertSame($file, $fs->getFile(__FILE__));

        self::assertSame($directory, $fs->getDirectory(__DIR__));
    }
}
