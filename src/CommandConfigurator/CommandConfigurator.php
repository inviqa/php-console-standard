<?php

namespace App\CommandConfigurator;

use Symfony\Component\Console\Command\Command;

interface CommandConfigurator
{
    public function configure(Command $command);
}
