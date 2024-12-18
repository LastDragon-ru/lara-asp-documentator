<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Processor\FileSystem;

use LastDragon_ru\LaraASP\Core\Path\DirectoryPath;
use LastDragon_ru\LaraASP\Core\Path\FilePath;
use LastDragon_ru\LaraASP\Documentator\Testing\Package\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use SplFileInfo;

use function array_map;
use function basename;
use function file_get_contents;
use function iterator_to_array;

/**
 * @internal
 */
#[CoversClass(FileSystem::class)]
final class FileSystemTest extends TestCase {
    public function testGetFile(): void {
        $fs           = new FileSystem();
        $directory    = new Directory((new DirectoryPath(__DIR__))->getNormalizedPath());
        $readonly     = $fs->getFile($directory, __FILE__);
        $relative     = $fs->getFile($directory, basename(__FILE__));
        $notfound     = $fs->getFile($directory, 'not found');
        $writable     = new Directory((new DirectoryPath(__DIR__))->getNormalizedPath());
        $internal     = $fs->getFile($writable, self::getTestData()->path('c.html'));
        $external     = $fs->getFile($writable, '../Processor.php');
        $file         = new File((new FilePath(self::getTestData()->path('c.txt')))->getNormalizedPath());
        $fromFile     = $fs->getFile($writable, $file);
        $splFile      = new SplFileInfo((string) $file);
        $fromSplFile  = $fs->getFile($writable, $splFile);
        $fromFilePath = $fs->getFile($writable, $file->getPath());

        self::assertNotNull($readonly);
        self::assertEquals(
            (string) (new FilePath(__FILE__))->getNormalizedPath(),
            (string) $readonly,
        );

        self::assertNotNull($relative);
        self::assertEquals(
            (string) (new FilePath(__FILE__))->getNormalizedPath(),
            (string) $relative,
        );

        self::assertNull($notfound);

        self::assertNotNull($internal);
        self::assertEquals(
            (string) (new FilePath(self::getTestData()->path('c.html')))->getNormalizedPath(),
            (string) $internal,
        );

        self::assertNotNull($external);
        self::assertEquals(
            (string) (new FilePath(__FILE__))->getFilePath('../Processor.php'),
            (string) $external,
        );

        self::assertNotNull($fromFile);
        self::assertEquals($file->getPath(), $fromFile->getPath());
        self::assertEquals(
            (string) (new FilePath(self::getTestData()->path('c.txt')))->getNormalizedPath(),
            (string) $fromFile,
        );

        self::assertNotNull($fromSplFile);
        self::assertEquals($file->getPath(), $fromSplFile->getPath());
        self::assertEquals(
            (string) (new FilePath(self::getTestData()->path('c.txt')))->getNormalizedPath(),
            (string) $fromSplFile,
        );

        self::assertNotNull($fromFilePath);
        self::assertEquals($file->getPath(), $fromFilePath->getPath());
        self::assertEquals(
            (string) (new FilePath(self::getTestData()->path('c.txt')))->getNormalizedPath(),
            (string) $fromFilePath,
        );
    }

    public function testGetDirectory(): void {
        // Prepare
        $fs        = new FileSystem();
        $directory = new Directory((new DirectoryPath(__DIR__))->getParentPath());
        $writable  = new Directory($directory->getPath());

        // Self
        self::assertSame(
            $fs->getDirectory($directory, '.'),
            $fs->getDirectory($directory, ''),
        );

        // Readonly
        $readonly = $fs->getDirectory($directory, __DIR__);

        self::assertNotNull($readonly);
        self::assertEquals(
            (string) (new DirectoryPath(__DIR__))->getNormalizedPath(),
            (string) $readonly,
        );

        // Relative
        $relative = $fs->getDirectory($directory, basename(__DIR__));

        self::assertNotNull($relative);
        self::assertEquals(
            (string) (new DirectoryPath(__DIR__))->getNormalizedPath(),
            (string) $relative,
        );

        // Not directory
        $notDirectory = $fs->getDirectory($directory, 'not directory');

        self::assertNull($notDirectory);

        // Internal
        $internalPath = self::getTestData()->path('a');
        $internal     = $fs->getDirectory($writable, $internalPath);

        self::assertNotNull($internal);
        self::assertEquals($internalPath, (string) $internal);

        // External
        $external = $fs->getDirectory($writable, '../Testing');

        self::assertNotNull($external);
        self::assertEquals(
            (string) (new DirectoryPath(__DIR__))->getDirectoryPath('../../Testing'),
            (string) $external,
        );

        // From File
        $filePath = (new FilePath(self::getTestData()->path('c.html')))->getNormalizedPath();
        $fromFile = $fs->getDirectory($writable, new File($filePath));

        self::assertNotNull($fromFile);
        self::assertEquals(
            (string) (new DirectoryPath(self::getTestData()->path('')))->getNormalizedPath(),
            (string) $fromFile,
        );

        // From FilePath
        $filePath     = (new FilePath(self::getTestData()->path('c.html')))->getNormalizedPath();
        $fromFilePath = $fs->getDirectory($writable, $filePath);

        self::assertNotNull($fromFilePath);
        self::assertEquals(
            (string) (new DirectoryPath(self::getTestData()->path('')))->getNormalizedPath(),
            (string) $fromFilePath,
        );

        // From SplFileInfo
        $spl     = new SplFileInfo(self::getTestData()->path('b'));
        $fromSpl = $fs->getDirectory($writable, $spl);

        self::assertNotNull($fromSpl);
        self::assertEquals(
            (string) (new DirectoryPath($spl->getPathname()))->getNormalizedPath(),
            (string) $fromSpl,
        );

        // From Directory
        $directoryPath = (new DirectoryPath(self::getTestData()->path('a/a')))->getNormalizedPath();
        $directory     = new Directory($directoryPath);
        $fromDirectory = $fs->getDirectory($writable, $directory);

        self::assertNotNull($fromDirectory);
        self::assertEquals((string) $directory, (string) $fromDirectory);

        // From DirectoryPath
        $directoryPath     = (new DirectoryPath(self::getTestData()->path('a/a')))->getNormalizedPath();
        $fromDirectoryPath = $fs->getDirectory($writable, $directoryPath);

        self::assertNotNull($fromDirectoryPath);
        self::assertEquals((string) $directoryPath, (string) $fromDirectoryPath);
    }

    public function testGetFilesIterator(): void {
        $fs        = new FileSystem();
        $root      = (new DirectoryPath(self::getTestData()->path('')))->getNormalizedPath();
        $directory = new Directory($root);
        $map       = static function (File $file) use ($directory): string {
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
            array_map($map, iterator_to_array($fs->getFilesIterator($directory))),
        );

        self::assertEquals(
            [
                'a/a.html',
                'b/b.html',
                'c.html',
            ],
            array_map($map, iterator_to_array($fs->getFilesIterator($directory, '*.html'))),
        );

        self::assertEquals(
            [
                'c.html',
                'c.txt',
            ],
            array_map($map, iterator_to_array($fs->getFilesIterator($directory, depth: 0))),
        );

        self::assertEquals(
            [
                'c.html',
            ],
            array_map($map, iterator_to_array($fs->getFilesIterator($directory, '*.html', 0))),
        );

        self::assertEquals(
            [
                'a/a.html',
                'b/b.html',
                'c.html',
            ],
            array_map($map, iterator_to_array($fs->getFilesIterator($directory, exclude: ['#.*?\.txt$#']))),
        );
    }

    public function testGetDirectoriesIterator(): void {
        $fs        = new FileSystem();
        $root      = (new DirectoryPath(self::getTestData()->path('')))->getNormalizedPath();
        $directory = new Directory($root);
        $map       = static function (Directory $dir) use ($directory): string {
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
            array_map($map, iterator_to_array($fs->getDirectoriesIterator($directory))),
        );

        self::assertEquals(
            [
                'a',
                'b',
            ],
            array_map($map, iterator_to_array($fs->getDirectoriesIterator($directory, depth: 0))),
        );

        self::assertEquals(
            [
                'a',
                'b',
                'b/a',
                'b/b',
            ],
            array_map($map, iterator_to_array($fs->getDirectoriesIterator($directory, exclude: '#^a/[^/]*?$#'))),
        );

        self::assertEquals(
            [
                'a',
                'a/b',
                'b',
                'b/b',
            ],
            array_map($map, iterator_to_array($fs->getDirectoriesIterator($directory, exclude: '#^[^/]*?/a$#'))),
        );
    }

    public function testSaveInsideRoot(): void {
        $temp = (new FilePath(self::getTempFile(__FILE__)->getPathname()))->getNormalizedPath();
        $file = new File($temp);
        $fs   = new FileSystem($temp->getDirectoryPath());

        self::assertTrue($fs->save($file)); // because no changes

        self::assertSame($file, $file->setContent(__METHOD__));

        self::assertTrue($fs->save($file));

        self::assertEquals(__METHOD__, file_get_contents((string) $temp));
    }

    public function testSaveOutsideRoot(): void {
        $fs   = new FileSystem(new DirectoryPath(__DIR__));
        $temp = (new FilePath(self::getTempFile(__FILE__)->getPathname()))->getNormalizedPath();
        $file = new File($temp);

        self::assertTrue($fs->save($file)); // because no changes

        self::assertSame($file, $file->setContent(__METHOD__));

        self::assertFalse($fs->save($file));

        self::assertEquals(__FILE__, file_get_contents((string) $temp));
    }

    public function testCache(): void {
        $fs        = new FileSystem();
        $dir       = new Directory((new DirectoryPath(__DIR__))->getNormalizedPath());
        $file      = $fs->getFile($dir, __FILE__);
        $directory = $fs->getDirectory($dir, __DIR__);

        self::assertNotNull($file);
        self::assertSame($file, $fs->getFile($dir, __FILE__));

        self::assertNotNull($directory);
        self::assertSame($directory, $fs->getDirectory($dir, __DIR__));
    }
}
