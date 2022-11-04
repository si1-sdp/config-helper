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
class Schema1 implements ConfigurationInterface
{
    public const DUMP =
    'schema:

    # True or false.
    true_or_false:        false

    # A number between 0 and 100.
    positive_number:      100

    # A string
    this_is_a_string:     ~
    another_string:       ~
';
    /**
     * The main configuration tree
     *
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('');
        $treeBuilder->getRootNode()->children()
                ->booleanNode('true_or_false')->defaultValue(false)
                    ->info("True or false.")->end()
                ->integerNode('positive_number')->defaultValue(100)->min(0)
                    ->info("A number between 0 and 100.")->end()
                ->scalarNode('this_is_a_string')->info("A string")->end()
                ->scalarNode('another_string')->end()
            ->end();

        return $treeBuilder;
    }
}
