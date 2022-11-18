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
use Consolidation\Config\Util\ConfigInterpolatorInterface;
use Consolidation\Config\Util\ConfigRuntimeInterface;
use DgfipSI1\ConfigHelper\Exception\ConfigSchemaException;
use DgfipSI1\ConfigHelper\Exception\RuntimeException;
use DgfipSI1\ConfigHelper\Loader\ArrayLoader;
use Grasmash\Expander\Expander;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\ConfigurationInterface as configSchema;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor as ConfigProcessor;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * class ConfigHelper
 */
interface ConfigHelperInterface extends ConfigInterface, ConfigInterpolatorInterface, ConfigRuntimeInterface
{
    /**
     * sets the logger
     *
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function setLogger($logger);
    /**
     * sets the configuration schema
     *
     * @param configSchema $schema
     *
     * @return void
     */
    public function setSchema($schema);
    /**
     * adds a configuration to schem
     *
     * @param configSchema $schema
     *
     * @return void
     */
    public function addSchema($schema);
    /**
     * sets check option
     *
     * @param bool $doCheck
     *
     * @return self
     */
    public function setCheckOption($doCheck);
    /**
     * sets expand option
     *
     * @param bool $doExpand
     *
     * @return self
     */
    public function setExpandOption($doExpand);
    /**
     * findConfigFiles :
     * Use symfony finder to find configuration files. see https://symfony.com/doc/current/components/finder.html
     * - $rootDirs are given as parameter of finder->in()
     * - $pathPatterns as parameter of finder->path()
     * - $filePatternes as parameter of finder->name()
     *
     * Results are sorted by full path name by default.
     * If sortByFilename is true, sort is strictly on base filename.
     *
     * Each file is added to the ConfigOverlay with a context name of 'filename'
     * if more than one file has same name, then context name will be postfix with "02", "03" ...
     *
     * @param array<string>|string      $rootDirs
     * @param array<string>|string|null $paths      path patterns
     * @param array<string>|string|null $names      file name patterns
     * @param bool                      $sortByName
     * @param int                       $depth      recurse depth. 0 = norecurse, -1 = no limit
     *
     * @return void
     */
    public function findConfigFiles($rootDirs, $paths = null, $names = null, $sortByName = false, $depth = -1);
    /**
     * addFile : adds context or replace it with the contents of a yml file
     *
     * @param string $name
     * @param string $filename
     *
     * @return void
     */
    public function addFile($name, $filename = null);
   /**
     * addArray : adds context or replace it with the contents of data array
     *
     * @param string              $name
     * @param array<string,mixed> $data
     *
     * @return void
     */
    public function addArray($name, $data);
    /**
     * build: builds resulting config from all contexts :
     *  - expand ${var}
     *  - checks that config matches schema
     *
     * @return Config
     */
    public function build();
    /**
     * Sets the active context (the context that will be affected by 'set' method calls)
     *
     * @param string $contextName
     *
     * @return void
     */
    public function setActiveContext($contextName);
    /**
     * set a key value in the given context
     *
     * @param string $context
     * @param string $key
     * @param mixed  $value
     *
     * @return self
     */
    public function contextSet($context, $key, $value);
    /**
     * gets the raw value
     * - without check (i.e. without defaultValue)
     * - not expanded
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getRaw($key);
    /**
     * For debug purposes - dumps the schema in yaml format
     *
     * @return string
     */
    public function dumpSchema();
    /**
     * For debug purposes - dumps the merged config
     *
     * @return string
     */
    public function dumpConfig();
}
