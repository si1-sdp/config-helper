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
use DgfipSI1\ConfigHelper\Exception\ConfigSchemaException;
use DgfipSI1\ConfigHelper\Exception\ConfigurationException;
use DgfipSI1\ConfigHelper\Exception\RuntimeException;
use DgfipSI1\ConfigHelper\Loader\ArrayLoader;
use Grasmash\Expander\Expander;
use Symfony\Component\Config\Definition\ConfigurationInterface as configSchema;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor as ConfigProcessor;
use Symfony\Component\Yaml\Yaml;

/**
 * class ConfigHelper
 */
class ConfigHelper extends ConfigOverlay
{
    const NO_CHECK = 1;
    const NO_EXPANSION = 2;

    /** @var MultiSchema $schema */
    protected $schema;

    /** @var ConfigInterface|null $processedConfig */
    protected $processedConfig;

    /** @var string $activeContext */
    protected $activeContext;

    /** @var bool $doCheck */
    protected $doCheck;

    /** @var bool $doExpand */
    protected $doExpand;

    /**
     * Constructor
     * @param configSchema $schema
     *
     * @return void
     */
    public function __construct($schema = null)
    {
        parent::__construct();
        $this->schema = new MultiSchema();
        if (null !== $schema) {
            $this->setSchema($schema);
            $this->doCheck = true;
        } else {
            $this->doCheck = false;
        }
        $this->doExpand = true;
        $this->activeContext = parent::DEFAULT_CONTEXT;
    }
    /**
     * sets the configuration schema
     *
     * @param configSchema $schema
     *
     * @return void
     */
    public function setSchema($schema)
    {
        if (!$this->schema->empty()) {
            throw new ConfigSchemaException("Schema allready set, use 'addSchema' for multischema functionalities");
        }
        $this->schema->addSchema($schema);
        $this->doCheck = true;
    }
    /**
     * adds a configuration to schem
     *
     * @param configSchema $schema
     *
     * @return void
     */
    public function addSchema($schema)
    {
        if ($this->schema->empty()) {
            $this->setSchema($schema);
        } else {
            $this->schema->addSchema($schema);
        }
    }
    /**
     * sets check option
     *
     * @param bool $doCheck
     *
     * @return self
     */
    public function setCheckOption($doCheck)
    {
            $this->doCheck = $doCheck;

            return $this;
    }
    /**
     * sets expand option
     *
     * @param bool $doExpand
     *
     * @return self
     */
    public function setExpandOption($doExpand)
    {
            $this->doExpand = $doExpand;

            return $this;
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
     * @return Config
     */
    public function build()
    {
        $this->processedConfig = new Config();

        if ($this->doExpand) {
            $expander = new expander();
            $expanded = $expander->expandArrayProperties($this->export());
        } else {
            $expanded = $this->export();
        }
        if (!$this->doCheck) {
            $this->processedConfig->import($expanded);
        } else {
            try {
                $processor = new ConfigProcessor();
                $this->processedConfig->import($processor->processConfiguration($this->schema, [ $expanded ]));
            } catch (InvalidConfigurationException $e) {
                $message = "================== CONFIG =====================================\n";
                $message .= $this->dumpRawConfig();
                $message .= "===============================================================\n";
                $message .= $e->getMessage();
                throw new RuntimeException($message);
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
     * just call parent and clear cache
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return self
     */
    public function setDefault($key, $value)
    {
        parent::setDefault($key, $value);
        $this->processedConfig = null;

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
     * gets the raw value
     * - without check (i.e. without defaultValue)
     * - not expanded
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getRaw($key)
    {
        return parent::get($key);
    }
    /**
     * For debug purposes - dumps the schema in yaml format
     *
     * @return string
     */
    public function dumpSchema()
    {
        $dumper = new YamlReferenceDumper();

        return $dumper->dump($this->schema);
    }
    /**
     * For debug purposes - dumps the merged config
     *
     * @return string
     */
    public function dumpConfig()
    {
        $conf = $this->processedConfig;
        if (null === $conf) {
            $conf = $this->build();
        }

        return Yaml::dump($conf->export(), 4, 2);
    }
    /**
     * For debug purposes - dumps the merged config
     *
     * @return string
     */
    public function dumpRawConfig()
    {
        return Yaml::dump($this->export(), 4, 2);
    }
}
