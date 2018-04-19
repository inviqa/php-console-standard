<?php

namespace App\Command;

use App\CommandConfigurator\CommandConfigurator;

abstract class Command extends \Symfony\Component\Console\Command\Command
{
    public function addCommandConfigurator(CommandConfigurator $commandConfigurator)
    {
        $commandConfigurator->configure($this);
    }
}
