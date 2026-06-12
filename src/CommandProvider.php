<?php

declare(strict_types=1);

namespace ComposerCheckUpdates;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use ComposerCheckUpdates\Command\CheckUpdatesCommand;

final class CommandProvider implements CommandProviderCapability
{
    public function getCommands(): array
    {
        return [
            new CheckUpdatesCommand(),
        ];
    }
}
