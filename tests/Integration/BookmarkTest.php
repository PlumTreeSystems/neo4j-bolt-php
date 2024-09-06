<?php

namespace PTS\Bolt\Tests\Integration;

use PTS\Bolt\Tests\IntegrationTestCase;
use PTS\Bolt\Exception\BoltExceptionInterface;

/**
 * @group integration
 * @group V4+
 */
class BookmarkTest extends IntegrationTestCase
{
    public function testLastBookmarkIsSet()
    {
        if ($this->getConfig()->getValue('forced_bolt_version') < 4) {
            $this->markTestSkipped('Bookmark feature only since V4+');
            return;
        }
        $session = $this->getSession();
        $result = $session->run('RETURN 1');
        $this->assertEquals($session->getLastBookmark(), $result->getSummary()->getBookmark());
    }

    public function testInvalidBookmarkFails()
    {
        if ($this->getConfig()->getValue('forced_bolt_version') < 4) {
            $this->markTestSkipped('Bookmark feature only since V4+');
            return;
        }
        $config = $this->getConfig()->withBookmarks(['FB:bad']);
        $this->setDriverWhithConfig($config);
        $session = $this->getSession();
        $this->expectException(BoltExceptionInterface::class);
        $session->run('RETURN 1');
    }

    public function testGoodBookmarkWorks()
    {
        if ($this->getConfig()->getValue('forced_bolt_version') < 4) {
            $this->markTestSkipped('Bookmark feature only since V4+');
            return;
        }
        $session = $this->getSession();
        $session->run('RETURN 1');
        $this->setDriverWhithConfig($this->getConfig()->withBookmarks([$session->getLastBookmark()]));
        $session2 = $this->getSession();
        $result = $session2->run('RETURN 1');
        $this->assertNotNull($result);
    }
}
