<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Processor\Tasks\Preprocess;

use LastDragon_ru\LaraASP\Core\Application\ContainerResolver;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\Directory;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\File;
use LastDragon_ru\LaraASP\Documentator\Processor\Tasks\Preprocess\Contracts\Instruction;
use LastDragon_ru\LaraASP\Documentator\Processor\Tasks\Preprocess\Contracts\Parameters;
use LastDragon_ru\LaraASP\Documentator\Testing\Package\ProcessorHelper;
use LastDragon_ru\LaraASP\Documentator\Testing\Package\TestCase;
use LastDragon_ru\LaraASP\Serializer\Contracts\Serializable;
use LastDragon_ru\LaraASP\Serializer\Contracts\Serializer;
use LastDragon_ru\LaraASP\Testing\Mockery\MockProperties;
use Mockery;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * @internal
 */
#[CoversClass(Task::class)]
final class TaskTest extends TestCase {
    private const MARKDOWN = <<<'MARKDOWN'
        Bla bla bla [processable]: ./path/to/file should be ignored.

        [unknown]: ./path/to/file (should not be parsed)

        [test:empty]: <./path/to/file "value">

        [test:instruction]: ./path/to/file

        [test:instruction]: <./path/to/file>
        [//]: # (start: hash)

        [test:instruction]: ./path/to/file
        [//]: # (start: nested-hash)

        outdated

        [//]: # (end: nested-hash)

        [//]: # (end: hash)

        [test:instruction]: ./path/to/file

        [test:instruction]: ./path/to/file

        [test:instruction]: ./path/to/file/parametrized ({"a": "aa", "b": {"a": "a", "b": "b"}})

        [test:instruction]: ./path/to/file/parametrized ({"b":{ "b": "b","a": "a"},"a":"aa"})
        MARKDOWN;

    public function testParse(): void {
        $a    = new TaskTest__EmptyInstruction();
        $b    = new TaskTest__TestInstruction();
        $task = Mockery::mock(Task::class, MockProperties::class);
        $task->shouldAllowMockingProtectedMethods();
        $task->makePartial();
        $task
            ->shouldUseProperty('container')
            ->value(
                $this->app()->make(ContainerResolver::class),
            );
        $task
            ->shouldUseProperty('serializer')
            ->value(
                $this->app()->make(Serializer::class),
            );

        $task->addInstruction($a::class);
        $task->addInstruction($b);

        $file = Mockery::mock(File::class);
        $file
            ->shouldReceive('getContent')
            ->once()
            ->andReturn(self::MARKDOWN);

        $root   = Mockery::mock(Directory::class);
        $tokens = $task->parse($root, $file);

        self::assertEquals(
            new TokenList([
                '036f5cd95d39a2990511d9602015ccd8b4da87a199f021f507527c66bddc0fd4' => new Token(
                    $a,
                    new Context($root, $file, '<./path/to/file "value">', null),
                    './path/to/file "value"',
                    new TaskTest__ParametersEmpty('./path/to/file "value"'),
                    [
                        '[test:empty]: <./path/to/file "value">' => '[test:empty]: <./path/to/file "value">',
                    ],
                ),
                '482df4f411df199a43077cfefb8251f4e320a0dcc4de0005598872dc2aee76b2' => new Token(
                    $b,
                    new Context($root, $file, './path/to/file', null),
                    './path/to/file',
                    new TaskTest__Parameters('./path/to/file'),
                    [
                        // phpcs:disable Squiz.Arrays.ArrayDeclaration.DoubleArrowNotAligned
                        '[test:instruction]: ./path/to/file' => '[test:instruction]: ./path/to/file',
                        <<<'TXT'
                        [test:instruction]: <./path/to/file>
                        [//]: # (start: hash)

                        [test:instruction]: ./path/to/file
                        [//]: # (start: nested-hash)

                        outdated

                        [//]: # (end: nested-hash)

                        [//]: # (end: hash)
                        TXT
                                                             => '[test:instruction]: <./path/to/file>',
                        // phpcs:enable
                    ],
                ),
                '5c77db20daf8999d844774772dce6db762c2c45f2e4f6993812bcaaeeb34e02d' => new Token(
                    $b,
                    new Context(
                        $root,
                        $file,
                        './path/to/file/parametrized',
                        '{"a": "aa", "b": {"a": "a", "b": "b"}}',
                    ),
                    './path/to/file/parametrized',
                    new TaskTest__Parameters(
                        './path/to/file/parametrized',
                        'aa',
                        [
                            'a' => 'a',
                            'b' => 'b',
                        ],
                    ),
                    [
                        '[test:instruction]: ./path/to/file/parametrized ({"a": "aa", "b": {"a": "a", "b": "b"}})' => ''
                            .'[test:instruction]: ./path/to/file/parametrized ({"a": "aa", "b": {"a": "a", "b": "b"}})',
                        '[test:instruction]: ./path/to/file/parametrized ({"b":{ "b": "b","a": "a"},"a":"aa"})'    => ''
                            .'[test:instruction]: ./path/to/file/parametrized ({"b":{ "b": "b","a": "a"},"a":"aa"})',
                    ],
                ),
            ]),
            $tokens,
        );
    }

    public function testInvoke(): void {
        $task   = $this->app()->make(Task::class)
            ->addInstruction(new TaskTest__EmptyInstruction())
            ->addInstruction(new TaskTest__TestInstruction());
        $actual = null;
        $file   = Mockery::mock(File::class);
        $file
            ->shouldReceive('getContent')
            ->atLeast()
            ->once()
            ->andReturn(static::MARKDOWN);
        $file
            ->shouldReceive('setContent')
            ->once()
            ->andReturnUsing(
                static function (string $content) use ($file, &$actual): File {
                    $actual = $content;

                    return $file;
                },
            );

        $root   = Mockery::mock(Directory::class);
        $result = ProcessorHelper::runTask($task, $root, $file);

        self::assertTrue($result);
        self::assertEquals(
            <<<'MARKDOWN'
            Bla bla bla [processable]: ./path/to/file should be ignored.

            [unknown]: ./path/to/file (should not be parsed)

            [test:empty]: <./path/to/file "value">
            [//]: # (start: 036f5cd95d39a2990511d9602015ccd8b4da87a199f021f507527c66bddc0fd4)
            [//]: # (warning: Generated automatically. Do not edit.)
            [//]: # (empty)
            [//]: # (end: 036f5cd95d39a2990511d9602015ccd8b4da87a199f021f507527c66bddc0fd4)

            [test:instruction]: ./path/to/file
            [//]: # (start: 482df4f411df199a43077cfefb8251f4e320a0dcc4de0005598872dc2aee76b2)
            [//]: # (warning: Generated automatically. Do not edit.)

            result({"target":".\/path\/to\/file","a":"a","b":[]})

            [//]: # (end: 482df4f411df199a43077cfefb8251f4e320a0dcc4de0005598872dc2aee76b2)

            [test:instruction]: <./path/to/file>
            [//]: # (start: 482df4f411df199a43077cfefb8251f4e320a0dcc4de0005598872dc2aee76b2)
            [//]: # (warning: Generated automatically. Do not edit.)

            result({"target":".\/path\/to\/file","a":"a","b":[]})

            [//]: # (end: 482df4f411df199a43077cfefb8251f4e320a0dcc4de0005598872dc2aee76b2)

            [test:instruction]: ./path/to/file
            [//]: # (start: 482df4f411df199a43077cfefb8251f4e320a0dcc4de0005598872dc2aee76b2)
            [//]: # (warning: Generated automatically. Do not edit.)

            result({"target":".\/path\/to\/file","a":"a","b":[]})

            [//]: # (end: 482df4f411df199a43077cfefb8251f4e320a0dcc4de0005598872dc2aee76b2)

            [test:instruction]: ./path/to/file
            [//]: # (start: 482df4f411df199a43077cfefb8251f4e320a0dcc4de0005598872dc2aee76b2)
            [//]: # (warning: Generated automatically. Do not edit.)

            result({"target":".\/path\/to\/file","a":"a","b":[]})

            [//]: # (end: 482df4f411df199a43077cfefb8251f4e320a0dcc4de0005598872dc2aee76b2)

            [test:instruction]: ./path/to/file/parametrized ({"a": "aa", "b": {"a": "a", "b": "b"}})
            [//]: # (start: 5c77db20daf8999d844774772dce6db762c2c45f2e4f6993812bcaaeeb34e02d)
            [//]: # (warning: Generated automatically. Do not edit.)

            result({"target":".\/path\/to\/file\/parametrized","a":"aa","b":{"a":"a","b":"b"}})

            [//]: # (end: 5c77db20daf8999d844774772dce6db762c2c45f2e4f6993812bcaaeeb34e02d)

            [test:instruction]: ./path/to/file/parametrized ({"b":{ "b": "b","a": "a"},"a":"aa"})
            [//]: # (start: 5c77db20daf8999d844774772dce6db762c2c45f2e4f6993812bcaaeeb34e02d)
            [//]: # (warning: Generated automatically. Do not edit.)

            result({"target":".\/path\/to\/file\/parametrized","a":"aa","b":{"a":"a","b":"b"}})

            [//]: # (end: 5c77db20daf8999d844774772dce6db762c2c45f2e4f6993812bcaaeeb34e02d)
            MARKDOWN,
            $actual,
        );
    }
}

// @phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
// @phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps

/**
 * @internal
 * @noinspection PhpMultipleClassesDeclarationsInOneFile
 *
 * @implements Instruction<TaskTest__ParametersEmpty>
 */
class TaskTest__EmptyInstruction implements Instruction {
    #[Override]
    public static function getName(): string {
        return 'test:empty';
    }

    #[Override]
    public static function getParameters(): string {
        return TaskTest__ParametersEmpty::class;
    }

    #[Override]
    public function __invoke(Context $context, string $target, mixed $parameters): string {
        return '';
    }
}

/**
 * @internal
 * @noinspection PhpMultipleClassesDeclarationsInOneFile
 *
 * @implements Instruction<TaskTest__Parameters>
 */
class TaskTest__TestInstruction implements Instruction {
    #[Override]
    public static function getName(): string {
        return 'test:instruction';
    }

    #[Override]
    public static function getParameters(): string {
        return TaskTest__Parameters::class;
    }

    #[Override]
    public function __invoke(Context $context, string $target, mixed $parameters): string {
        return 'result('.json_encode($parameters, JSON_THROW_ON_ERROR).')';
    }
}

/**
 * @internal
 * @noinspection PhpMultipleClassesDeclarationsInOneFile
 */
class TaskTest__Parameters implements Parameters, Serializable {
    /**
     * @param array<string, string> $b
     */
    public function __construct(
        public readonly string $target,
        public readonly string $a = 'a',
        public readonly array $b = [],
    ) {
        // empty
    }
}

/**
 * @internal
 * @noinspection PhpMultipleClassesDeclarationsInOneFile
 */
class TaskTest__ParametersEmpty implements Parameters, Serializable {
    public function __construct(
        public readonly string $target,
    ) {
        // empty
    }
}
