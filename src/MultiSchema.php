<?php

declare(strict_types=1);

/*
 * This file is part of DgfipSI1\ConfigHelper
 */

namespace DgfipSI1\ConfigHelper;

use DgfipSI1\ConfigHelper\Exception\ConfigSchemaException;
use DgfipSI1\ConfigHelper\Exception\RuntimeException;
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
        return 0 === sizeof($this->schemas);
    }
    /**
     * get global configTree
     *
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $e = new \Exception();
        $this->debug("=============================== START ====================================\n");
        $this->debug($e->getTraceAsString());
        $this->debug("\n");
        /** @infection-ignore-all */
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
                    $child = $this->getChildByName($currentNode, $nodeName);
                    if (null === $child) {
                        $this->debug("CREATE ARRAY NODE $nodeName\n");
                        $currentNode = $currentNode->children()->arrayNode($nodeName)->addDefaultsIfNotSet();
                    } else {
                        if ($child instanceof ArrayNodeDefinition) {
                            $currentNode = $child;
                        } else {
                            $srcPath = $child->getNode()->getPath();
                            $tgtPath = $currentNode->getNode()->getPath();
                            $target = "$srcPath [".preg_replace('/.*\\\\/', '', get_class($child))."]";
                            $source = "$tgtPath/$nodeName [ArrayNodeDefinition]";
                            throw new ConfigSchemaException("Type mismatch, can't replace $target with $source");
                        }
                    }
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
        $this->debug("Insert [$nodeName] children AT [".$insertPoint->getNode(true)->getPath()."]\n");
        foreach ($insertedNode->getChildNodeDefinitions() as $definition) {
            // if child is an array and array key already exists, we do not want to overwrite all subtree
            // instead we insert its children at existing array level
            $nodeName = $definition->getNode()->getName();
            $nodeType = get_class($definition);
            $child = $this->getChildByName($insertPoint, $nodeName);
            if (null !== $child) {
                $this->debug("      [$nodeName] EXISTS ALREADY\n");
                if (get_class($child) !== $nodeType) {
                    $srcPath = $definition->getNode()->getPath();
                    $tgtPath = $definition->getNode()->getPath();
                    $source = "$srcPath [".preg_replace('/.*\\\\/', '', $nodeType)."]";
                    $target = "$tgtPath [".preg_replace('/.*\\\\/', '', get_class($child))."]";
                    throw new ConfigSchemaException("Type mismatch, can't replace $target with $source");
                }
                if ($child instanceof ArrayNodeDefinition) {
                    $this->debug("          IT'S AN ARRAY => INSERT UPPER\n");
                    /** @var ArrayNodeDefinition $definition */
                    $this->insertNodeAt($definition, $child);
                } else {
                    $this->debug("          STANDARD NODE => SKIP\n");
                }
            } else {
                $this->debug("      [$nodeName] DOES NOT EXISTS => INSERT INPLACE\n");
                $insertPoint->children()->append($definition);
            }
        }
    }
    /**
     * Undocumented function
     *
     * @param ArrayNodeDefinition $node
     * @param string              $name
     *
     * @return NodeDefinition|null
     */
    private function getChildByName($node, $name)
    {
        foreach ($node->getChildNodeDefinitions() as $child) {
            if ($name === $child->getNode(true)->getName()) {
                return $child;
            }
        }

        return null;
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
            print($message);
        }
    }
}
