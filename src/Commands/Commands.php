<?php declare(strict_types = 1);

namespace LastDragon_ru\LaraASP\Documentator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use LastDragon_ru\LaraASP\Core\Path\DirectoryPath;
use LastDragon_ru\LaraASP\Core\Utils\Cast;
use LastDragon_ru\LaraASP\Documentator\Markdown\Contracts\Markdown;
use LastDragon_ru\LaraASP\Documentator\Markdown\Mutations\Document\Move;
use LastDragon_ru\LaraASP\Documentator\Package;
use LastDragon_ru\LaraASP\Documentator\PackageViewer;
use LastDragon_ru\LaraASP\Documentator\Utils\ArtisanSerializer;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

use function getcwd;
use function is_dir;

#[AsCommand(
    name       : Commands::Name,
    description: 'Saves help for each command in the `namespace` into a separate file in the `target` directory.',
)]
class Commands extends Command {
    public const string Name = Package::Name.':commands';

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     * @var string
     */
    public $signature = self::Name.<<<'SIGNATURE'
        {namespace  : The namespace of the commands.}
        {target     : Directory to save generated files. It will be created if not exist. All files/directories inside it will be removed otherwise.}
        {--defaults : Include application default arguments/options like `--help`, etc.}
    SIGNATURE;

    public function __invoke(
        PackageViewer $viewer,
        Filesystem $filesystem,
        Markdown $markdown,
        ArtisanSerializer $serializer,
    ): void {
        // Options
        $application = Cast::to(Application::class, $this->getApplication());
        $namespace   = $application->findNamespace(Cast::toString($this->argument('namespace')));
        $cwd         = new DirectoryPath((string) getcwd());
        $target      = $cwd->getDirectoryPath(Cast::toString($this->argument('target')));
        $defaults    = Cast::toBool($this->option('defaults'));
        $commands    = $application->all($namespace);

        // Cleanup
        $this->components->task(
            'Prepare',
            static function () use ($filesystem, $target): void {
                if (is_dir((string) $target)) {
                    $filesystem->remove(
                        Finder::create()->in((string) $target),
                    );
                } else {
                    $filesystem->mkdir((string) $target);
                }
            },
        );

        // Process
        foreach ($commands as $command) {
            if ($command->isHidden()) {
                continue;
            }

            $this->components->task(
                "Command: {$command->getName()}",
                static function () use (
                    $markdown,
                    $filesystem,
                    $serializer,
                    $viewer,
                    $namespace,
                    $cwd,
                    $target,
                    $defaults,
                    $command,
                ): void {
                    // Default options?
                    if ($defaults) {
                        $command->mergeApplicationDefinition(); // @phpstan-ignore method.internal (this is the way)
                    } else {
                        $command->setDefinition(
                            $command->getNativeDefinition(),
                        );
                    }

                    // Render
                    $name    = Str::after((string) $command->getName(), "{$namespace}:");
                    $path    = $target->getFilePath("{$name}.md");
                    $source  = $cwd->getFilePath("{$name}.md");
                    $content = $viewer->render('commands.default', [
                        'serializer' => $serializer,
                        'command'    => $command,
                    ]);
                    $content = (string) $markdown->parse($content, $source)->mutate(
                        new Move($path),
                    );

                    $filesystem->dumpFile((string) $path, $content);
                },
            );
        }
    }
}
