<?php

namespace PTS\Bolt\Tests\Integration;

use PTS\Bolt\Configuration;
use PTS\Bolt\Protocol\SessionInterface;
use PTS\Bolt\Tests\IntegrationTestCase;
use PTS\Bolt\Driver;

/**
 * @group integration
 * @group ssl
 */
class SSLConnectionTest extends IntegrationTestCase
{
    public function testSSLConnectionWithoutValidation()
    {
        $config = $this->getConfig()->withTLSMode(Configuration::TLSMODE_REQUIRED_NO_VALIDATION);
        $this->setDriverWhithConfig($config);
        $session = $this->getSession();
        $this->assertInstanceOf(SessionInterface::class, $session);
    }

    public function testSSLConnectionWithValidation()
    {
        $config = $this->getConfig()->withTLSMode(Configuration::TLSMODE_REQUIRED);
        $this->setDriverWhithConfig($config);
        $this->expectException(\PTS\Bolt\Exception\SSLException::class);
        $this->getSession();
    }
}
