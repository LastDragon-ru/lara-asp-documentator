<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Processor\FileSystem;

use Closure;
use Iterator;
use LastDragon_ru\LaraASP\Core\Path\DirectoryPath;
use LastDragon_ru\LaraASP\Core\Path\FilePath;
use LastDragon_ru\LaraASP\Core\Path\Path;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use WeakReference;

use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;

class FileSystem {
    /**
     * @var array<string, WeakReference<Directory|File>>
     */
    private array                 $cache = [];
    public readonly DirectoryPath $input;
    public readonly DirectoryPath $output;

    public function __construct(DirectoryPath $input, ?DirectoryPath $output = null) {
        $this->input  = $input;
        $this->output = $output ?? $this->input;
    }

    /**
     * Relative path will be resolved based on {@see self::$input}.
     */
    public function getFile(SplFileInfo|FilePath|string $path): ?File {
        // Object?
        if ($path instanceof FilePath) {
            $path = (string) $path;
        } elseif ($path instanceof SplFileInfo) {
            $path = $path->getPathname();
        } else {
            // empty
        }

        // Cached?
        $path = $this->input->getFilePath($path);
        $file = $this->cached($path);

        if ($file !== null && !($file instanceof File)) {
            return null;
        }

        if ($file instanceof File) {
            return $file;
        }

        // Create
        if (is_file((string) $path)) {
            $file = $this->cache(new File($path));
        }

        return $file;
    }

    /**
     * Relative path will be resolved based on {@see self::$input}.
     */
    public function getDirectory(SplFileInfo|DirectoryPath|FilePath|string $path): ?Directory {
        // Object?
        if ($path instanceof SplFileInfo) {
            $path = $path->getPathname();
        } elseif ($path instanceof Path) {
            $path = (string) $path->getDirectoryPath();
        } else {
            // empty
        }

        // Cached?
        $path      = $this->input->getDirectoryPath($path);
        $directory = $this->cached($path);

        if ($directory !== null && !($directory instanceof Directory)) {
            return null;
        }

        if ($directory instanceof Directory) {
            return $directory;
        }

        // Create
        if (is_dir((string) $path)) {
            $directory = $this->cache(new Directory($path));
        }

        return $directory;
    }

    /**
     * @param array<array-key, string>|string|null         $patterns {@see Finder::name()}
     * @param array<array-key, string|int>|string|int|null $depth    {@see Finder::depth()}
     * @param array<array-key, string>|string|null         $exclude  {@see Finder::notPath()}
     *
     * @return Iterator<array-key, File>
     */
    public function getFilesIterator(
        Directory $directory,
        array|string|null $patterns = null,
        array|string|int|null $depth = null,
        array|string|null $exclude = null,
    ): Iterator {
        yield from $this->getIterator($directory, $this->getFile(...), $patterns, $depth, $exclude);
    }

    /**
     * @param array<array-key, string>|string|null         $patterns {@see Finder::name()}
     * @param array<array-key, string|int>|string|int|null $depth    {@see Finder::depth()}
     * @param array<array-key, string>|string|null         $exclude  {@see Finder::notPath()}
     *
     * @return Iterator<array-key, Directory>
     */
    public function getDirectoriesIterator(
        Directory $directory,
        array|string|null $patterns = null,
        array|string|int|null $depth = null,
        array|string|null $exclude = null,
    ): Iterator {
        yield from $this->getIterator($directory, $this->getDirectory(...), $patterns, $depth, $exclude);
    }

    /**
     * @template T of object
     *
     * @param Closure(SplFileInfo): ?T                     $factory
     * @param array<array-key, string>|string|null         $patterns {@see Finder::name()}
     * @param array<array-key, string|int>|string|int|null $depth    {@see Finder::depth()}
     * @param array<array-key, string>|string|null         $exclude  {@see Finder::notPath()}
     *
     * @return Iterator<array-key, T>
     */
    protected function getIterator(
        Directory $directory,
        Closure $factory,
        array|string|null $patterns = null,
        array|string|int|null $depth = null,
        array|string|null $exclude = null,
    ): Iterator {
        $finder = Finder::create()
            ->ignoreVCSIgnored(true)
            ->exclude('node_modules')
            ->exclude('vendor')
            ->in((string) $directory)
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
            $item = $factory($info);

            if ($item !== null) {
                yield $item;
            }
        }

        yield from [];
    }

    public function save(File $file): bool {
        // Modified?
        if (!$file->isModified()) {
            return true;
        }

        // Inside?
        if ($this->output->isInside($file->getPath()) !== true) {
            return false;
        }

        // Directory?
        $directory = (string) $file->getDirectoryPath();

        if (!is_dir($directory) && !mkdir($directory, recursive: true)) {
            return false;
        }

        // Save
        return file_put_contents((string) $file->getPath(), $file->getContent()) !== false;
    }

    /**
     * @template T of Directory|File
     *
     * @param T $object
     *
     * @return T
     */
    private function cache(Directory|File $object): Directory|File {
        $this->cache[(string) $object] = WeakReference::create($object);

        return $object;
    }

    private function cached(Path $path): Directory|File|null {
        $key    = (string) $path;
        $cached = null;

        if (isset($this->cache[$key])) {
            $cached = $this->cache[$key]->get();

            if ($cached === null) {
                unset($this->cache[$key]);
            }
        }

        return $cached;
    }
}
