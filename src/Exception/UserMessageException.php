<?php

namespace SimpleComplex\Utils\Exception;

/**
 * Generic runtime exception which can accommodate a non-sensitive message
 * to end user.
 *
 * Free to use within other packages.
 *
 * @package SimpleComplex\Utils
 */
class UserMessageException extends \RuntimeException
{
    /**
     * @var string
     */
    protected $userMessage;

    /**
     * @var array
     */
    protected $failures;

    /**
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     * @param string|null $userMessage
     *      Also used as fallback for empty arg $message; counter laziness.
     */
    public function __construct(
        $message = '', $code = 0, \Throwable $previous = null,
        string $userMessage = null
    ) {
        parent::__construct($message ? $message : ($userMessage ?? ''), $code, $previous);
        $this->userMessage = $userMessage;
    }

    /**
     * @return string
     */
    public function getUserMessage() : string
    {
        return $this->userMessage ?? '';
    }
}
