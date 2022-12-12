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
class ConfigHelper extends ConfigOverlay implements ConfigHelperInterface
{
    const NO_CHECK = 1;
    const NO_EXPANSION = 2;

    public const DUMP_MODE_BUILT    = 'built';
    public const DUMP_MODE_RAW      = 'raw';
    public const DUMP_MODE_CONTEXTS = 'contexts';


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

    /** @var LoggerInterface|null $logger */
    protected $logger;

    /** @var bool $debug */
    protected $debug = false;

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
     * sets the logger
     *
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
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
    public function findConfigFiles($rootDirs, $paths = null, $names = null, $sortByName = false, $depth = -1)
    {
        $finder = new Finder();
        $finder->in($rootDirs);
        if (null !== $paths) {
            $finder->path($paths);
        }
        if (null === $names) {
            $names = [ '*.yml', '*.yaml' ];
        }
        $finder->name($names);
        if (0 === $depth) {
            $finder->depth('== 0');
        } elseif ($depth > 0) {
            $finder->depth("<= $depth");
        }
        if ($sortByName) {
            $finder->sort(function (\SplFileInfo $a, \SplFileInfo $b) {
                return strcmp($a->getFilename(), $b->getFilename());
            });
        } else {
            $finder->sort(function (\SplFileInfo $a, \SplFileInfo $b) {
                return $this->cmpConfigPaths($a, $b);
            });
        }
        foreach ($finder as $file) {
            $this->addFile($file->getPathname());
        }
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
            $name = basename($filename, ".twig");
            $name = basename($name, ".yaml");
            $name = basename($name, ".yml");
            $this->debug("Adding context : $name from file ".$filename, ['name' => 'addFile']);
        }
        $n = 1;
        while ($this->hasContext($name)) {
            $name = sprintf("%s-%02d", $name, ++$n);
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
                $message .= $this->dumpConfig(self::DUMP_MODE_RAW);
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
     * For debug purposes - dumps the config
     *
     * @param string $mode
     *
     * @return string
     */
    public function dumpConfig($mode = self::DUMP_MODE_BUILT)
    {
        $inline = 4;
        $indent = 2;
        $flags = Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE|Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK;
        switch ($mode) {
            case (self::DUMP_MODE_BUILT):
                $conf = $this->processedConfig;
                if (null === $conf) {
                    $conf = $this->build();
                }
                $ret =  Yaml::dump($conf->export(), $inline, $indent, $flags);
                break;
            case (self::DUMP_MODE_RAW):
                $ret =  Yaml::dump($this->export(), $inline, $indent, $flags);
                break;
            case (self::DUMP_MODE_CONTEXTS):
                $ret = '';
                foreach ($this->getContextNames() as $context) {
                    $conf = $this->getContext($context);
                    $ret .= "============================================================\n";
                    $ret .= "         CONTEXT : $context\n";
                    $ret .= "============================================================\n";
                    $ret .= Yaml::dump($conf->export(), $inline, $indent, $flags);
                    $ret .= "\n";
                }
                break;
            default:
                throw new RuntimeException(sprintf("Unknown dump mode '%s'", $mode));
        }

        return $ret;
    }
    /**
     * Debugging trace
     *
     * @param string               $message
     * @param array<string,string> $context
     *
     * @return void
     */
    protected function debug($message, $context = [])
    {
        if (null === $this->logger) {
            if ($this->debug) {
                print "DEBUG : $message\n";
            }
        } else {
            $this->logger->debug($message, $context);
        }
    }
    /**
     * Gets an array of all contexts
     *
     * @return array<string>
     */
    protected function getContextNames()
    {
        return array_keys($this->contexts);
    }
    /**
     *
     *
     * @param \SplFileInfo $a
     * @param \SplFileInfo $b
     *
     * @return int
     */
    protected function cmpConfigPaths(\SplFileInfo $a, \SplFileInfo $b)
    {
        // do not use realpath on phar files
        if ('phar://' === substr($a->getPath(), 0, 7) && 'phar://' === substr($b->getPath(), 0, 7)) {
            return strcmp($a->getPathname(), $b->getPathname());
        }
        if ('phar://' !== substr($a->getPath(), 0, 7) && 'phar://' !== substr($b->getPath(), 0, 7)) {
            return strcmp($a->getRealPath(), $b->getRealPath());
        }
        // if one configFile in phar and other is normal file, put phar first
        // this way phar config will be overriden by live config
        return 'phar://' !== substr($a->getPath(), 0, 7) ? 1 : -1;
    }
}
