<?php

/*
 * This file is part of the GraphAware Bolt package.
 *
 * (c) GraphAware Ltd <christophe@graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PTS\Bolt\Protocol\V1;

use PTS\Bolt\Driver;
use PTS\Bolt\Exception\BoltInvalidArgumentException;
use PTS\Bolt\IO\AbstractIO;
use PTS\Bolt\Protocol\AbstractSession;
use PTS\Bolt\Protocol\Message\AbstractMessage;
use PTS\Bolt\Protocol\Message\AckFailureMessage;
use PTS\Bolt\Protocol\Message\InitMessage;
use PTS\Bolt\Protocol\Message\PullAllMessage;
use PTS\Bolt\Protocol\Message\RawMessage;
use PTS\Bolt\Protocol\Message\RunMessage;
use PTS\Bolt\Protocol\Message\V4\PullMessage;
use PTS\Bolt\Protocol\Pipeline;
use PTS\Bolt\Exception\MessageFailureException;
use PTS\Bolt\Result\Result as CypherResult;
use GraphAware\Common\Cypher\Statement;
use GraphAware\Common\Driver\PipelineInterface;
use http\Exception\RuntimeException;
use phpDocumentor\Reflection\Types\Boolean;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Session extends AbstractSession
{
    const PROTOCOL_VERSION = 1;

    /**
     * @var bool
     */
    public $isInitialized = false;

    /**
     * @var Transaction|null
     */
    public $transaction;

    /**
     * @var array
     */
    protected $credentials;

    /**
     * @param AbstractIO $io
     * @param EventDispatcherInterface $dispatcher
     * @param array $credentials
     * @param bool $init
     * @throws \Exception
     */
    public function __construct(
        AbstractIO $io,
        EventDispatcherInterface $dispatcher,
        array $credentials = [],
        $init = true
    ) {
        parent::__construct($io, $dispatcher);
        $this->credentials = $credentials;
        if ($init) {
            $this->init();
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getProtocolVersion()
    {
        return self::PROTOCOL_VERSION;
    }

    /**
     * {@inheritdoc}
     */
    public function run($statement, array $parameters = [], $tag = null)
    {
        if (null === $statement) {
            //throw new BoltInvalidArgumentException("Statement cannot be null");
        }
        $messages = [
            $this->createRunMessage($statement, $parameters),
            $this->createPullAllMessage()
        ];

        $this->sendMessages($messages);

        $runResponse = $this->fetchRunResponse();

        $pullResponse = $this->fetchPullResponse();

        $cypherResult = new CypherResult(Statement::create($statement, $parameters, $tag));
        $cypherResult->setFields($runResponse->getMetadata()[0]->getElements());

        foreach ($pullResponse->getRecords() as $record) {
            $cypherResult->pushRecord($record);
        }

        $pullMeta = $pullResponse->getMetadata();

        if (isset($pullMeta[0])) {
            if (isset($pullMeta[0]->getElements()['stats'])) {
                $cypherResult->setStatistics($pullResponse->getMetadata()[0]->getElements()['stats']);
            } else {
                $cypherResult->setStatistics([]);
            }
        }

        return $cypherResult;
    }

    /**
     * @return Response
     */
    protected function fetchRunResponse()
    {
        $runResponse = new Response();
        $r = $this->unpacker->unpack();

        if ($r->isSuccess()) {
            $runResponse->onSuccess($r);
        } elseif ($r->isFailure()) {
            try {
                $runResponse->onFailure($r);
            } catch (MessageFailureException $e) {
                // server ignores the PULL ALL
                $this->handleIgnore();
                $this->sendMessage(new AckFailureMessage());
                // server success for ACK FAILURE
                $r2 = $this->handleSuccess();
                throw $e;
            }
        }
        return $runResponse;
    }

    /**
     * @return Response
     */
    protected function fetchPullResponse()
    {
        $pullResponse = new Response();

        while (!$pullResponse->isCompleted()) {
            $r = $this->unpacker->unpack();

            if ($r->isRecord()) {
                $pullResponse->onRecord($r);
            }

            if ($r->isSuccess()) {
                $pullResponse->onSuccess($r);
            }

            if ($r->isFailure()) {
                $pullResponse->onFailure($r);
            }
        }
        return $pullResponse;
    }


    protected function createRunMessage($statement, $prams = [])
    {
        return new RunMessage($statement, $prams);
    }

    protected function createPullAllMessage()
    {
        return new PullAllMessage();
    }

    /**
     * {@inheritdoc}
     */
    public function runPipeline(PipelineInterface $pipeline)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function createPipeline($query = null, array $parameters = [], $tag = null)
    {
        return new Pipeline($this);
    }

    /**
     * @throws \Exception
     */
    public function init()
    {
        $this->io->assertConnected();
        $ua = Driver::getUserAgent();
        $this->sendMessage(new InitMessage($ua, $this->credentials));
        $responseMessage = $this->receiveMessage();

        if ($responseMessage->getSignature() != 'SUCCESS') {
            throw new \Exception('Unable to INIT');
        }

        $this->isInitialized = true;
    }

    /**
     * @return \PTS\Bolt\PackStream\Structure\Structure
     */
    public function receiveMessage()
    {
        $bytes = '';

        $chunkHeader = $this->io->read(2);
        list(, $chunkSize) = unpack('n', $chunkHeader);
        $nextChunkLength = $chunkSize;

        do {
            if ($nextChunkLength) {
                $bytes .= $this->io->read($nextChunkLength);
            }

            list(, $next) = unpack('n', $this->io->read(2));
            $nextChunkLength = $next;
        } while ($nextChunkLength > 0);

        $rawMessage = new RawMessage($bytes);
        $message = $this->serializer->deserialize($rawMessage);

        if ($message->getSignature() === 'FAILURE') {
            $msg = sprintf(
                'Neo4j Exception "%s" with code "%s"',
                $message->getElements()['message'],
                $message->getElements()['code']
            );
            $e = new MessageFailureException($msg);
            $e->setStatusCode($message->getElements()['code']);
            $this->sendMessage(new AckFailureMessage());
            throw $e;
        }

        return $message;
    }

    /**
     * @param \PTS\Bolt\Protocol\Message\AbstractMessage $message
     */
    public function sendMessage(AbstractMessage $message)
    {
        $this->sendMessages([$message]);
    }

    /**
     * @param \PTS\Bolt\Protocol\Message\AbstractMessage[] $messages
     */
    public function sendMessages(array $messages)
    {
        foreach ($messages as $message) {
            $this->serializer->serialize($message);
        }

        $this->writer->writeMessages($messages);
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $this->io->close();
        $this->isInitialized = false;
    }

    /**
     * {@inheritdoc}
     */
    public function transaction()
    {
        if ($this->transaction instanceof Transaction) {
            throw new \RuntimeException('A transaction is already bound to this session');
        }

        return new Transaction($this);
    }

    private function handleSuccess()
    {
        return $this->handleMessage('SUCCESS');
    }

    private function handleIgnore()
    {
        $this->handleMessage('IGNORED');
    }

    private function handleMessage($messageType)
    {
        $message = $this->unpacker->unpack();
        if ($messageType !== $message->getSignature()) {
            throw new \RuntimeException(
                sprintf('Expected an %s message, got %s', $messageType, $message->getSignature())
            );
        }

        return $message;
    }

    public function begin()
    {
        throw new \RuntimeException('Bolt protocol V1 does not support transaction messages.');
    }

    public function commit()
    {
        throw new \RuntimeException('Bolt protocol V1 does not support transaction messages.');
    }

    public function rollback()
    {
        throw new \RuntimeException('Bolt protocol V1 does not support transaction messages.');
    }
}
