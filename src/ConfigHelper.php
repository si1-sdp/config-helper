<?php

declare(strict_types=1);

/*
 * This file is part of DgfipSI1\ConfigHelper
 */

namespace DgfipSI1\ConfigHelper;

use Consolidation\Config\Config;
use Consolidation\Config\ConfigInterface;
use Consolidation\Config\Util\ConfigOverlay;
use Consolidation\Config\Loader\YamlConfigLoader;
use DgfipSI1\ConfigHelper\Loader\ArrayLoader;
use Grasmash\Expander\Expander;
use Symfony\Component\Config\Definition\ConfigurationInterface as configSchema;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor as ConfigProcessor;
use Symfony\Component\Yaml\Yaml;

/**
 * class RepoMirror
 * Yum repo mirror class
 */
class ConfigHelper extends ConfigOverlay
{
    const NO_CHECK = 1;
    const NO_EXPANSION = 2;

    /** @var configSchema $schema */
    protected $schema;

    /** @var ConfigInterface|null $processedConfig */
    protected $processedConfig;

    /** @var string $activeContext */
    protected $activeContext;

    /**
     * Constructor
     * @param configSchema $schema
     *
     * @return void
     */
    public function __construct($schema = null)
    {
        parent::__construct();
        if (null !== $schema) {
            $this->setSchema($schema);
        }
        $this->activeContext = parent::DEFAULT_CONTEXT;
    }
    /**
     * Undocumented function
     *
     * @param configSchema $schema
     *
     * @return void
     */
    public function setSchema($schema)
    {
        $this->schema = $schema;
    }

    /**
     * addFile : adds context or replace it with the contents of a yml file
     *
     * @param string $name
     * @param string $filename
     *
     * @return void
     */
    public function addFile($name, $filename = null)
    {
        if (null === $filename) {
            $filename = $name;
        }
        $loader = new YamlConfigLoader();
        $loader->load($filename);

        $config = new Config();
        $config->import($loader->export());

        $this->addContext($name, $config);
        $this->processedConfig = null;
    }
   /**
     * addArray : adds context or replace it with the contents of data array
     *
     * @param string              $name
     * @param array<string,mixed> $data
     *
     * @return void
     */
    public function addArray($name, $data)
    {
        $loader = new ArrayLoader();
        $loader->import($name, $data);

        $config = new Config();
        $config->import($loader->export());

        $this->addContext($name, $config);
        $this->processedConfig = null;
    }

    /**
     * build: builds resulting config from all contexts :
     *  - expand ${var}
     *  - checks that config matches schema
     *
     * @param integer $options
     *
     * @return Config
     */
    public function build($options = 0)
    {
        $this->processedConfig = new Config();

        if (0 !== ($options & self::NO_EXPANSION)) {
            $expanded = $this->export();
        } else {
            $expander = new expander();
            $expanded = $expander->expandArrayProperties($this->export());
        }
        if (0 !== ($options & self::NO_CHECK)) {
            $this->processedConfig->import($expanded);
        } else {
            try {
                $processor = new ConfigProcessor();
                $this->processedConfig->import($processor->processConfiguration($this->schema, [ $expanded ]));
            } catch (InvalidConfigurationException $e) {
                //print "===============================================================\n";
                //print_r($expanded);
                //print "===============================================================\n";
                throw new \Exception($e->getMessage());
            }
        }

        return $this->processedConfig;
    }
    /**
     * Sets the active context (the context that will be affected by 'set' method calls)
     *
     * @param string $contextName
     *
     * @return void
     */
    public function setActiveContext($contextName)
    {
        if (!array_key_exists($contextName, $this->contexts)) {
            $this->addPlaceholder($contextName);
        }
        $this->activeContext = $contextName;
    }
    /**
     * set a key value in the currently active context
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return self
     */
    public function set($key, $value)
    {
        $this->processedConfig = null;
        $this->contexts[$this->activeContext]->set($key, $value);

        return $this;
    }
    /**
     * set a key value in the given context
     *
     * @param string $context
     * @param string $key
     * @param mixed  $value
     *
     * @return self
     */
    public function contextSet($context, $key, $value)
    {
        if (!array_key_exists($context, $this->contexts)) {
            $this->addPlaceholder($context);
        }
        $this->contexts[$context]->set($key, $value);
        $this->processedConfig = null;

        return $this;
    }
    /**
     * gets the value of the given key
     *
     * @param string      $key
     * @param string|null $defaultFallback
     *
     * @return mixed
     */
    public function get($key, $defaultFallback = null)
    {
        $conf = $this->processedConfig;
        if (null === $conf) {
            $conf = $this->build();
        }

        return $conf->get($key, $defaultFallback);
    }
    /**
     * For debug purposes - dumps the schema in yaml format
     *
     * @return void
     */
    public function dumpSchema()
    {
        $dumper = new YamlReferenceDumper();
        print $dumper->dump($this->schema);
    }
    /**
     * For debug purposes - dumps the merged config
     *
     * @return void
     */
    public function dumpConfig()
    {
        $conf = $this->processedConfig;
        if (null === $conf) {
            $conf = $this->build();
        }
        print Yaml::dump($conf->export(), 2, 4);
    }
}
