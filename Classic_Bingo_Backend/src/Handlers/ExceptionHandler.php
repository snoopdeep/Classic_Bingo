<?php 

namespace App\Handlers; 

use App\Utils\Logger; 
use App\Utils\Response;
use App\Utils\UUIDGenerator;
use App\Enums\ErrorCode;
use App\Constants\ErrorResponseKeys;
use Throwable;

/**
 * The global exception handler for the entire application.
 *
 * This class catches all uncaught Throwables, logs them with a unique correlation ID,
 * and sends a standardized, safe JSON response to the client.
 */
class ExceptionHandler {

    private const STATUS = 'status';
    /**
     * The global handler for all uncaught exceptions and errors.
     *
     * It is registered via `set_exception_handler()` in the application's entry point.
     *
     * @param Throwable $exception The exception or error that was thrown.
     * @return void This method never returns as it terminates script execution via `Response::json()`.
     */
    public static function handle(Throwable $exception): void {
        // Generate a unique ID to link this specific error event in logs and client responses.
        $correlationId = UUIDGenerator::generate();

        if ($exception instanceof AppException) {
            // --- Path for KNOWN, controlled application errors ---
            $errorCode = $exception->errorCode;
            $errorMeta = ErrorCatalog::get($errorCode);
            // Use the HTTP status code defined in errors.json (e.g., 400, 404, 409).
            $httpStatus = $errorMeta[self::STATUS];
            
            // Log the full details for internal debugging, including any custom parameters.
            Logger::error("Controlled application error occurred.", [
                ErrorResponseKeys::CORRELATION_ID => $correlationId,
                ErrorResponseKeys::ERROR_CODE => $errorCode->value,
                ErrorResponseKeys::ERROR_MESSAGE => $exception->getMessage(),
                ErrorResponseKeys::ERROR_FILE => $exception->getFile(),
                ErrorResponseKeys::ERROR_LINE => $exception->getLine(),
                ErrorResponseKeys::EXCEPTION_PARAMS => $exception->params
            ]);
            
            // Send a clean, coded, and safe response to the client.
            // Only the error code and correlation ID are exposed.
            Response::json([
                ErrorResponseKeys::ERROR => [
                    ErrorResponseKeys::ERROR_CODE => $errorCode->value,
                    ErrorResponseKeys::CORRELATION_ID  => $correlationId,
                    ErrorResponseKeys::EXCEPTION_PARAMS => $exception->params // Optionally include safe params if needed by the client.
                ]
            ], $httpStatus);

        } else {
            // --- Path for UNEXPECTED, critical system errors ---
            $httpStatus = 500;
            $errorCode = ErrorCode::INFRA_UNEXPECTED_ERROR;
            
            // Log the full, original exception details for debugging. This is crucial.
            Logger::error("Unexpected system error occurred.", [
                ErrorResponseKeys::CORRELATION_ID  => $correlationId,
                ErrorResponseKeys::ERROR_CODE => $errorCode->value,
                ErrorResponseKeys::ERROR_CLASS => get_class($exception),
                ErrorResponseKeys::ERROR_MESSAGE => $exception->getMessage(),
                ErrorResponseKeys::ERROR_FILE => $exception->getFile(),
                ErrorResponseKeys::ERROR_LINE => $exception->getLine(),
            ]);

            // Send a generic, coded, and safe response to the client.
            // NEVER expose the original error message or stack trace.
            Response::json([
                ErrorResponseKeys::ERROR => [
                    ErrorResponseKeys::ERROR_CODE => $errorCode->value,
                    ErrorResponseKeys::ERROR_MESSAGE => 'An internal server error occurred. Please contact support and provide the correlation ID.',
                    ErrorResponseKeys::CORRELATION_ID  => $correlationId
                ]
            ], $httpStatus);
        }
    }
}