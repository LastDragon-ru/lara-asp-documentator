<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Preprocessor\Instructions\IncludeTemplate;

use LastDragon_ru\LaraASP\Documentator\Preprocessor\Contracts\Parameters as ParametersContract;
use LastDragon_ru\LaraASP\Serializer\Contracts\Serializable;

class Parameters implements ParametersContract, Serializable {
    public function __construct(
        /**
         * File path.
         */
        public readonly string $target,
        /**
         * Array of variables (`${name}`) to replace.
         *
         * @var array<string, scalar|null>
         */
        public readonly array $data,
    ) {
        // empty
    }
}
