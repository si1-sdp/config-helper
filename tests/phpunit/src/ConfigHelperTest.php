<?php
/*
 * This file is part of DgfipSI1\ConfigHelper.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DgfipSI1\ConfigHelperTests;

use DgfipSI1\ConfigHelper\ConfigHelper;
use DgfipSI1\ConfigHelperTests\TestSchema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @uses DgfipSI1\ConfigHelper\ConfigHelper
 *
 */
class ConfigurationHelperTest extends TestCase
{
   /**
     * @covers DgfipSI1\ConfigHelper\ConfigHelper::__construct
     * @covers DgfipSI1\ConfigHelper\ConfigHelper::setSchema
     * @covers DgfipSI1\ConfigHelper\ConfigHelper::dumpSchema
     */
    public function testConstructor(): void
    {
        $conf = new ConfigHelper(new TestSchema());
        $this->assertInstanceOf(ConfigHelper::class, $conf);
        $this->assertEquals(TestSchema::DUMPED_SCHEMA, $conf->dumpSchema());
    }
    /**
     * test addFile method
     *
     * @covers DgfipSI1\ConfigHelper\ConfigHelper::addFile
     * @covers DgfipSI1\ConfigHelper\ConfigHelper::dumpConfig
     */
    public function testAddFile(): void
    {
        $file = __DIR__."/../data/testConfig.yaml";
        $content = str_replace("---\n", '', "".file_get_contents($file));
        $conf = new ConfigHelper(new TestSchema());
        $conf->addFile($file);
        $this->assertTrue($conf->hasContext($file));
        $this->assertEquals($content, $conf->dumpConfig());
    }
    /**
     * test addArray method
     *
     * @covers DgfipSI1\ConfigHelper\ConfigHelper::addArray
     *
     * @uses DgfipSI1\ConfigHelper\Loader\ArrayLoader
     */
    public function testAddArray(): void
    {
        $file = __DIR__."/../data/testConfig.yaml";
        $content = str_replace("---\n", '', ''.file_get_contents($file));
        $data['true_or_false']    = true;
        $data['positive_number']  = 50;
        $data['this_is_a_string'] = "foo";
        $conf = new ConfigHelper(new TestSchema());
        $conf->addArray('values', $data);
        $this->assertTrue($conf->hasContext('values'));
        $this->assertEquals($content, $conf->dumpConfig());
    }
   /**
     * test build method
     *
     * @covers DgfipSI1\ConfigHelper\ConfigHelper::Build
     *
     * @uses DgfipSI1\ConfigHelper\Loader\ArrayLoader
     */
    public function testBuild(): void
    {
        $class = new ReflectionClass('DgfipSI1\ConfigHelper\ConfigHelper');
        $processedConfig = $class->getProperty('processedConfig');
        $processedConfig->setAccessible(true);
      //
      // test with bad config and check, bad config and no check
      //
        $data['true_or_false']    = 'foo';
        $conf = new ConfigHelper(new TestSchema());
        $conf->addArray('values', $data);
        $msg = '';
        try {
            $conf->build();
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        $this->assertMatchesRegularExpression('/Expected "bool", but got "string"/', $msg);
      // now with NO_CHECK, build should not throw exception
        $conf->build(ConfigHelper::NO_CHECK);
        /** @var \Consolidation\Config\ConfigInterface $pc */
        $pc = $processedConfig->getValue($conf);
        $this->assertEquals('foo', $pc->get('true_or_false'));
      //
      // test with expansion and without
      //
        $data = [ 'this_is_a_string' => 'foo${another_string}', 'another_string' => 'bar' ];
      //$data = [ 'this_is_a_string' => '${positive_number}' ];
        $conf->addArray('values', $data);
        $conf->build();
        /** @var \Consolidation\Config\ConfigInterface $pc */
        $pc = $processedConfig->getValue($conf);
        $this->assertEquals('foobar', $pc->get('this_is_a_string'));
        $conf->build(ConfigHelper::NO_EXPANSION);
        /** @var \Consolidation\Config\ConfigInterface $pc */
        $pc = $processedConfig->getValue($conf);
        $this->assertEquals('foo${another_string}', $pc->get('this_is_a_string'));
    }
    /**
     * test setActiveContext method
     *
     * @covers DgfipSI1\ConfigHelper\ConfigHelper::setActiveContext
     */
    public function testSetActiveContext(): void
    {
        $class = new ReflectionClass('DgfipSI1\ConfigHelper\ConfigHelper');
        $activeContext = $class->getProperty('activeContext');
        $activeContext->setAccessible(true);
        $conf = new ConfigHelper(new TestSchema());
        $conf->setActiveContext('custom');
        $this->assertTrue($conf->hasContext('custom'));
        $this->assertEquals('custom', $activeContext->getValue($conf));
    }
    /**
     * test set method
     *
     * @covers DgfipSI1\ConfigHelper\ConfigHelper::set
     * @covers DgfipSI1\ConfigHelper\ConfigHelper::get
     * @covers DgfipSI1\ConfigHelper\ConfigHelper::contextSet
     */
    public function testSetAndGet(): void
    {
        $conf = new ConfigHelper(new TestSchema());
        $conf->setActiveContext('custom');
        $conf->set('another_string', 'bar');
        $conf->set('this_is_a_string', 'foo${another_string}');
        $this->assertEquals('foo${another_string}', $conf->getContext('custom')->get('this_is_a_string'));
        $this->assertEquals('bar', $conf->getContext('custom')->get('another_string'));
        $this->assertEquals('foobar', $conf->get('this_is_a_string'));
        $conf->contextSet('new_context', 'another_string', '_new_bar');
        $this->assertEquals('foo_new_bar', $conf->get('this_is_a_string'));
        $this->assertEquals('_new_bar', $conf->getContext('new_context')->get('another_string'));
    }
}
