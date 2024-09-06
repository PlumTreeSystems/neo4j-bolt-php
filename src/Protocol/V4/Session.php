<?php


namespace PTS\Bolt\Protocol\V4;

use PTS\Bolt\Protocol\Message\V3\RunMessageWithMetadata;
use PTS\Bolt\Protocol\Message\V4\PullMessage;

class Session extends \PTS\Bolt\Protocol\V3\Session
{
    const PROTOCOL_VERSION = 4;

    /**
     * {@inheritdoc}
     */
    public static function getProtocolVersion()
    {
        return self::PROTOCOL_VERSION;
    }

    protected function getMessageMeta(): array
    {
        $meta = [];
        if ($this->config->getValue('database')) {
            $meta['db'] = $this->config->getValue('database');
        }
        if ($this->config->getValue('bookmarks')) {
            $meta['bookmarks'] = $this->config->getValue('bookmarks');
        }
        return $meta;
    }

    protected function createRunMessage($statement, $prams = [])
    {
        return new RunMessageWithMetadata($statement, $prams, $this->getMessageMeta());
    }

    protected function createPullAllMessage()
    {
        $meta = $this->getMessageMeta();
        // same effect as PullAll message
        $meta['n'] = -1;
        return new PullMessage($meta);
    }
}
