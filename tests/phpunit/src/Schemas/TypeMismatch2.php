<?php
/*
 * This file is part of DgfipSI1\ConfigHelper
 */

namespace DgfipSI1\ConfigHelperTests\Schemas;

use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Test configuration schema
 */
class TypeMismatch2 implements ConfigurationInterface
{
    /**
     * The main configuration tree - should throw a type mismatch when merged into schema1
     *
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('foo/bar');
        $treeBuilder->getRootNode()->children()
                ->scalarNode('test')->end()
            ->end();

        return $treeBuilder;
    }
}
