<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Processor\FileSystem;

use InvalidArgumentException;
use LastDragon_ru\LaraASP\Core\Utils\Path;
use LastDragon_ru\LaraASP\Documentator\Processor\Contracts\Metadata;
use LastDragon_ru\LaraASP\Documentator\Testing\Package\TestCase;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;

use function array_shift;
use function basename;
use function file_get_contents;
use function file_put_contents;
use function sprintf;

/**
 * @internal
 */
#[CoversClass(File::class)]
final class FileTest extends TestCase {
    public function testConstruct(): void {
        $path = Path::normalize(__FILE__);
        $file = new File($path, false);

        self::assertEquals($path, $file->getPath());
        self::assertEquals("{$path}", $file->getPath());
        self::assertEquals('php', $file->getExtension());
        self::assertEquals('FileTest.php', $file->getName());
    }

    public function testConstructNotNormalized(): void {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Path must be normalized, `/../file.txt` given.');

        new File('/../file.txt', false);
    }

    public function testConstructNotAbsolute(): void {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Path must be absolute, `../file.txt` given.');

        new File('../file.txt', false);
    }

    public function testConstructNotFile(): void {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage(sprintf('The `%s` is not a file.', Path::normalize(__DIR__)));

        new File(Path::normalize(Path::normalize(__DIR__)), false);
    }

    public function testGetContent(): void {
        $temp = Path::normalize(self::getTempFile(__FILE__)->getPathname());
        $file = new File($temp, false);

        self::assertEquals(__FILE__, $file->getContent());
        self::assertNotFalse(file_put_contents($temp, __DIR__));
        self::assertEquals(__DIR__, file_get_contents($temp));
        self::assertEquals(__FILE__, $file->getContent());
    }

    public function testSetContent(): void {
        $temp    = Path::normalize(self::getTempFile(__FILE__)->getPathname());
        $file    = new File($temp, false);
        $meta    = new class([1, 2]) implements Metadata {
            public function __construct(
                /**
                 * @var list<int>
                 */
                private array $value,
            ) {
                // empty
            }

            #[Override]
            public function __invoke(File $file): mixed {
                return array_shift($this->value);
            }
        };
        $current = $file->getMetadata($meta);

        self::assertEquals(__FILE__, $file->getContent());
        self::assertSame($current, $file->getMetadata($meta));
        self::assertNotFalse(file_put_contents($temp, __DIR__));
        self::assertSame($file, $file->setContent(__METHOD__));
        self::assertEquals(__DIR__, file_get_contents($temp));
        self::assertEquals(__METHOD__, $file->getContent());
        self::assertNotEquals($current, $file->getMetadata($meta));
    }

    public function testGetRelativePath(): void {
        $internal  = new File(Path::normalize(__FILE__), false);
        $directory = new Directory(Path::normalize(__DIR__), true);

        self::assertEquals(basename(__FILE__), $internal->getRelativePath($directory));
        self::assertEquals(basename(__FILE__), $internal->getRelativePath($internal));
    }
}
