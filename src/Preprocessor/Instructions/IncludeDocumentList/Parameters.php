<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Preprocessor\Instructions\IncludeDocumentList;

use LastDragon_ru\LaraASP\Serializer\Contracts\Serializable;
use Symfony\Component\Finder\Finder;

class Parameters implements Serializable {
    public function __construct(
        /**
         * [Directory Depth](https://symfony.com/doc/current/components/finder.html#directory-depth)
         * (eg the `0` means no nested directories, the `null` removes limits).
         *
         * @see Finder::depth()
         * @var array<array-key, string|int>|string|int|null
         */
        public readonly array|string|int|null $depth = 0,
        /**
         * Blade template.
         */
        public readonly string $template = 'default',
    ) {
        // empty
    }
}
