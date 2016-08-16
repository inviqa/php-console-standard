<?php

namespace {{project_namespace}}\DependencyInjection;

use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class ContainerSetUp
{
    public static function bootstrapContainer(array $additionalConfig = [])
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new CommandPass('{{project_name}}.console'));
        $fileLocator = new FileLocator(__DIR__.'/../../config');

        $loader = new DelegatingLoader(new LoaderResolver(
            [new YamlFileLoader($container, $fileLocator), new XmlFileLoader($container, $fileLocator)]
        ));

        foreach (array_merge(['parameters.yml', 'services.xml'], $additionalConfig) as $config) {
            $loader->load($config);
        }

        $container->compile();

        return $container;
    }
}
