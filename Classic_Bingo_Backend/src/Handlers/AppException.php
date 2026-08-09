<?php

namespace App\Handlers;

use App\Enums\ErrorCode;
use App\Constants\GameEntitiesConstants;
use Exception;
use Throwable;

/**
 * A custom exception for handling all "known" application errors.
 * It carries a specific ErrorCode to be used by the global ExceptionHandler.
 */
class AppException extends Exception {   
    /**
     * The specific error code enum that represents this exception.
     * @var ErrorCode
     */
    public readonly ErrorCode $errorCode;
    /**
     * An optional array of parameters providing additional context about the error.
     * @var array<string, mixed>
     */
    public readonly array $params;

    /**
     * Constructs the AppException.
     *
     * @param ErrorCode $errorCode The enum case representing the specific error type.
     * @param array<string, mixed> $params Optional key-value pairs for additional error context.
     * @param ?Throwable $previous The previous throwable used for exception chaining.
     */
    public function __construct(ErrorCode $errorCode, array $params = [], ?Throwable $previous = null)
    {
        $this->errorCode = $errorCode;
        $this->params = $params;

        $errorDetails = ErrorCatalog::get($errorCode);
        
        parent::__construct($errorDetails[GameEntitiesConstants::MESSAGE], $errorDetails[GameEntitiesConstants::STATUS], $previous);
    }

    /**
     * Public method to retrieve the error details (parameters/context).
     * This is required by your unit tests.
     * * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->params;
    }
}
