<?php
namespace App\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

class AppExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container)
    {
        (new CommandPass('app.console.command_loader', 'app.console.command'))->process($container);
    }

    public function load(array $configs, ContainerBuilder $container)
    {
    }
}