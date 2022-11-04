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
class Schema3 implements ConfigurationInterface
{
    public const DUMP =
    '        subbranch2:

            # yeat another boolean.
            boolean:              false

            # A negative number.
            negative_number:      -10
            yet_another_string:   ~
';

    /**
     * The main configuration tree
     *
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('branch/subbranch2');
        $treeBuilder->getRootNode()->children()
                ->booleanNode('boolean')->defaultValue(false)
                    ->info("yeat another boolean.")->end()
                ->integerNode('negative_number')->defaultValue(-10)->max(0)
                    ->info("A negative number.")->end()
                ->scalarNode('yet_another_string')->end()
            ->end();

        return $treeBuilder;
    }
}
