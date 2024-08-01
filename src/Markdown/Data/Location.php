<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Markdown\Data;

use LastDragon_ru\LaraASP\Documentator\Markdown\Location\Location as LocationContract;
use Override;

/**
 * @internal
 * @implements Value<LocationContract>
 */
class Location implements Value {
    public function __construct(
        private readonly LocationContract $location,
    ) {
        // empty
    }

    #[Override]
    public function get(): mixed {
        return $this->location;
    }
}
