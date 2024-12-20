<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Processor\Tasks\Preprocess\Instructions\IncludeGraphqlDirective;

use ArrayAccess;
use GraphQL\Language\Parser;
use LastDragon_ru\LaraASP\Core\Path\DirectoryPath;
use LastDragon_ru\LaraASP\Core\Path\FilePath;
use LastDragon_ru\LaraASP\Documentator\Markdown\Document;
use LastDragon_ru\LaraASP\Documentator\Markdown\Extensions\Reference\Node;
use LastDragon_ru\LaraASP\Documentator\Markdown\Mutations\Nop;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\File;
use LastDragon_ru\LaraASP\Documentator\Processor\Tasks\Preprocess\Context;
use LastDragon_ru\LaraASP\Documentator\Processor\Tasks\Preprocess\Exceptions\DependencyIsMissing;
use LastDragon_ru\LaraASP\Documentator\Processor\Tasks\Preprocess\Instructions\IncludeGraphqlDirective\Exceptions\TargetIsNotDirective;
use LastDragon_ru\LaraASP\Documentator\Testing\Package\ProcessorHelper;
use LastDragon_ru\LaraASP\Documentator\Testing\Package\TestCase;
use LastDragon_ru\LaraASP\GraphQLPrinter\Contracts\DirectiveResolver;
use LastDragon_ru\LaraASP\GraphQLPrinter\Contracts\Printer as PrinterContract;
use LastDragon_ru\LaraASP\GraphQLPrinter\Printer;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 */
#[CoversClass(Instruction::class)]
final class InstructionTest extends TestCase {
    // <editor-fold desc="Tests">
    // =========================================================================
    public function testInvoke(): void {
        $directive = <<<'GRAPHQL'
            directive @test
            on
                | SCALAR
            GRAPHQL;

        $this->override(PrinterContract::class, static function () use ($directive): PrinterContract {
            $resolver = Mockery::mock(DirectiveResolver::class);
            $resolver
                ->shouldReceive('getDefinition')
                ->with('test')
                ->atLeast()
                ->once()
                ->andReturn(
                    Parser::directiveDefinition($directive),
                );

            return (new Printer())->setDirectiveResolver($resolver);
        });

        $file     = Mockery::mock(File::class);
        $input    = Mockery::mock(DirectoryPath::class);
        $params   = new Parameters('@test');
        $context  = new Context($file, Mockery::mock(Document::class), new Node(), new Nop());
        $instance = $this->app()->make(Instruction::class);
        $actual   = ProcessorHelper::runInstruction($instance, $input, $context, $params);

        self::assertEquals(
            <<<MARKDOWN
            ```graphql
            {$directive}
            ```
            MARKDOWN,
            $actual,
        );
    }

    public function testInvokeNoPrinter(): void {
        // Reset
        $app = $this->app();

        if ($app instanceof ArrayAccess) {
            unset($app[PrinterContract::class]);
        }

        // Test
        $file     = new File((new FilePath(__FILE__))->getNormalizedPath());
        $input    = (new DirectoryPath(__DIR__))->getNormalizedPath();
        $params   = new Parameters('@test');
        $context  = new Context($file, Mockery::mock(Document::class), new Node(), new Nop());
        $instance = $this->app()->make(Instruction::class);

        self::expectExceptionObject(
            new DependencyIsMissing($context, $params, PrinterContract::class),
        );

        ProcessorHelper::runInstruction($instance, $input, $context, $params);
    }

    public function testInvokeNoDirective(): void {
        $this->override(PrinterContract::class, static function (): PrinterContract {
            $resolver = Mockery::mock(DirectiveResolver::class);
            $resolver
                ->shouldReceive('getDefinition')
                ->with('test')
                ->once()
                ->andReturn(
                    null,
                );

            return (new Printer())->setDirectiveResolver($resolver);
        });

        $file     = new File((new FilePath(__FILE__))->getNormalizedPath());
        $input    = (new DirectoryPath(__DIR__))->getNormalizedPath();
        $params   = new Parameters('@test');
        $context  = new Context($file, Mockery::mock(Document::class), new Node(), new Nop());
        $instance = $this->app()->make(Instruction::class);

        self::expectExceptionObject(
            new TargetIsNotDirective($context, $params),
        );

        ProcessorHelper::runInstruction($instance, $input, $context, $params);
    }

    public function testInvokeNoDirectiveResolver(): void {
        $this->override(PrinterContract::class, static function (): PrinterContract {
            return (new Printer())->setDirectiveResolver(null);
        });

        $file     = new File((new FilePath(__FILE__))->getNormalizedPath());
        $input    = (new DirectoryPath(__DIR__))->getNormalizedPath();
        $params   = new Parameters('@test');
        $context  = new Context($file, Mockery::mock(Document::class), new Node(), new Nop());
        $instance = $this->app()->make(Instruction::class);

        self::expectExceptionObject(
            new TargetIsNotDirective($context, $params),
        );

        ProcessorHelper::runInstruction($instance, $input, $context, $params);
    }
}
