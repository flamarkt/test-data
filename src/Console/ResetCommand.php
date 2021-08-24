<?php

namespace Flamarkt\TestData\Console;

use Flarum\Console\AbstractCommand;
use Flarum\Extension\Extension;
use Flarum\Extension\ExtensionManager;
use Illuminate\Support\Arr;
use Symfony\Component\Console\Input\InputOption;

/**
 * Adapted from Flarum's migrate:reset command but will recursively rollback child extensions before the requested extension
 */
class ResetCommand extends AbstractCommand
{
    protected $manager;

    public function __construct(ExtensionManager $manager)
    {
        $this->manager = $manager;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('flamarkt:migrate:reset')
            ->setDescription('Run all migrations down for an extension and its child extensions')
            ->addOption(
                'extension',
                null,
                InputOption::VALUE_REQUIRED,
                'The extension to reset migrations for.'
            );
    }

    protected function fire()
    {
        $extensionName = $this->input->getOption('extension');

        if (!$extensionName) {
            $this->info('No extension specified. Please check command syntax.');

            return;
        }

        $extension = $this->manager->getExtension($extensionName);

        if (!$extension) {
            $this->info('Could not find extension ' . $extensionName);

            return;
        }

        $dependents = [
            // We need to have the original extension in here otherwise the dependency check later won't work
            $extension,
        ];

        // This only works at one level. Finds all extensions that depend on the one we want to revert
        // This is designed to be used with flamarkt-core as the --extension parameter, but could be useful for others
        foreach ($this->manager->getExtensions() as $checkExtension) {
            /**
             * @var Extension $checkExtension
             */
            if (in_array($extension->getId(), $checkExtension->getExtensionDependencyIds())) {
                $dependents[] = $checkExtension;
            }
        }

        // Then because some of the Flamarkt extensions might depend on each others, use Flarum's logic to resolve the order
        $extensionOrder = ExtensionManager::resolveExtensionOrder($dependents);

        $missingDependencies = Arr::get($extensionOrder, 'missingDependencies');

        if (count($missingDependencies)) {
            $this->error('Cannot compute extension order because of missing dependencies for ' . implode(', ', array_map(function ($deps, $id) {
                    return $id . ' (' . implode(', ', $deps) . ')';
                }, $missingDependencies, array_keys($missingDependencies))));

            return;
        }

        $dependentsInLoadingOrder = Arr::get($extensionOrder, 'valid');

        $this->manager->getMigrator()->setOutput($this->output);

        foreach (array_reverse($dependentsInLoadingOrder) as $dependentExtension) {
            $this->info('Rolling back extension: ' . $dependentExtension->getId());

            $this->manager->migrateDown($dependentExtension);
        }

        $this->info('DONE.');
    }
}
