<?php

declare(strict_types=1);

namespace StrictPhp\SyncScriptsPlugin;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;

class CommandProvider implements CommandProviderCapability
{
    public function getCommands()
    {
        return [new UpdateScriptsCommand()];
    }
}
