<?php

declare(strict_types=1);

/*
 * This file is part of DgfipSI1\ConfigHelper
 */

namespace DgfipSI1\ConfigHelper;

use DgfipSI1\ConfigHelper\Exception\ConfigSchemaException;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * class ConfigHelper
 *
 */
class MultiSchema implements ConfigurationInterface
{
    /** @var array<ConfigurationInterface> $schemas */
    protected $schemas = [];

    /**
     * Adds a schema node
     *
     * @param ConfigurationInterface $schema
     *
     * @return void
     */
    public function addSchema($schema)
    {
        $tree = $schema->getConfigTreeBuilder();
        if (null === $tree) {
            return;
        }
        $schemaName = $tree->getRootNode()->getNode(true)->getName();
        if ($this->empty() && '' === $schemaName) {
            $err = "Root schema defined in '".$schema::class."' should have a name\n";
            throw new ConfigSchemaException($err);
        }

        $this->schemas[] = $schema;
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
            $schemaName = $schema->getConfigTreeBuilder()->getRootNode()->getNode(true)->getName();
            if (null === $configSchema) {
                $configSchema = $schema->getConfigTreeBuilder();
            } else {
                /** @var ArrayNodeDefinition $childNode */
                $childNode = $schema->getConfigTreeBuilder()->getRootNode();
                $insertAt = $configSchema->getRootNode()->children();
                if ('' === $schemaName) {
                    /** var node */
                    foreach ($childNode->getChildNodeDefinitions() as $definition) {
                        $insertAt->append($definition)->end();
                    }
                } else {
                    $insertAt->append($childNode)->end();
                }
            }
        }

        return $configSchema;
    }
}
