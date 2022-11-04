<?php

declare(strict_types=1);

/*
 * This file is part of DgfipSI1\ConfigHelper
 */

namespace DgfipSI1\ConfigHelper;

use DgfipSI1\ConfigHelper\Exception\ConfigSchemaException;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * class ConfigHelper
 *
 */
class MultiSchema implements ConfigurationInterface
{
    /** @var bool $debug */
    private $debug = false;
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
        /** @var null|TreeBuilder $tree */
        $tree = $schema->getConfigTreeBuilder();
        if (null === $tree) {
            return;
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
        $e = new \Exception();
        $this->debug("=============================== START ====================================\n");
        $this->debug($e->getTraceAsString());
        $this->debug("\n");
        foreach ($this->schemas as $s) {
            $this->debug("SCHEMA ".get_class($s)."\n");
        }
        /** @var TreeBuilder $configSchema */
        $configSchema = new TreeBuilder('schema');
        foreach ($this->schemas as $schema) {
            /** Merge each schema definition into current tree at schemaName */
            $schemaName = $schema->getConfigTreeBuilder()->getRootNode()->getNode(true)->getName();
            $this->debug("\nINSERTING SCHEMA ".get_class($schema)." at [$schemaName]\n");
            /** @var ArrayNodeDefinition $currentNode */
            $currentNode = $configSchema->getRootNode();
            if ('' !== $schemaName) {
                foreach (explode('/', $schemaName) as $nodeName) {
                    $this->debug("INSERTING NODE $nodeName\n");
                    $childNodes = $currentNode->getChildNodeDefinitions();
                    $this->debug("PARSING SUBTREE...\n");
                    foreach ($childNodes as $child) {
                        if ($child instanceof ArrayNodeDefinition) {
                            try {
                                $node = $child->getNode();
                            } catch (\TypeError $e) {
                                $node = $child->getNode(true);
                            }
                            $this->debug("......".$node->getName()."\n");
                            if ($nodeName === $node->getName()) {
                                $currentNode = $child;
                                continue 2;
                            }
                        }
                    }
                    // if we are here, we didn't get searched node => create it
                    $this->debug("CREATE ARRAY NODE $nodeName\n");
                    $currentNode = $currentNode->children()->arrayNode($nodeName)->addDefaultsIfNotSet();
                }
            }
            /** @var ArrayNodeDefinition $insertedNode */
            $insertedNode = $schema->getConfigTreeBuilder()->getRootNode();
            $this->insertNodeAt($insertedNode, $currentNode);
        }
        $this->debug("================================ END =====================================\n");

        return $configSchema;
    }
    /**
     * insert all children of $insertedNode at $insertPoint
     *
     * @param ArrayNodeDefinition $insertedNode
     * @param ArrayNodeDefinition $insertPoint
     *
     * @return void
     */
    private function insertNodeAt($insertedNode, $insertPoint)
    {
        $nodeName = $insertedNode->getNode(true)->getName();
        $this->debug("Insert $nodeName children AT [".$insertPoint->getNode(true)->getPath()."]\n");
        foreach ($insertedNode->getChildNodeDefinitions() as $definition) {
            // if child is an array and array key already exists, we do not want to overwrite all subtree
            // instead we insert its children at existing array level
            $nodeName = $definition->getNode(true)->getName();
            $nodeType = get_class($definition);
            $exists = false;
            foreach ($insertPoint->getChildNodeDefinitions() as $child) {
                if ($nodeName === $child->getNode(true)->getName()) {
                    $this->debug("      [$nodeName] EXISTS ALREADY\n");
                    if (get_class($child) !== $nodeType) {
                        $source = $definition->getNode(true)->getPath()." [".$nodeType."]";
                        $target = $child->getNode(true)->getPath()." [".get_class($child)."]";
                        throw new \Exception("Type mismatch, can't replace $target with $source");
                    }
                    if ($child instanceof ArrayNodeDefinition) {
                        $this->debug("          IT'S AN ARRAY => INSERT UPPER\n");
                        /** @var ArrayNodeDefinition $definition */
                        $this->insertNodeAt($definition, $child);
                    } else {
                        $this->debug("          STANDARD NODE => SKIP\n");
                    }
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $this->debug("      [$nodeName] DOES NOT EXISTS => INSERT INPLACE\n");
                $insertPoint->children()->append($definition);
            }
        }
    }
    /**
     * debug
     *
     * @param string $message
     *
     * @return void
     */
    private function debug($message)
    {
        if ($this->debug) {
            print $message;
        }
    }
}
