<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Processor\Contracts;

use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\Directory;
use LastDragon_ru\LaraASP\Documentator\Processor\FileSystem\File;
use Traversable;

interface DependencyResolver {
    /**
     * @template V of Traversable<mixed, Directory|File>|Directory|File|null
     * @template D of Dependency<V>
     *
     * @param D $dependency
     *
     * @return V
     */
    public function resolve(Dependency $dependency): Traversable|Directory|File|null;

    /**
     * @template V of Traversable<mixed, File>|File|null
     * @template D of Dependency<V>
     *
     * @param D $dependency
     */
    public function queue(Dependency $dependency): void;
}
