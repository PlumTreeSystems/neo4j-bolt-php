<?php

/*
 * This file is part of the GraphAware Bolt package.
 *
 * (c) Graph Aware Limited <http://graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PTS\Bolt\IO;

use PTS\Bolt\Configuration;
use PTS\Bolt\Exception\IOException;
use PTS\Bolt\Misc\Helper;
use Symfony\Component\EventDispatcher\EventDispatcher;

class StreamSocket extends AbstractIO
{
    /**
     * @var string
     */
    protected $protocol;

    /**
     * @var string
     */
    protected $host;

    /**
     * @var int
     */
    protected $port;

    /**
     * @var array|null
     */
    protected $context;

    /**
     * @var bool
     */
    protected $keepAlive;

    /**
     * @var null|EventDispatcher
     */
    protected $eventDispatcher;

    /**
     * @var int
     */
    protected $timeout = 5;

    /**
     * @var resource|null
     */
    private $sock;

    private $configuration;

    /**
     * @var null|float
     */
    private $lastRead = null;

    /**
     * @param string $host
     * @param int $port
     * @param array|null $context
     * @param bool $keepAlive
     * @param EventDispatcher|null $eventDispatcher
     * @param Configuration|null $configuration
     */
    public function __construct(
        $host,
        $port,
        $context = null,
        $keepAlive = false,
        EventDispatcher $eventDispatcher = null,
        Configuration $configuration = null
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->context = $context;
        $this->keepAlive = $keepAlive;
        $this->eventDispatcher = $eventDispatcher;
        $this->protocol = 'tcp';

        $this->context = null !== $context ? $context : stream_context_create();
        $this->configuration = $configuration;

        /*
        if (is_null($this->context)) {
            $this->context = stream_context_create();
        } else {
            $this->protocol = 'ssl';
        }
        */
        //stream_set_blocking($this->sock, false);
    }

    public static function withConfiguration(
        $host,
        $port,
        Configuration $configuration,
        EventDispatcher $eventDispatcher = null
    ) {
        $context = null;
        $bindTo = $configuration->getValue('bind_to_interface');
        if (null !== $bindTo && 'null' !== $bindTo) {
            $context = stream_context_create([
                'socket' => [
                    'bindto' => $bindTo
                ]
            ]);
        }

        return new self($host, $port, $context, false, $eventDispatcher, $configuration);
    }

    /**
     * {@inheritdoc}
     */
    public function write($data)
    {
        //echo \PTS\Bolt\Misc\Helper::prettyHex($data) . PHP_EOL;
        $this->assertConnected();
        $written = 0;
        $len = mb_strlen($data, 'ASCII');

        while ($written < $len) {
            $buf = fwrite($this->sock, $data);

            if ($buf === false) {
                throw new IOException('Error writing data');
            }

            if ($buf === 0 && feof($this->sock)) {
                throw new IOException('Broken pipe or closed connection');
            }

            $written += $buf;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function read($n)
    {
        if (null === $n) {
            return $this->readAll();
        }
        $this->assertConnected();
        $read = 0;
        $data = '';

        while ($read < $n) {
            $buffer = fread($this->sock, ($n - $read));
            //var_dump(\PTS\Bolt\Misc\Helper::prettyHex($buffer));
            // check '' later for non-blocking mode use case
            if ($buffer === false || '' === $buffer) {
                throw new IOException('Error receiving data');
            }

            $read += mb_strlen($buffer, 'ASCII');
            $data .= $buffer;
        }

        return $data;
    }

    /**
     * @param int $l
     *
     * @return string
     */
    public function readChunk($l = 8192)
    {
        $data = stream_socket_recvfrom($this->sock, $l);
        if (empty($data)) {
            if ($this->lastRead === null) {
                $this->lastRead = microtime(true);
            } else {
                $timeSinceLastRead = microtime(true) - $this->lastRead;
                // 5 minutes
                if ($timeSinceLastRead > 60 * 5) {
                    throw new IOException('Timeout reached');
                }
            }
        } else {
            $this->lastRead = null;
        }
        //echo Helper::prettyHex($data);

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function select($sec, $usec)
    {
        $r = [$this->sock];
        $w = $e = null;
        $result = stream_select($r, $w, $e, $sec, $usec);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        $errstr = $errno = null;

        $remote = sprintf(
            '%s://%s:%s',
            $this->protocol,
            $this->host,
            $this->port
        );

        $this->sock = stream_socket_client(
            $remote,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $this->context
        );

        if (false === $this->sock) {
            throw new IOException(sprintf(
                'Error to connect to the server(%s) :  "%s"',
                $errno,
                $errstr
            ));
        }

        if ($this->shouldEnableCrypto()) {
            $result = stream_socket_enable_crypto(
                $this->sock,
                true,
                STREAM_CRYPTO_METHOD_SSLv23_CLIENT
            );
            if (true !== $result) {
                throw new \RuntimeException(sprintf('Unable to enable crypto on socket'));
            }
        }

        stream_set_read_buffer($this->sock, 0);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        if (is_resource($this->sock)) {
            fclose($this->sock);
        }

        $this->sock = null;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function reconnect()
    {
        $this->close();

        return $this->connect();
    }

    /**
     * {@inheritdoc}
     */
    public function isConnected()
    {
        return is_resource($this->sock);
    }

    /**
     * @return string
     */
    private function readAll()
    {
        stream_set_blocking($this->sock, false);
        $r = [$this->sock];
        $w = $e = [];
        $data = '';
        $continue = true;

        while ($continue) {
            $select = stream_select($r, $w, $e, 0, 10000);

            if (0 === $select) {
                stream_set_blocking($this->sock, true);

                return $data;
            }

            $buffer = stream_get_contents($this->sock, 8192);

            if ($buffer === '') {
                stream_select($r, $w, $e, null, null);
            }

            $r = [$this->sock];
            $data .= $buffer;
        }

        return $data;
    }

    public function shouldEnableCrypto()
    {
        if (null !== $this->configuration
            && $this->configuration->getValue('tls_mode') === Configuration::TLSMODE_REQUIRED
        ) {
            return true;
        }

        return false;
    }
}
