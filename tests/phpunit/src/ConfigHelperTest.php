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
 * @uses DgfipSI1\ConfigHelper\MultiSchema
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
        $this->assertEquals(TestSchema::DUMP, $conf->dumpSchema());

        $class = new ReflectionClass(ConfigHelper::class);
        $dc = $class->getProperty('doCheck');
        $dc->setAccessible(true);
        $this->assertTrue($dc->getValue($conf));

        $msg = '';
        try {
            $conf->setSchema(new TestSchema2());
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        $this->assertEquals("Schema allready set, use 'addSchema' for multischema functionalities", $msg);

        $conf = new ConfigHelper();
        $this->assertFalse($dc->getValue($conf));
    }
    /**
     * @covers DgfipSI1\ConfigHelper\ConfigHelper::addSchema
     * @covers DgfipSI1\ConfigHelper\MultiSchema
     */
    public function testAddSetSchema(): void
    {
        $conf = new ConfigHelper();
        $msg = '';
        try {
            $conf->addSchema(new TestUnnamedSchema());
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        $this->assertMatchesRegularExpression('/Root schema defined in .* should have a name/', $msg);

        $conf = new ConfigHelper();
        $conf->addSchema(new TestSchema());
        $expectedDump = TestSchema::DUMP;
        $this->assertEquals($expectedDump, $conf->dumpSchema());

        $conf->addSchema(new TestSchema2());
        $expectedDump .= TestSchema2::DUMP;
        $this->assertEquals($expectedDump, $conf->dumpSchema());

        $conf->addSchema(new TestUnnamedSchema());
        $expectedDump .= TestUnnamedSchema::DUMP;
        $this->assertEquals($expectedDump, $conf->dumpSchema());
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
     * @covers DgfipSI1\ConfigHelper\ConfigHelper::setCheckOption
     * @covers DgfipSI1\ConfigHelper\ConfigHelper::setExpandOption
     * @covers DgfipSI1\ConfigHelper\ConfigHelper::dumpConfig
     * @covers DgfipSI1\ConfigHelper\ConfigHelper::dumpRawConfig
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
        $conf->setCheckOption(false);
        $conf->build();
        /** @var \Consolidation\Config\ConfigInterface $pc */
        $pc = $processedConfig->getValue($conf);
        $this->assertEquals('foo', $pc->get('true_or_false'));
        //
        // test with expansion and without
        //
        $data = [ 'this_is_a_string' => 'foo${another_string}', 'another_string' => 'bar' ];
        $conf->addArray('values', $data);
        $conf->build();
        // test dump and dumpRaw
        $dump = "this_is_a_string: foobar\nanother_string: bar\n";
        $dumpRaw = "this_is_a_string: 'foo\${another_string}'\nanother_string: bar\n";
        $this->assertEquals($dump, $conf->dumpConfig());
        $this->assertEquals($dumpRaw, $conf->dumpRawConfig());
        /** @var \Consolidation\Config\ConfigInterface $pc */
        $pc = $processedConfig->getValue($conf);
        $this->assertEquals('foobar', $pc->get('this_is_a_string'));
        $conf->setExpandOption(false);
        $conf->build();
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
     * @covers DgfipSI1\ConfigHelper\ConfigHelper::setDefault
     * @covers DgfipSI1\ConfigHelper\ConfigHelper::get
     * @covers DgfipSI1\ConfigHelper\ConfigHelper::getRaw
     * @covers DgfipSI1\ConfigHelper\ConfigHelper::contextSet
     */
    public function testSetAndGet(): void
    {
        $conf = new ConfigHelper(new TestSchema());
        $conf->setActiveContext('custom');
        $conf->set('this_is_a_string', 'foo${another_string}');
        $this->assertEquals('foo${another_string}', $conf->getContext('custom')->get('this_is_a_string'));
        $this->assertEquals('foo${another_string}', $conf->get('this_is_a_string'));

        /** check that setDefault invalidates cache */
        $conf->setDefault('another_string', 'bar');
        $this->assertEquals('foobar', $conf->get('this_is_a_string'));
        $this->assertEquals('foo${another_string}', $conf->getRaw('this_is_a_string'));

        $this->assertEquals('bar', $conf->get('another_string'));
        $this->assertEquals('foobar', $conf->get('this_is_a_string'));
        $conf->contextSet('new_context', 'another_string', '_new_bar');
        $this->assertEquals('foo_new_bar', $conf->get('this_is_a_string'));
        $this->assertEquals('_new_bar', $conf->getContext('new_context')->get('another_string'));
    }
}
