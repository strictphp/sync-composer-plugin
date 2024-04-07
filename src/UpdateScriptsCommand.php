<?php

declare(strict_types=1);

namespace StrictPhp\SyncScriptsPlugin;

use Composer\Command\BaseCommand;
use StrictPhp\SyncScriptsPlugin\Exceptions\FailedException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateScriptsCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('update-scripts')
            ->setDescription('Updates the composer.json scripts from composer-scripts.json.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $plugins = $this->requireComposer()
            ->getPluginManager()
            ->getPlugins();

        $plugin = null;
        foreach ($plugins as $plugin) {
            if ($plugin instanceof ScriptsUpdaterPlugin) {
                break;
            }
        }

        if (($plugin instanceof ScriptsUpdaterPlugin) === false) {
            $output->writeln('<error>Scripts Updater Plugin is not installed.</error>');
            return 1;
        }

        try {
            if ($plugin->updateScripts() === false) {
                $output->writeln('<info>Scripts are already up-to-date.</info>');
                return 0;
            }

            $output->writeln('<info>Scripts updated successfully.</info>');
        } catch (FailedException $failedException) {
            $output->writeln(sprintf('<error>%s</error>', $failedException->getMessage()));
            return 1;

        }

        return 0;

    }
}
