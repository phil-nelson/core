<?php

namespace PhpIntegrator\Sockets;

/**
 * An enumeration of JSON-RPC 2.0 error codes.
 */
class JsonRpcErrorCode
{
    /**
     * @var int
     */
    const PARSE_ERROR        = -32700;

    /**
     * @var int
     */
    const INVALID_REQUEST    = -32600;

    /**
     * @var int
     */
    const METHOD_NOT_FOUND   = -32601;

    /**
     * @var int
     */
    const INVALID_PARAMS     = -32602;

    /**
     * @var int
     */
    const INTERNAL_ERROR     = -32603;

    /**
     * Indicates that a fatal error occurred in the srever.
     *
     * This is an error of the same origin as a LogicException in PHP. In other words, this was an exception that
     * indicates there is some sort of bug or problem in the server itself that should never have happened.
     *
     * @var int
     */
    const FATAL_SERVER_ERROR = -32000;

    /**
     * Indicates that a runtime error occurred in the server.
     *
     * This acts much like PHP's RuntimeException, i.e. this is an exception that does not indicate something went
     * horribly wrong, the execution of something has simply ceased because of an error condition.
     *
     * @var int
     */
    const RUNTIME_ERROR      = -32001;
}
