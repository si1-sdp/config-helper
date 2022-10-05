<?php

/*
 * This file is part of DgfipSI1\ConfigHelper
 */
namespace DgfipSI1\ConfigHelper\Loader;

use Consolidation\Config\Config;
use Consolidation\Config\Loader\ConfigLoader;

/**
 * Load configuration files, and fill in any property values that
 * need to be expanded.
 */
class ArrayLoader extends ConfigLoader
{

    /**
     * @param string              $name
     * @param array<string,mixed> $data
     * @param bool                $expandDotedValues
     *
     * @return self
     */
    public function import($name, $data, $expandDotedValues = true)
    {
        if (null !== $data) {
            if ($expandDotedValues) {
                $config = new Config();
                foreach ($data as $optName => $optValue) {
                    $config->set("$optName", $optValue);
                }
                $this->config = $config->export();
            } else {
                $this->config = $data;
            }
        }
        $this->setSourceName($name);

        return $this;
    }
     /**
     * {@inheritdoc}
     *
     */
    public function load($string)
    {
        $this->unsupported(__FUNCTION__);
        /** @phpstan-ignore-line - ignore no return statement (we have thrown an exception) */
    }
    /**
     * Generic function for unsuported methods
     *
     * @param string $fn
     *
     * @return void
     */
    protected function unsupported($fn)
    {
        throw new \Exception("The method '$fn' is not supported for the ArrayLoader class.");
    }
}
