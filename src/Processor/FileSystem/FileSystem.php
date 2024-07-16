<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Processor\FileSystem;

use Closure;
use Iterator;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

use function dirname;
use function is_dir;
use function is_file;

class FileSystem {
    public function __construct() {
        // empty
    }

    public function getFile(Directory $root, SplFileInfo|File|string $path): ?File {
        // Object?
        if ($path instanceof SplFileInfo) {
            $path = $path->getPathname();
        } elseif ($path instanceof File) {
            $path = $path->getPath();
        } else {
            // empty
        }

        // File?
        $path = $root->getPath($path);

        if (!is_file($path)) {
            return null;
        }

        // Create
        $writable = $root->isWritable() && $root->isInside($path);
        $file     = new File($path, $writable);

        return $file;
    }

    public function getDirectory(Directory $root, SplFileInfo|Directory|File|string $path): ?Directory {
        // Object?
        if ($path instanceof SplFileInfo) {
            $path = $path->getPathname();
        } elseif ($path instanceof File) {
            $path = dirname($path->getPath());
        } elseif ($path instanceof Directory) {
            $path = $path->getPath();
        } else {
            // empty
        }

        // Self?
        if ($path === '.' || $path === '') {
            return $root;
        }

        // Directory?
        $path = $root->getPath($path);

        if (!is_dir($path)) {
            return null;
        }

        // Create
        $writable = $root->isWritable() && $root->isInside($path);
        $dir      = $root->getPath() !== $path
            ? new Directory($path, $writable)
            : $root;

        return $dir;
    }

    /**
     * @param array<array-key, string>|string|null         $patterns {@see Finder::name()}
     * @param array<array-key, string|int>|string|int|null $depth    {@see Finder::depth()}
     * @param array<array-key, string>|string|null         $exclude  {@see Finder::notPath()}
     *
     * @return Iterator<array-key, File>
     */
    public function getFilesIterator(
        Directory $root,
        array|string|null $patterns = null,
        array|string|int|null $depth = null,
        array|string|null $exclude = null,
    ): Iterator {
        yield from $this->getIterator($root, $this->getFile(...), $patterns, $depth, $exclude);
    }

    /**
     * @param array<array-key, string>|string|null         $patterns {@see Finder::name()}
     * @param array<array-key, string|int>|string|int|null $depth    {@see Finder::depth()}
     * @param array<array-key, string>|string|null         $exclude  {@see Finder::notPath()}
     *
     * @return Iterator<array-key, Directory>
     */
    public function getDirectoriesIterator(
        Directory $root,
        array|string|null $patterns = null,
        array|string|int|null $depth = null,
        array|string|null $exclude = null,
    ): Iterator {
        yield from $this->getIterator($root, $this->getDirectory(...), $patterns, $depth, $exclude);
    }

    /**
     * @template T of object
     *
     * @param Closure(Directory, SplFileInfo): ?T          $factory
     * @param array<array-key, string>|string|null         $patterns {@see Finder::name()}
     * @param array<array-key, string|int>|string|int|null $depth    {@see Finder::depth()}
     * @param array<array-key, string>|string|null         $exclude  {@see Finder::notPath()}
     *
     * @return Iterator<array-key, T>
     */
    protected function getIterator(
        Directory $root,
        Closure $factory,
        array|string|null $patterns = null,
        array|string|int|null $depth = null,
        array|string|null $exclude = null,
    ): Iterator {
        $finder = Finder::create()
            ->ignoreVCSIgnored(true)
            ->exclude('node_modules')
            ->exclude('vendor')
            ->in($root->getPath())
            ->sortByName(true);

        if ($patterns !== null) {
            $finder = $finder->name($patterns);
        }

        if ($depth !== null) {
            $finder = $finder->depth($depth);
        }

        if ($exclude !== null) {
            $finder = $finder->notPath($exclude);
        }

        foreach ($finder as $info) {
            $item = $factory($root, $info);

            if ($item !== null) {
                yield $item;
            }
        }

        yield from [];
    }
}
