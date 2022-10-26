<?php

declare(strict_types=1);

/*
 * This file is part of DgfipSI1\ConfigHelper
 */

namespace DgfipSI1\ConfigHelper;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * class ConfigHelper
 *
 * @phpstan-type schemaEntry array{schema: ConfigurationInterface, insert: string}
 */
class MultiSchema implements ConfigurationInterface
{
    /** @var array<schemaEntry> $schemas */
    protected $schemas = [];

    /**
     * Adds a schema node
     *
     * @param ConfigurationInterface $schema
     * @param bool                   $insertChildren inserts children instead of root node
     *
     * @return void
     */
    public function addSchema($schema, $insertChildren = false)
    {
        $this->schemas[] = [ 'schema' => $schema, 'insert' => ($insertChildren ? 'children' : 'root') ];
    }
    /**
     * is schema list empty ?
     *
     * @return bool
     */
    public function empty()
    {
        return empty($this->schemas);
    }
    /**
     * get global configTree
     *
     * @return TreeBuilder|null
     */
    public function getConfigTreeBuilder()
    {
        /** @var TreeBuilder|null $configSchema */
        $configSchema = null;
        foreach ($this->schemas as $schema) {
            /** @var ConfigurationInterface $newSchema */
            $newSchema = $schema['schema'];
            if (null === $configSchema) {
                $configSchema = $newSchema->getConfigTreeBuilder();
            } else {
                /** @var ArrayNodeDefinition $childNode */
                $childNode = $newSchema->getConfigTreeBuilder()->getRootNode();
                $insertAt = $configSchema->getRootNode()->children();
                if ('root' === $schema['insert']) {
                    $insertAt->append($childNode)->end();
                } else {
                    /** var node */
                    foreach ($childNode->getChildNodeDefinitions() as $definition) {
                        $insertAt->append($definition)->end();
                    }
                }
            }
        }

        return $configSchema;
    }
}
