<?php

namespace App\DependencyInjection;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\TypedReference;

class CommandPass implements CompilerPassInterface
{
    private $applicationServiceId;
    private $commandLoaderServiceId;
    private $commandTag;

    public function __construct($applicationServiceId = 'console', $commandLoaderServiceId = 'console.command_loader', $commandTag = 'console.command')
    {
        $this->applicationServiceId = $applicationServiceId;
        $this->commandLoaderServiceId = $commandLoaderServiceId;
        $this->commandTag = $commandTag;
    }

    public function process(ContainerBuilder $container)
    {
        $commandServices = $container->findTaggedServiceIds($this->commandTag, true);
        $lazyCommandMap = array();
        $lazyCommandRefs = array();
        $serviceIds = array();
        $lazyServiceIds = array();
        $greedyServiceRefs = array();

        foreach ($commandServices as $id => $tags) {
            $definition = $container->getDefinition($id);
            $class = $container->getParameterBag()->resolveValue($definition->getClass());

            $commandId = 'console.command.'.strtolower(str_replace('\\', '_', $class));

            if (isset($tags[0]['command'])) {
                $commandName = $tags[0]['command'];
            } else {
                if (!$r = $container->getReflectionClass($class)) {
                    throw new InvalidArgumentException(sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
                }
                if (!$r->isSubclassOf(Command::class)) {
                    throw new InvalidArgumentException(sprintf('The service "%s" tagged "%s" must be a subclass of "%s".', $id, $this->commandTag, Command::class));
                }
                $commandName = $class::getDefaultName();
            }

            if (null === $commandName) {
                if (isset($serviceIds[$commandId]) || $container->hasAlias($commandId)) {
                    $commandId = $commandId.'_'.$id;
                }
                if (!$definition->isPublic() || $definition->isPrivate()) {
                    $container->setAlias($commandId, $id)->setPublic(true);
                    $id = $commandId;
                }
                $serviceIds[$commandId] = $id;
                $greedyServiceRefs[$commandId] = new TypedReference($id, $class);

                continue;
            }

            $serviceIds[$commandId] = $id;
            $lazyServiceIds[$id] = true;
            unset($tags[0]);
            $lazyCommandMap[$commandName] = $id;
            $lazyCommandRefs[$id] = new TypedReference($id, $class);
            $aliases = array();

            foreach ($tags as $tag) {
                if (isset($tag['command'])) {
                    $aliases[] = $tag['command'];
                    $lazyCommandMap[$tag['command']] = $id;
                }
            }

            $definition->addMethodCall('setName', array($commandName));

            if ($aliases) {
                $definition->addMethodCall('setAliases', array($aliases));
            }
        }

        $container
            ->register($this->commandLoaderServiceId, ContainerCommandLoader::class)
            ->setPublic(true)
            ->setArguments(array(ServiceLocatorTagPass::register($container, $lazyCommandRefs), $lazyCommandMap));

        if (count($greedyServiceRefs) > 0) {
            $applicationDefinition = $container->getDefinition($this->applicationServiceId);
            $applicationDefinition->addMethodCall('addCommands', [$greedyServiceRefs]);
        }
    }
}
