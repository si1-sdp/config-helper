<?php
/*
 * This file is part of DgfipSI1\ConfigHelper.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DgfipSI1\ConfigHelperTests;

use DgfipSI1\ConfigHelper\ConfigHelper;
use DgfipSI1\ConfigHelper\MultiSchema;
use DgfipSI1\ConfigHelperTests\Schemas\EmptySchema;
use DgfipSI1\ConfigHelperTests\Schemas\Schema1;
use DgfipSI1\ConfigHelperTests\Schemas\Schema2;
use DgfipSI1\ConfigHelperTests\Schemas\Schema3;
use DgfipSI1\ConfigHelperTests\Schemas\SubBranchOverwrite;
use DgfipSI1\ConfigHelperTests\Schemas\TypeMismatch;
use DgfipSI1\ConfigHelperTests\Schemas\UnnamedSchema;
use DgfipSI1\ConfigHelperTests\TestSchema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Config\Definition\ConfigurationInterface;

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
        $conf = new ConfigHelper(new Schema1());
        $this->assertEquals(Schema1::DUMP, $conf->dumpSchema());

        $class = new ReflectionClass(ConfigHelper::class);
        $dc = $class->getProperty('doCheck');
        $dc->setAccessible(true);
        $this->assertTrue($dc->getValue($conf));

        $msg = '';
        try {
            $conf->setSchema(new Schema2());
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
        $configClass = new \ReflectionClass(ConfigHelper::class);
        $schema = $configClass->getProperty('schema');
        $schema->setAccessible(true);

        $schemaClass = new \ReflectionClass(MultiSchema::class);
        $schemas = $schemaClass->getProperty('schemas');
        $schemas->setAccessible(true);

        $conf = new ConfigHelper();
        $schemaObject = $schema->getValue($conf);

        $conf->addSchema(new EmptySchema());
        /** @var MultiSchema  $schemaObject */
        $schemaList = $schemas->getValue($schemaObject);
        /** @var array<ConfigurationInterface> $schemaList */
        $this->assertEquals([], array_keys($schemaList));

        $conf->addSchema(new Schema1());
        $expectedDump = Schema1::DUMP;
        $this->assertEquals($expectedDump, $conf->dumpSchema());

        $conf->addSchema(new UnnamedSchema());
        $expectedDump .= UnnamedSchema::DUMP;
        //print $conf->dumpSchema();
        $this->assertEquals($expectedDump, $conf->dumpSchema());

        $conf->addSchema(new Schema2());
        $expectedDump .= Schema2::DUMP;
        $this->assertEquals($expectedDump, $conf->dumpSchema());

        $conf->addSchema(new SubBranchOverwrite());
        $expectedDump .= SubBranchOverwrite::DUMP;
        $this->assertEquals($expectedDump, $conf->dumpSchema());

        $conf->addSchema(new Schema3());
        $expectedDump .= Schema3::DUMP;
        $this->assertEquals($expectedDump, $conf->dumpSchema());

        $conf->addSchema(new TypeMismatch());
        $msg = '';
        try {
            $conf->build();
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        $this->assertMatchesRegularExpression('/Type mismatch, can\'t replace/', $msg);
    }
    /**
     * @covers DgfipSI1\ConfigHelper\MultiSchema::debug
     *
     * @return void
     */
    public function testMultiSchemaDebug(): void
    {
        $schemaClass = new \ReflectionClass(MultiSchema::class);
        $debugProp   = $schemaClass->getProperty('debug');
        $debugProp->setAccessible(true);
        $debugMethod = $schemaClass->getMethod('debug');
        $debugMethod->setAccessible(true);

        $this->expectOutputString('foo');
        $schema = new MultiSchema();
        $debugProp->setValue($schema, true);
        $debugMethod->invokeArgs($schema, ['foo']);
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
        $conf = new ConfigHelper(new Schema1());
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
        $conf = new ConfigHelper(new Schema1());
        $conf->addArray('values', $data);
        $this->assertTrue($conf->hasContext('values'));
        $this->assertEquals($content, $conf->dumpConfig());
    }
    /**
     * test addFoundFiles method
     *
     * @covers DgfipSI1\ConfigHelper\ConfigHelper::addFoundFiles
     */
    public function testAddFoundFiles(): void
    {
        /* test data in dataRoot :
         * config01                      number     string                  bool
         *   00-baseConfig.yml           100        00-baseConfig.yml       -
         *   01-testConfig.yaml            -        01-testConfig.yaml      true
         *   03-testConfig.Yaml            3        03-testConfig.Yaml      -
         * config02
         *   00-baseConfig.yml           200        00-baseConfig.yml       -
         *   02-testConfig.Yaml            2        02-testConfig.Yaml      false
        */
        $file = __DIR__."/../data/testConfig.yaml";
        $content = str_replace("---\n", '', "".file_get_contents($file));
        $conf = new ConfigHelper(new Schema1());
        $dataRoot = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR.'data';

        // test with all files in default order
        $conf->addFoundFiles($dataRoot, ['config'], [ '*.yaml', '*.yml']);
        $contexts = [ '00-baseConfig', '01-testConfig', '03-testConfig', '00-baseConfig-02', '02-testConfig'];
        $this->assertEquals($contexts, $this->getAddedContexts($conf));
        $this->assertEquals(false, $conf->get('true_or_false'));
        $this->assertEquals("02-testConfig.yaml", $conf->get('this_is_a_string'));
        $this->assertEquals(2, $conf->get('positive_number'));

        // test with all files filename order
        $conf = new ConfigHelper(new Schema1());
        $conf->addFoundFiles($dataRoot, ['config'], [ '*.yaml', '*.yml'], true);
        $contexts = [ '00-baseConfig', '00-baseConfig-02', '01-testConfig', '02-testConfig', '03-testConfig'];
        $this->assertEquals($contexts, $this->getAddedContexts($conf));
        $this->assertEquals(false, $conf->get('true_or_false'));
        $this->assertEquals("03-testConfig.yaml", $conf->get('this_is_a_string'));
        $this->assertEquals(3, $conf->get('positive_number'));

        // test with config01 directory only
        $conf = new ConfigHelper(new Schema1());
        $conf->addFoundFiles($dataRoot, ['config01'], [ '*.yaml', '*.yml']);
        $contexts = [ '00-baseConfig', '01-testConfig', '03-testConfig'];
        $this->assertEquals($contexts, $this->getAddedContexts($conf));
        $this->assertEquals(true, $conf->get('true_or_false'));
        $this->assertEquals("03-testConfig.yaml", $conf->get('this_is_a_string'));
        $this->assertEquals(3, $conf->get('positive_number'));

        // test with yaml files only
        $conf = new ConfigHelper(new Schema1());
        $conf->addFoundFiles($dataRoot, ['config'], [ '*.yaml'], true);
        $contexts = [ '01-testConfig', '02-testConfig', '03-testConfig'];
        $this->assertEquals($contexts, $this->getAddedContexts($conf));
        $this->assertEquals(false, $conf->get('true_or_false'));
        $this->assertEquals("03-testConfig.yaml", $conf->get('this_is_a_string'));
        $this->assertEquals(3, $conf->get('positive_number'));

        // test without file or path filters
        $conf = new ConfigHelper(new Schema1());
        $conf->addFoundFiles($dataRoot.DIRECTORY_SEPARATOR.'tests');
        $contexts = [ '00-baseConfig', '01-testConfig', '03-testConfig', '00-baseConfig-02', '02-testConfig'];
        $this->assertEquals($contexts, $this->getAddedContexts($conf));
        $this->assertEquals(false, $conf->get('true_or_false'));
        $this->assertEquals("02-testConfig.yaml", $conf->get('this_is_a_string'));
        $this->assertEquals(2, $conf->get('positive_number'));

        // $this->assertTrue($conf->hasContext('00-baseConfig'));
        // $this->assertTrue($conf->hasContext('00-baseConfig02'));
        // $this->assertTrue($conf->hasContext('01-testConfig'));
        // $this->assertTrue($conf->hasContext('02-testConfig'));
        //$this->assertTrue($conf->hasContext('03-testConfig'));
        // $this->assertEquals($content, $conf->dumpConfig());
    }
    /**
     * get all contextes but default ones
     *
     * @param ConfigHelper $conf
     *
     * @return array<string>
     */
    public function getAddedContexts($conf)
    {
        $class = new \ReflectionClass(ConfigHelper::class);
        $ctx = $class->getProperty('contexts');
        $ctx->setAccessible(true);
        /** @var array<string,mixed> $contexts */
        $contexts = $ctx->getValue($conf);
        $contextsNames = array_keys($contexts);
        array_shift($contextsNames); // get rid of 'default'
        array_pop($contextsNames);   // get rid of 'process'

        return $contextsNames;
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
        $conf = new ConfigHelper(new Schema1());
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
        $conf = new ConfigHelper(new Schema1());
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
        $conf = new ConfigHelper(new Schema1());
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
