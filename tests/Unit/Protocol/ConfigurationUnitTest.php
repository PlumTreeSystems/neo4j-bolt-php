<?php

namespace PTS\Bolt\Tests\Protocol;

use PTS\Bolt\Configuration;

/**
 * @group configuration
 * @group unit
 */
class ConfigurationUnitTest extends \PHPUnit\Framework\TestCase
{
    public function testConnectionUriParsing()
    {
        $uri = 'bolt://admin:secret@neo4j:1234/default?tls=true&validate_tls=false';
        $config = Configuration::create()->withUri($uri);
        $this->assertEquals('bolt', $config->getValue('scheme'));
        $this->assertEquals('admin', $config->getValue('username'));
        $this->assertEquals('secret', $config->getValue('password'));
        $this->assertEquals('neo4j', $config->getValue('host'));
        $this->assertEquals('1234', $config->getValue('port'));
        $this->assertEquals('default', $config->getValue('database'));
        $this->assertEquals(Configuration::TLSMODE_REQUIRED_NO_VALIDATION, $config->getValue('tls_mode'));
    }
}
