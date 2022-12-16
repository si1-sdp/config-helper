<?php
/*
 * This file is part of DgfipSI1\ConfigHelper.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DgfipSI1\ConfigHelperTests;

use DgfipSI1\ConfigHelper\Loader\ArrayLoader;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @uses DgfipSI1\ConfigHelper\ConfigHelper
 *
 */
class ArrayLoaderTest extends TestCase
{
   /**
     * @covers DgfipSI1\ConfigHelper\Loader\ArrayLoader::import
     */
    public function testImport(): void
    {
        $loader = new ArrayLoader();

        $ref = new \ReflectionClass(ArrayLoader::class);
        $src = $ref->getProperty('source');
        $src->setAccessible(true);

        $dataIn  = [ 'foo' => 'bar', 'subtree.bar' => 100, 'subtree.baz' => 'another_value'];
        $dataOut = [ 'foo' => 'bar', 'subtree' => [ 'bar' => 100, 'baz' => 'another_value']];
        self::assertEquals($dataOut, $loader->import('values', $dataIn)->export());
        self::assertEquals($dataIn, $loader->import('values', $dataIn, false)->export());

        self::assertEquals('values', $src->getValue($loader));
    }
    /**
     * test addFile method
     *
     * @covers DgfipSI1\ConfigHelper\Loader\ArrayLoader::load
     * @covers DgfipSI1\ConfigHelper\Loader\ArrayLoader::unsupported
     */
    public function testLoad(): void
    {
        $loader = new ArrayLoader();
        $msg = '';
        try {
            $loader->load('foo');
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        self::assertEquals("The method 'load' is not supported for the ArrayLoader class.", $msg);
    }
}
