<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Processor\Tasks\CodeLinks\Links;

use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\File;
use LastDragon_ru\LaraASP\Documentator\Processor\Metadata\FileSystem\Content;
use LastDragon_ru\LaraASP\Documentator\Processor\Metadata\Php\ClassObject;
use LastDragon_ru\LaraASP\Documentator\Processor\Metadata\Php\ClassObjectMetadata;
use LastDragon_ru\LaraASP\Documentator\Testing\Package\TestCase;
use LastDragon_ru\LaraASP\Testing\Mockery\PropertiesMock;
use LastDragon_ru\LaraASP\Testing\Mockery\WithProperties;
use Mockery;
use Override;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\EnumCase;
use PHPUnit\Framework\Attributes\CoversClass;

use function array_map;

/**
 * @internal
 */
#[CoversClass(ClassConstantLink::class)]
final class ClassConstantLinkTest extends TestCase {
    public function testToString(): void {
        self::assertSame('Class::Constant', (string) new ClassConstantLink('Class', 'Constant'));
        self::assertSame('App\\Class::Constant', (string) new ClassConstantLink('App\\Class', 'Constant'));
        self::assertSame(
            '\\App\\Class::Constant',
            (string) new ClassConstantLink('\\App\\Class', 'Constant'),
        );
    }

    public function testGetTitle(): void {
        self::assertSame('Class::Constant', (new ClassConstantLink('Class', 'Constant'))->getTitle());
        self::assertSame('Class::Constant', (new ClassConstantLink('App\\Class', 'Constant'))->getTitle());
        self::assertSame(
            'Class::Constant',
            (new ClassConstantLink('\\App\\Class', 'Constant'))->getTitle(),
        );
    }

    public function testGetTargetNodeClassConstant(): void {
        $file = Mockery::mock(File::class, new WithProperties(), PropertiesMock::class);
        $file->makePartial();
        $file
            ->shouldReceive('as')
            ->with(Content::class)
            ->once()
            ->andReturn(
                new Content(
                    <<<'PHP'
                    <?php declare(strict_types = 1);

                    class A {
                        public const Constant = 123;
                    }
                    PHP,
                ),
            );

        $link = new class ('A', 'Constant') extends ClassConstantLink {
            #[Override]
            public function getTargetNode(ClassLike $class): ?Node {
                return parent::getTargetNode($class);
            }
        };

        $resolver = $this->app()->make(ClassObjectMetadata::class);
        $class    = $resolver->resolve($file, ClassObject::class);
        $actual   = $link->getTargetNode($class->class);

        self::assertInstanceOf(ClassConst::class, $actual);
        self::assertEquals(
            ['Constant'],
            array_map(
                static fn ($const) => (string) $const->name,
                $actual->consts,
            ),
        );
    }

    public function testGetTargetNodeEnum(): void {
        $file = Mockery::mock(File::class, new WithProperties(), PropertiesMock::class);
        $file->makePartial();
        $file
            ->shouldReceive('as')
            ->with(Content::class)
            ->once()
            ->andReturn(
                new Content(
                    <<<'PHP'
                    <?php declare(strict_types = 1);

                    enum A {
                        case A;
                    }
                    PHP,
                ),
            );

        $link = new class ('A', 'A') extends ClassConstantLink {
            #[Override]
            public function getTargetNode(ClassLike $class): ?Node {
                return parent::getTargetNode($class);
            }
        };

        $resolver = $this->app()->make(ClassObjectMetadata::class);
        $class    = $resolver->resolve($file, ClassObject::class);
        $actual   = $link->getTargetNode($class->class);

        self::assertInstanceOf(EnumCase::class, $actual);
        self::assertSame('A', (string) $actual->name);
    }
}
