<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

/**
 * Composer plugin that installs PHP quality tool configurations.
 * Automatically detects the framework and installs appropriate configs.
 * Files are only copied on first install and NEVER overwritten.
 * Optionally installs suggested dependencies (Rector, PHP-CS-Fixer, etc.)
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.com>
 * @see    https://github.com/HecFranco
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    /** @var Composer The Composer instance */
    private Composer $composer;

    /** @var IOInterface The IO interface */
    private IOInterface $io;

    /**
     * Framework detection configuration
     * package => framework name
     */
    private const FRAMEWORK_PACKAGES = [
        'symfony/framework-bundle' => 'symfony',
        'symfony/symfony' => 'symfony',
        'laravel/framework' => 'laravel',
        'yiisoft/yii2' => 'yii',
        'cakephp/cakephp' => 'cakephp',
        'laminas/laminas-mvc' => 'laminas',
        'codeigniter4/framework' => 'codeigniter',
        'slim/slim' => 'slim',
    ];

    /**
     * Suggested dependencies by framework
     */
    private const SUGGESTED_PACKAGES = [
        'generic' => [
            'rector/rector' => 'Rector for automated code refactoring',
            'friendsofphp/php-cs-fixer' => 'PHP-CS-Fixer for code style fixing',
            'vincentlanglet/twig-cs-fixer' => 'Twig-CS-Fixer for Twig template style fixing',
        ],
        'symfony' => [
            'rector/rector' => 'Rector for automated code refactoring',
            'rector/rector-symfony' => 'Rector rules for Symfony',
            'rector/rector-doctrine' => 'Rector rules for Doctrine',
            'rector/rector-phpunit' => 'Rector rules for PHPUnit',
            'friendsofphp/php-cs-fixer' => 'PHP-CS-Fixer for code style fixing',
            'vincentlanglet/twig-cs-fixer' => 'Twig-CS-Fixer for Twig template style fixing',
        ],
        'laravel' => [
            'rector/rector' => 'Rector for automated code refactoring',
            'driftingly/rector-laravel' => 'Rector rules for Laravel',
            'friendsofphp/php-cs-fixer' => 'PHP-CS-Fixer for code style fixing',
            'shufo/blade-formatter' => 'Blade Formatter for Blade template formatting (npm package)',
        ],
        'yii' => [
            'rector/rector' => 'Rector for automated code refactoring',
            'friendsofphp/php-cs-fixer' => 'PHP-CS-Fixer for code style fixing',
        ],
        'cakephp' => [
            'rector/rector' => 'Rector for automated code refactoring',
            'friendsofphp/php-cs-fixer' => 'PHP-CS-Fixer for code style fixing',
        ],
        'laminas' => [
            'rector/rector' => 'Rector for automated code refactoring',
            'friendsofphp/php-cs-fixer' => 'PHP-CS-Fixer for code style fixing',
        ],
        'codeigniter' => [
            'rector/rector' => 'Rector for automated code refactoring',
            'friendsofphp/php-cs-fixer' => 'PHP-CS-Fixer for code style fixing',
        ],
        'slim' => [
            'rector/rector' => 'Rector for automated code refactoring',
            'friendsofphp/php-cs-fixer' => 'PHP-CS-Fixer for code style fixing',
        ],
    ];

    /**
     * Activate the plugin.
     *
     * @param Composer    $composer The Composer instance
     * @param IOInterface $io       The IO interface
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * Deactivate the plugin.
     *
     * @param Composer    $composer The Composer instance
     * @param IOInterface $io       The IO interface
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * Uninstall the plugin.
     *
     * @param Composer    $composer The Composer instance
     * @param IOInterface $io       The IO interface
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {
        $io->write('<info>php-quality-tools: Configuration files preserved (may contain customizations)</info>');
    }

    /**
     * Get the subscribed events.
     *
     * @return array<string, string> The subscribed events
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'onPostInstall',
            ScriptEvents::POST_UPDATE_CMD => 'onPostUpdate',
        ];
    }

    /**
     * Handle post-install command event.
     *
     * @param Event $event The script event
     */
    public function onPostInstall(Event $event): void
    {
        $this->installFiles($event->getIO());
        $this->checkAndInstallDependencies($event->getIO());
    }

    /**
     * Handle post-update command event.
     *
     * @param Event $event The script event
     */
    public function onPostUpdate(Event $event): void
    {
        $this->installFiles($event->getIO(), isUpdate: true);
        // Only check dependencies on update if explicitly requested
        if ($this->io->isInteractive()) {
            $this->checkAndInstallDependencies($event->getIO(), isUpdate: true);
        }
    }

    /**
     * Detect the framework being used in the project.
     *
     * @return string The detected framework name (e.g., 'symfony', 'laravel', 'generic')
     */
    private function detectFramework(): string
    {
        $vendorDir = $this->composer->getConfig()->get('vendor-dir');
        $projectDir = dirname($vendorDir);
        $composerJsonPath = $projectDir . '/composer.json';

        if (!file_exists($composerJsonPath)) {
            return 'generic';
        }

        $composerJson = json_decode(file_get_contents($composerJsonPath), true);
        $require = array_merge(
            $composerJson['require'] ?? [],
            $composerJson['require-dev'] ?? []
        );

        foreach (self::FRAMEWORK_PACKAGES as $package => $framework) {
            if (isset($require[$package])) {
                return $framework;
            }
        }

        return 'generic';
    }

    /**
     * Check if a package is installed.
     *
     * @param string $packageName The package name to check
     *
     * @return bool True if the package is installed, false otherwise
     */
    private function isPackageInstalled(string $packageName): bool
    {
        $repositoryManager = $this->composer->getRepositoryManager();
        $localRepository = $this->composer->getRepositoryManager()->getLocalRepository();

        return $localRepository->findPackage($packageName, '*') !== null;
    }

    /**
     * Check and optionally install suggested dependencies.
     *
     * @param IOInterface $io       The IO interface
     * @param bool        $isUpdate Whether this is an update operation
     */
    private function checkAndInstallDependencies(IOInterface $io, bool $isUpdate = false): void
    {
        $framework = $this->detectFramework();
        $suggestedPackages = self::SUGGESTED_PACKAGES[$framework] ?? self::SUGGESTED_PACKAGES['generic'];

        $missingPackages = [];
        foreach ($suggestedPackages as $package => $description) {
            if (!$this->isPackageInstalled($package)) {
                $missingPackages[$package] = $description;
            }
        }

        if (empty($missingPackages)) {
            return;
        }

        $io->write('');
        $io->write('<comment>php-quality-tools: Missing suggested dependencies detected:</comment>');
        foreach ($missingPackages as $package => $description) {
            $io->write(sprintf('  - <info>%s</info>: %s', $package, $description));
        }

        if (!$io->isInteractive()) {
            $io->write('<comment>php-quality-tools: Run in interactive mode to install dependencies automatically</comment>');
            $io->write('<comment>php-quality-tools: Or install manually: composer require --dev ' . implode(' ', array_keys($missingPackages)) . '</comment>');
            return;
        }

        $io->write('');
        if ($io->askConfirmation('<question>Would you like to install these dependencies now? (yes/no) [yes]: </question>', true)) {
            $this->installDependencies($io, array_keys($missingPackages));
        } else {
            $io->write('<comment>php-quality-tools: Skipped. Install manually with:</comment>');
            $io->write('<comment>  composer require --dev ' . implode(' ', array_keys($missingPackages)) . '</comment>');
        }
    }

    /**
     * Install dependencies using Composer.
     *
     * @param IOInterface $io       The IO interface
     * @param array       $packages Array of package names to install
     */
    private function installDependencies(IOInterface $io, array $packages): void
    {
        $vendorDir = $this->composer->getConfig()->get('vendor-dir');
        $projectDir = dirname($vendorDir);
        $composerBin = $this->composer->getConfig()->get('bin-dir') . '/composer';

        // Fallback to system composer if not found in vendor
        if (!file_exists($composerBin)) {
            $composerBin = 'composer';
        }

        $io->write('<info>php-quality-tools: Installing dependencies...</info>');

        $command = sprintf(
            '%s require --dev --no-interaction %s',
            escapeshellarg($composerBin),
            implode(' ', array_map('escapeshellarg', $packages))
        );

        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode === 0) {
            $io->write('<info>php-quality-tools: Dependencies installed successfully!</info>');
        } else {
            $io->writeError('<error>php-quality-tools: Failed to install dependencies</error>');
            $io->writeError('<error>php-quality-tools: Output: ' . implode("\n", $output) . '</error>');
            $io->writeError('<error>php-quality-tools: Please install manually: composer require --dev ' . implode(' ', $packages) . '</error>');
        }
    }

    /**
     * Install configuration files to the project root.
     *
     * @param IOInterface $io       The IO interface
     * @param bool        $isUpdate Whether this is an update operation
     */
    private function installFiles(IOInterface $io, bool $isUpdate = false): void
    {
        $vendorDir = $this->composer->getConfig()->get('vendor-dir');
        $projectDir = dirname($vendorDir);
        $packageDir = __DIR__ . '/..';

        // Detect framework
        $framework = $this->detectFramework();
        $io->write(sprintf('<info>php-quality-tools: Detected framework: %s</info>', $framework));

        // Files to install based on framework
        // Priority: framework-specific > generic
        $files = $this->getFilesToInstall($framework);

        $installedCount = 0;
        $skippedCount = 0;

        foreach ($files as $source => $dest) {
            $sourcePath = $packageDir . '/' . $source;
            $destPath = $projectDir . '/' . $dest;

            if (!file_exists($sourcePath)) {
                continue;
            }

            if (file_exists($destPath)) {
                $skippedCount++;
                continue;
            }

            $io->write(sprintf('<info>php-quality-tools: Installing %s</info>', $dest));
            copy($sourcePath, $destPath);
            $installedCount++;
        }

        if ($installedCount > 0) {
            $io->write(sprintf('<info>php-quality-tools: Installed %d file(s) for %s</info>', $installedCount, $framework));
        }

        if ($isUpdate && $skippedCount > 0) {
            $io->write(sprintf('<comment>php-quality-tools: %d file(s) already exist (not overwritten)</comment>', $skippedCount));
        }
    }

    /**
     * Get the list of files to install based on framework.
     *
     * @param string $framework The framework name
     *
     * @return array<string, string> Array mapping source paths to destination paths
     */
    private function getFilesToInstall(string $framework): array
    {
        $files = [];

        // Rector
        $rectorSource = "config/{$framework}/rector.php";
        $rectorCustomSource = "config/{$framework}/rector.custom.php";

        // Fallback to generic if framework-specific doesn't exist
        $packageDir = __DIR__ . '/..';
        if (!file_exists($packageDir . '/' . $rectorSource)) {
            $rectorSource = 'config/generic/rector.php';
            $rectorCustomSource = 'config/generic/rector.custom.php';
        }

        $files[$rectorSource] = 'rector.php';
        $files[$rectorCustomSource] = 'rector.custom.php';

        // PHP-CS-Fixer
        $csfixerSource = "config/{$framework}/.php-cs-fixer.dist.php";
        $csfixerCustomSource = "config/{$framework}/.php-cs-fixer.custom.php";

        if (!file_exists($packageDir . '/' . $csfixerSource)) {
            $csfixerSource = 'config/generic/.php-cs-fixer.dist.php';
            $csfixerCustomSource = 'config/generic/.php-cs-fixer.custom.php';
        }

        $files[$csfixerSource] = '.php-cs-fixer.dist.php';
        $files[$csfixerCustomSource] = '.php-cs-fixer.custom.php';

        // Twig-CS-Fixer (only for frameworks that use Twig)
        if (in_array($framework, ['symfony', 'generic'])) {
            $twigSource = "config/{$framework}/.twig-cs-fixer.php";
            $twigCustomSource = "config/{$framework}/.twig-cs-fixer.custom.php";

            if (!file_exists($packageDir . '/' . $twigSource)) {
                $twigSource = 'config/generic/.twig-cs-fixer.php';
                $twigCustomSource = 'config/generic/.twig-cs-fixer.custom.php';
            }

            $files[$twigSource] = '.twig-cs-fixer.php';
            $files[$twigCustomSource] = '.twig-cs-fixer.custom.php';
        }

        // Blade Formatter (only for Laravel)
        if ($framework === 'laravel') {
            $bladeSource = "config/{$framework}/.blade-formatter.json";
            $bladeCustomSource = "config/{$framework}/.blade-formatter.custom.json";
            $bladeIgnoreSource = "config/{$framework}/.blade-formatter.ignore";

            if (file_exists($packageDir . '/' . $bladeSource)) {
                $files[$bladeSource] = '.blade-formatter.json';
            }
            if (file_exists($packageDir . '/' . $bladeCustomSource)) {
                $files[$bladeCustomSource] = '.blade-formatter.custom.json';
            }
            if (file_exists($packageDir . '/' . $bladeIgnoreSource)) {
                $files[$bladeIgnoreSource] = '.blade-formatter.ignore';
            }
        }

        return $files;
    }
}
