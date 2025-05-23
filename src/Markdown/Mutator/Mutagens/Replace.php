<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Markdown\Mutator\Mutagens;

use LastDragon_ru\LaraASP\Documentator\Editor\Locations\Location;

readonly class Replace {
    public function __construct(
        public Location $location,
        public string $string,
    ) {
        // empty
    }
}
