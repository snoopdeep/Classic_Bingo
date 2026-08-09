<?php

namespace App\Handlers;

use App\Enums\ErrorCode;
use App\Constants\UserDataKeys;
use RuntimeException;

/**
 * A central, static catalog that loads and provides error definitions from errors.json.
 *
 * This final class acts as a single source of truth for error messages and HTTP
 * status codes. It lazy-loads and caches the data to prevent repeated file access
 * during a single request.
 */
final class ErrorCatalog
{
    /**
     * The in-memory cache for the error map, indexed by error code.
     * @var array<string, array{code: string, status: int, message: string}>|null
     */
    private static ?array $errorMap = null;
    private const CODE_INDEX_KEY = 'code';

    /**
     * The path to the JSON file containing error definitions.
     * @var string
     */
    private const ERROR_FILE_PATH = __DIR__ . '/../Resources/errors.json';

    /**
     * Private constructor to prevent instantiation of this static utility class.
     */
    private function __construct() {}

     /**
     * Loads and indexes the error definitions from the JSON file.
     *
     * This method is called internally on the first `get()` call and only runs once.
     * It populates the static `$errorMap` property for fast lookups.
     *
     * @return void
     * @throws RuntimeException If the error definition file is missing or contains invalid JSON.
     */
    private static function load(): void
    {
        // If the map is already populated, do nothing.
        if (self::$errorMap !== null) {
            return; 
        }

        if (!file_exists(self::ERROR_FILE_PATH)) {
            // This is a catastrophic failure; the app can't run without its error definitions.
            throw new RuntimeException("Error definition file not found at " . self::ERROR_FILE_PATH);
        }

        $json = file_get_contents(self::ERROR_FILE_PATH);
        // error_log(print_r($json));
        $errors = json_decode($json, true);
        // error_log(print_r($errors));

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Failed to parse errors.json: " . json_last_error_msg());
        }

        // Index the array by the 'code' key for fast O(1) lookups later.
        // The second parameter `null` means the entire original sub-array is used as the value.
        self::$errorMap = array_column($errors, null, self::CODE_INDEX_KEY);
    }

    /**
     * Retrieves the metadata (HTTP status, message) for a given ErrorCode.
     *
     * @param ErrorCode $code The enum case representing the desired error.
     * @return array{status: int, message: string} An associative array with the error details.
     */
    public static function get(ErrorCode $code): array{
        // Ensure the error map is loaded before trying to access it (lazy loading).
        self::load();
        
        // Return the specific error details if found.
        if (isset(self::$errorMap[$code->value])) {
            return self::$errorMap[$code->value]; // an array of message and http code corresponding to the value. 
        }

        // Use a hardcoded, generic "unexpected error" as a robust fallback.
        // This prevents a failure even if the default error is missing from the JSON file.
        return [
            'status' => 500,
            'message' => 'An unexpected internal error occurred.'
        ];
    }
}