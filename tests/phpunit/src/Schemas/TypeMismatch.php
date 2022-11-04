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
class TypeMismatch implements ConfigurationInterface
{
    public const DUMP =
    '
            # yeat again another boolean.
            new_in_subbranch:     false
';

    /**
     * The main configuration tree
     *
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('branch');
        $treeBuilder->getRootNode()->children()
                ->booleanNode('subbranch')->end()
            ->end();

        return $treeBuilder;
    }
}
