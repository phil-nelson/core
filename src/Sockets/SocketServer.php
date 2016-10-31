<?php

namespace PhpIntegrator\Sockets;

use UnexpectedValueException;

use React\EventLoop\LoopInterface;

use React\Socket\Server;
use React\Socket\Connection;

/**
 * Represents a socket server that handles communication with the core.
 */
class SocketServer extends Server
{
    /**
     * @var string
     */
    const HEADER_DELIMITER = "\r\n";

    /**
     * @var array
     */
    protected $request;

    /**
     * @var int
     */
    protected $port;

    /**
     * @param LoopInterface $loop
     * @param int           $port
     */
    public function __construct(LoopInterface $loop, $port)
    {
        parent::__construct($loop);

        $this->port = $port;

        $this->setup();
    }

    /**
     * @return void
     */
    protected function setup()
    {
        $this->on('connection', [$this, 'onConnectionEstablished']);

        $this->listen($this->port);
    }

    /**
     * @return void
     */
    protected function resetRequestState()
    {
        $this->request = [
            'length'           => null,
            'mimeType'         => null,
            'wasBoundaryFound' => false,
            'bytesRead'        => 0,
            'content'          => ''
        ];
    }

    /**
     * @param Connection $connection
     */
    protected function onConnectionEstablished(Connection $connection)
    {
        $this->resetRequestState();

        $connection->on('data', function ($data) use ($connection) {
            $this->onDataReceived($connection, $data);
        });

        $connection->on('end', function () use ($connection) {
            $this->onConnectionEnded($connection);
        });

        $connection->on('close', function () use ($connection) {
            $this->onConnectionClosed($connection);
        });
    }

    /**
     * @param Connection $connection
     * @param string     $data
     */
    protected function onDataReceived(Connection $connection, $data)
    {
        try {
            $this->processConnectionData($connection, $data);
        } catch (RequestParsingException $e) {
            $this->handleRequestParsingException($e);
        }
    }

    /**
     * @param RequestParsingException $e
     */
    protected function handleRequestParsingException(RequestParsingException $e)
    {
        $this->resetRequestState();
    }

    /**
     * @param Connection $connection
     */
    protected function onConnectionEnded(Connection $connection)
    {

    }

    /**
     * @param Connection $connection
     */
    protected function onConnectionClosed(Connection $connection)
    {

    }

    /**
     * @param Connection $connection
     * @param string     $data
     */
    protected function processConnectionData(Connection $connection, $data)
    {
        // TODO: Extract a RequestHandler class.
        // TODO: There could be multiple simultaneous connections sending different requests.

        $bytesRead = 0;

        if ($this->request['length'] === null) {
            $contentLengthHeader = $this->readRawHeader($data);
            $contentLength = $this->getLengthFromContentLengthHeader($contentLengthHeader);

            $this->request['length'] = $contentLength;

            $bytesRead = strlen($contentLengthHeader) + strlen(self::HEADER_DELIMITER);
        } elseif (!$this->request['wasBoundaryFound']) {
            $header = $this->readRawHeader($data);

            if (empty($header)) {
                $this->request['wasBoundaryFound'] = true;
            }

            $bytesRead = strlen($header) + strlen(self::HEADER_DELIMITER);
        } else {
            $bytesToRead = min(strlen($data), $this->request['length'] - $this->request['bytesRead']);

            $this->request['content'] .= substr($data, 0, $bytesToRead);
            $this->request['bytesRead'] += $bytesToRead;

            $bytesRead = $bytesToRead;

            if ($this->request['bytesRead'] == $this->request['length']) {
                $requestContent = json_decode($this->request['content'], true);

                $arguments = $requestContent['params']['parameters'];

                if (!is_array($arguments)) {
                    throw new RequestParsingException('Malformed request content received');
                }

                array_unshift($arguments, __FILE__);

                $stdinStream = null;

                if ($requestContent['params']['stdinData']) {
                    $stdinStream = fopen('php://memory', 'w+');

                    fwrite($stdinStream, $requestContent['params']['stdinData']);
                    rewind($stdinStream);
                }

                // TODO: Refactor.
                // TODO: Don't create application and container over and over. This might pose a problem as currently
                // classes don't count on state being maintained.
                $responseData = (new \PhpIntegrator\UserInterface\Application())->handle($arguments, $stdinStream);

                if ($stdinStream) {
                    fclose($stdinStream);
                }

                $responseContent = [
                    'id'     => $requestContent['id'],
                    'result' => json_decode($responseData, true), // TODO: Commands should not encode, should be handled by outer layer.
                    'error'  => null
                ];

                $responseContent = json_encode($responseContent);

                $connection->write('Content-Length: ' . strlen($responseContent) . self::HEADER_DELIMITER);
                $connection->write(self::HEADER_DELIMITER);
                $connection->write($responseContent);

                $this->resetRequestState();
            }
        }

        $data = substr($data, $bytesRead);

        if (strlen($data) > 0) {
            $this->processConnectionData($connection, $data);
        }
    }

    /**
     * @param string $data
     *
     * @throws RequestParsingException
     *
     * @return string
     */
    protected function readRawHeader($data)
    {
        $end = strpos($data, self::HEADER_DELIMITER);

        if ($end === -1) {
            throw new RequestParsingException('Header delimiter not found');
        }

        return substr($data, 0, $end);
    }

    /**
     * @param string $rawHeader
     *
     * @throws RequestParsingException
     *
     * @return int
     */
    protected function getLengthFromContentLengthHeader($rawHeader)
    {
        $parts = explode(':', $rawHeader, 2);

        list($headerName, $contentLength) = $parts;

        $contentLength = trim($contentLength);

        if (!$contentLength || !is_numeric($contentLength)) {
            throw new RequestParsingException('Content of the Content-Length header is not a valid number');
        }

        return $contentLength;
    }
}