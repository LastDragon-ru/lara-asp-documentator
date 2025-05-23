<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Processor\Tasks\Preprocess\Instructions\IncludeFile;

use LastDragon_ru\LaraASP\Documentator\Markdown\Contracts\Document;
use LastDragon_ru\LaraASP\Documentator\Processor\Dependencies\FileReference;
use LastDragon_ru\LaraASP\Documentator\Processor\Metadata\FileSystem\Content;
use LastDragon_ru\LaraASP\Documentator\Processor\Tasks\Preprocess\Context;
use LastDragon_ru\LaraASP\Documentator\Processor\Tasks\Preprocess\Contracts\Instruction as InstructionContract;
use LastDragon_ru\LaraASP\Documentator\Processor\Tasks\Preprocess\Contracts\Parameters as InstructionParameters;
use Override;

/**
 * Includes the `<target>` file.
 *
 * @implements InstructionContract<Parameters>
 */
class Instruction implements InstructionContract {
    public function __construct() {
        // empty
    }

    #[Override]
    public static function getName(): string {
        return 'include:file';
    }

    #[Override]
    public static function getPriority(): ?int {
        return null;
    }

    #[Override]
    public static function getParameters(): string {
        return Parameters::class;
    }

    #[Override]
    public function __invoke(Context $context, InstructionParameters $parameters): Document|string {
        $target  = $context->file->getFilePath($parameters->target);
        $target  = $context->resolver->resolve(new FileReference($target));
        $content = $target->getExtension() !== 'md'
            ? $target->as(Content::class)->content
            : $target->as(Document::class);

        return $content;
    }
}
