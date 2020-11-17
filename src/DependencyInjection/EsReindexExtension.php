<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\DependencyInjection;

use Exception;
use InvalidArgumentException;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class EsReindexExtension extends Extension
{
    /**
     * @param array<mixed> $configs
     *
     * @throws InvalidArgumentException
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(dirname(__DIR__).'/Resources/config')
        );

        try {
            $loader->load('services.yaml');
        } catch (Exception $exception) {
            throw new InvalidArgumentException($exception->getMessage(), $exception->getCode(), $exception);
        }

        $configuration = $this->getConfiguration($configs, $container);

        if (!$configuration instanceof ConfigurationInterface) {
            return;
        }

        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('es_reindex.connection.host', $config['connection']['host']);
        $container->setParameter('es_reindex.connection.port', $config['connection']['port']);
        $container->setParameter('es_reindex.connection.user', $config['connection']['user']);
        $container->setParameter('es_reindex.connection.pass', $config['connection']['pass']);
        $container->setParameter('es_reindex.indices', $config['indices']);
    }
}
