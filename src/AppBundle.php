<?php

namespace App;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use App\DependencyInjection\CommandPass;

class AppBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new CommandPass('app.console', 'app.console.command_loader', 'app.console.command'));
    }
}
