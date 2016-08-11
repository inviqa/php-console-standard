<?php

namespace {{project_namespace}}\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class CommandPass implements CompilerPassInterface
{
    /**
     * Add console commands to the application.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (false === $container->hasDefinition('app')) {
            return;
        }

        $appDefinition = $container->getDefinition('app');

        foreach ($container->findTaggedServiceIds('console_command') as $id => $commands) {
            $appDefinition->addMethodCall('add', [new Reference($id)]);

            $this->addConfigurators($container, $commands, $id);
        }
    }

    /**
     * @param ContainerBuilder $container
     * @param $commands
     * @param $id
     */
    private function addConfigurators(ContainerBuilder $container, $commands, $id)
    {
        foreach ($commands as $command) {
            if (isset($command['configurator'])) {
                $container->getDefinition($id)->addMethodCall(
                    'addCommandConfigurator',
                    [new Reference($command['configurator'])]
                );
            }
        }
    }
}
