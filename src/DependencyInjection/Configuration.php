<?php

declare(strict_types=1);

namespace kr0lik\ElasticSearchReindex\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function assert;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('es_reindex');

        $rootNode = $treeBuilder->getRootNode();

        assert($rootNode instanceof ArrayNodeDefinition);

        $rootNode
            ->children()
            ->arrayNode('connection')
            ->children()
            ->scalarNode('host')->isRequired()->end()
            ->scalarNode('port')->isRequired()->end()
            ->scalarNode('user')->isRequired()->end()
            ->scalarNode('pass')->isRequired()->end()
            ->end()
            ->end()
            ->arrayNode('indices')->ignoreExtraKeys(false)->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
