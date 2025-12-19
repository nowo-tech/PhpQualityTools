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
 * Files are only created on install (composer install), NOT on update (composer update).
 * Existing files are NEVER overwritten.
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
        $this->installComposerScripts($event->getIO());
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
        // Always try to add missing scripts on update
        $this->installComposerScripts($event->getIO());
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
     * Get the major version of Rector if installed.
     *
     * @return int The major version number (1 or 2), or 1 as default if not installed
     */
    private function getRectorVersion(): int
    {
        $localRepository = $this->composer->getRepositoryManager()->getLocalRepository();
        $rectorPackage = $localRepository->findPackage('rector/rector', '*');
        
        if ($rectorPackage === null) {
            // Default to version 1 if Rector is not installed
            return 1;
        }
        
        $version = $rectorPackage->getVersion();
        
        // Extract major version from version string (e.g., "2.2.14" -> 2)
        if (preg_match('/^(\d+)\./', $version, $matches)) {
            return (int) $matches[1];
        }
        
        // Default to version 1 if version cannot be determined
        return 1;
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

        // Build command with correct versions for optional Rector packages
        // Detect Rector version to use compatible package versions
        $rectorVersion = $this->getRectorVersion();
        $packagesForMessage = [];
        foreach (array_keys($missingPackages) as $package) {
            if ($package === 'rector/rector-doctrine') {
                $packagesForMessage[] = $rectorVersion >= 2 ? 'rector/rector-doctrine:^2.0' : 'rector/rector-doctrine:^0.16';
            } elseif ($package === 'rector/rector-symfony') {
                $packagesForMessage[] = $rectorVersion >= 2 ? 'rector/rector-symfony:^2.0' : 'rector/rector-symfony:^1.0';
            } elseif ($package === 'rector/rector-phpunit') {
                $packagesForMessage[] = $rectorVersion >= 2 ? 'rector/rector-phpunit:^2.0' : 'rector/rector-phpunit:^1.0';
            } else {
                $packagesForMessage[] = $package;
            }
        }
        
        if (!$io->isInteractive()) {
            $io->write('<comment>php-quality-tools: Run in interactive mode to install dependencies automatically</comment>');
            $io->write('<comment>php-quality-tools: Or install manually: composer require --dev --with-all-dependencies ' . implode(' ', $packagesForMessage) . '</comment>');
            return;
        }

        $io->write('');
        if ($io->askConfirmation('<question>Would you like to install these dependencies now? (yes/no) [yes]: </question>', true)) {
            $this->installDependencies($io, array_keys($missingPackages));
        } else {
            $io->write('<comment>php-quality-tools: Skipped. Install manually with:</comment>');
            $io->write('<comment>  composer require --dev --with-all-dependencies ' . implode(' ', $packagesForMessage) . '</comment>');
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

        // Build command with correct versions for optional Rector packages
        // Detect Rector version to use compatible package versions
        $rectorVersion = $this->getRectorVersion();
        $packagesWithVersions = [];
        foreach ($packages as $package) {
            if ($package === 'rector/rector-doctrine') {
                // rector/rector-doctrine 0.16 is compatible with Rector 1.x, 2.x needs ^2.0
                $packagesWithVersions[] = $rectorVersion >= 2 ? 'rector/rector-doctrine:^2.0' : 'rector/rector-doctrine:^0.16';
            } elseif ($package === 'rector/rector-symfony') {
                // rector/rector-symfony 1.x is compatible with Rector 1.x, 2.x needs ^2.0
                $packagesWithVersions[] = $rectorVersion >= 2 ? 'rector/rector-symfony:^2.0' : 'rector/rector-symfony:^1.0';
            } elseif ($package === 'rector/rector-phpunit') {
                // rector/rector-phpunit 1.x is compatible with Rector 1.x, 2.x needs ^2.0
                $packagesWithVersions[] = $rectorVersion >= 2 ? 'rector/rector-phpunit:^2.0' : 'rector/rector-phpunit:^1.0';
            } else {
                $packagesWithVersions[] = $package;
            }
        }
        
        $command = sprintf(
            '%s require --dev --no-interaction --with-all-dependencies %s',
            escapeshellarg($composerBin),
            implode(' ', array_map('escapeshellarg', $packagesWithVersions))
        );

        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode === 0) {
            $io->write('<info>php-quality-tools: Dependencies installed successfully!</info>');
        } else {
            $io->writeError('<error>php-quality-tools: Failed to install dependencies</error>');
            $io->writeError('<error>php-quality-tools: Output: ' . implode("\n", $output) . '</error>');
            
            // Build command with correct versions for optional Rector packages
            // Detect Rector version to use compatible package versions
            $rectorVersion = $this->getRectorVersion();
            $packagesWithVersions = [];
            foreach ($packages as $package) {
                if ($package === 'rector/rector-doctrine') {
                    $packagesWithVersions[] = $rectorVersion >= 2 ? 'rector/rector-doctrine:^2.0' : 'rector/rector-doctrine:^0.16';
                } elseif ($package === 'rector/rector-symfony') {
                    $packagesWithVersions[] = $rectorVersion >= 2 ? 'rector/rector-symfony:^2.0' : 'rector/rector-symfony:^1.0';
                } elseif ($package === 'rector/rector-phpunit') {
                    $packagesWithVersions[] = $rectorVersion >= 2 ? 'rector/rector-phpunit:^2.0' : 'rector/rector-phpunit:^1.0';
                } else {
                    $packagesWithVersions[] = $package;
                }
            }
            
            $io->writeError('<error>php-quality-tools: Please install manually: composer require --dev --with-all-dependencies ' . implode(' ', $packagesWithVersions) . '</error>');
        }
    }

    /**
     * Install configuration files to the project root.
     *
     * Behavior:
     * - On install (isUpdate=false): Creates all configuration files that don't exist
     * - On update (isUpdate=true): Does NOT create new files, only reports existing ones
     * - Existing files are NEVER overwritten in either case
     *
     * @param IOInterface $io       The IO interface
     * @param bool        $isUpdate Whether this is an update operation (true) or install (false)
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
        // Template formatters are only installed if their dependencies are present
        $files = $this->getFilesToInstall($framework, $io);

        $installedCount = 0;
        $skippedCount = 0;
        $notCreatedCount = 0;

        foreach ($files as $source => $dest) {
            $sourcePath = $packageDir . '/' . $source;
            $destPath = $projectDir . '/' . $dest;

            if (!file_exists($sourcePath)) {
                continue;
            }

            // If file already exists, skip it (never overwrite)
            if (file_exists($destPath)) {
                $skippedCount++;
                continue;
            }

            // On update, don't create new files - only create on install
            if ($isUpdate) {
                $notCreatedCount++;
                continue;
            }

            // On install, create the file
            $io->write(sprintf('<info>php-quality-tools: Installing %s</info>', $dest));
            copy($sourcePath, $destPath);
            $installedCount++;
        }

        if ($installedCount > 0) {
            $io->write(sprintf('<info>php-quality-tools: Installed %d file(s) for %s</info>', $installedCount, $framework));
        }

        if ($isUpdate) {
            if ($skippedCount > 0) {
                $io->write(sprintf('<comment>php-quality-tools: %d file(s) already exist (not overwritten)</comment>', $skippedCount));
            }
            if ($notCreatedCount > 0) {
                $io->write(sprintf('<comment>php-quality-tools: %d new file(s) available but not created (run composer install to create them)</comment>', $notCreatedCount));
            }
        }
    }

    /**
     * Get the list of files to install based on framework.
     * Only installs template formatter configs if their dependencies are installed.
     *
     * @param string      $framework The framework name
     * @param IOInterface $io        The IO interface for logging (optional)
     *
     * @return array<string, string> Array mapping source paths to destination paths
     */
    private function getFilesToInstall(string $framework, ?IOInterface $io = null): array
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
        $csfixerSource = "config/{$framework}/.php-cs-fixer.php";
        $csfixerCustomSource = "config/{$framework}/.php-cs-fixer.custom.php";

        if (!file_exists($packageDir . '/' . $csfixerSource)) {
            $csfixerSource = 'config/generic/.php-cs-fixer.php';
            $csfixerCustomSource = 'config/generic/.php-cs-fixer.custom.php';
        }

        $files[$csfixerSource] = '.php-cs-fixer.php';
        $files[$csfixerCustomSource] = '.php-cs-fixer.custom.php';

        // Template formatters (framework-specific)
        // 
        // Template engines by framework:
        // - Symfony: Twig (default)
        // - Laravel: Blade (default), Twig (optional via twigbridge)
        // - Yii: PHP native views, Twig (optional)
        // - CakePHP: PHP native .ctp files, Twig (optional)
        // - Laminas: PHP native, Twig (optional), Smarty (optional)
        // - CodeIgniter: PHP native views
        // - Slim: PHP native, Twig (optional)
        // - Generic: Varies (Twig, PHP native, etc.)
        
        // Twig-CS-Fixer: Available for all frameworks if Twig is installed
        // Check for Twig in all frameworks (not just Symfony/Generic)
        if ($this->isPackageInstalled('twig/twig')) {
            // Prefer framework-specific config, fallback to generic
            $twigSource = "config/{$framework}/.twig-cs-fixer.php";
            $twigCustomSource = "config/{$framework}/.twig-cs-fixer.custom.php";

            if (!file_exists($packageDir . '/' . $twigSource)) {
                $twigSource = 'config/generic/.twig-cs-fixer.php';
                $twigCustomSource = 'config/generic/.twig-cs-fixer.custom.php';
            }

            $files[$twigSource] = '.twig-cs-fixer.php';
            $files[$twigCustomSource] = '.twig-cs-fixer.custom.php';
        } elseif ($io !== null && in_array($framework, ['symfony', 'generic'])) {
            // Only show message for frameworks that typically use Twig
            $io->write('<comment>php-quality-tools: Twig not detected, skipping Twig-CS-Fixer configuration</comment>');
        }

        // Blade templates (Laravel): Handled by PHP-CS-Fixer
        // Laravel Blade templates (.blade.php) are PHP files with special syntax.
        // The Laravel PHP-CS-Fixer config includes .blade.php files in the finder.
        // No additional config files needed - support is built into .php-cs-fixer.php
        
        // Future template formatters (not yet implemented):
        // - Smarty-CS-Fixer: For Smarty templates (.tpl) used by Laminas
        // - CakePHP Template Formatter: For .ctp files
        // - Yii View Formatter: For Yii PHP native views
        // - CodeIgniter View Formatter: For CodeIgniter PHP native views
        //
        // When adding new formatters:
        // 1. Check if the template engine package is installed
        // 2. Add config files to appropriate framework directories
        // 3. Update this method to install the config files
        // 4. Update documentation

        return $files;
    }

    /**
     * Install Composer scripts to the project's composer.json.
     * 
     * Adds quality tool scripts if they don't already exist.
     * Never overwrites existing scripts.
     *
     * @param IOInterface $io The IO interface
     */
    private function installComposerScripts(IOInterface $io): void
    {
        $vendorDir = $this->composer->getConfig()->get('vendor-dir');
        $projectDir = dirname($vendorDir);
        $composerJsonPath = $projectDir . '/composer.json';

        if (!file_exists($composerJsonPath)) {
            return;
        }

        $composerJson = json_decode(file_get_contents($composerJsonPath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $io->writeError('<error>php-quality-tools: Failed to parse composer.json</error>');
            return;
        }

        // Initialize scripts array if it doesn't exist
        if (!isset($composerJson['scripts'])) {
            $composerJson['scripts'] = [];
        }

        $framework = $this->detectFramework();
        $scriptsToAdd = $this->getScriptsForFramework($framework);

        $addedCount = 0;
        $existingCount = 0;

        foreach ($scriptsToAdd as $scriptName => $scriptCommand) {
            // Skip if script already exists
            if (isset($composerJson['scripts'][$scriptName])) {
                $existingCount++;
                continue;
            }

            $composerJson['scripts'][$scriptName] = $scriptCommand;
            $addedCount++;
        }

        // Only write if we added new scripts
        if ($addedCount > 0) {
            // Sort scripts alphabetically for better readability
            ksort($composerJson['scripts']);

            // Write back to composer.json with proper formatting
            $jsonContent = json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($jsonContent === false) {
                $io->writeError('<error>php-quality-tools: Failed to encode composer.json</error>');
                return;
            }

            // Add trailing newline
            $jsonContent .= "\n";

            if (file_put_contents($composerJsonPath, $jsonContent) === false) {
                $io->writeError('<error>php-quality-tools: Failed to write composer.json</error>');
                return;
            }

            $io->write(sprintf('<info>php-quality-tools: Added %d script(s) to composer.json</info>', $addedCount));
        }

        if ($existingCount > 0) {
            $io->write(sprintf('<comment>php-quality-tools: %d script(s) already exist in composer.json (not overwritten)</comment>', $existingCount));
        }
    }

    /**
     * Get Composer scripts for the detected framework.
     *
     * @param string $framework The framework name
     *
     * @return array<string, string> Array of script names to commands
     */
    private function getScriptsForFramework(string $framework): array
    {
        $scripts = [
            // PHP-CS-Fixer scripts
            'cs-check' => 'php-cs-fixer fix --dry-run --diff',
            'cs-fix' => 'php-cs-fixer fix',
            
            // Rector scripts
            'rector' => 'rector process --dry-run',
            'rector:fix' => 'rector process',
        ];

        // Add Twig-CS-Fixer scripts if Twig is installed
        if ($this->isPackageInstalled('twig/twig')) {
            $scripts['twig-check'] = 'twig-cs-fixer lint templates/';
            $scripts['twig-fix'] = 'twig-cs-fixer lint --fix templates/';
        }

        // Framework-specific scripts
        if ($framework === 'laravel') {
            // Laravel Blade is handled by PHP-CS-Fixer, but we can add specific scripts
            $scripts['blade-check'] = 'php-cs-fixer fix resources/views --dry-run --diff';
            $scripts['blade-fix'] = 'php-cs-fixer fix resources/views';
        }

        // Test script (if phpunit is available)
        if ($this->isPackageInstalled('phpunit/phpunit')) {
            // Only add if not already present
            if (!isset($scripts['test'])) {
                $scripts['test'] = 'phpunit';
            }
        }

        return $scripts;
    }
}
