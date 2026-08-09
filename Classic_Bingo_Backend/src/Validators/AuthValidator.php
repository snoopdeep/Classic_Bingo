<?php

namespace App\Validators;

use App\Constants\UserDataKeys;
use App\Enums\ErrorCode;
use App\Handlers\AppException;

/**
 * AuthValidator - Pure input validation
 * 
 */
class AuthValidator {


    /**
     * Ensures all required fields for sign-up are present.
     * * @param array<string, mixed> $data Raw input data
     * @return void
     * @throws AppException If fields are missing
     */
    public static function validateSignUpRequiredFields(array $data): void {
        if (empty($data[UserDataKeys::USER_NAME]) || empty($data[UserDataKeys::AVATAR_ID])) {
            throw new AppException(ErrorCode::VALIDATION_MISSING_FIELDS);
        }
    }

    /**
     * Validates the format of the username.
     * * @param string $username The username string (should be trimmed already).
     * @return void
     * @throws AppException If the format is invalid
     */
    public static function validateUsernameFormat(string $username): void {
        // 3-16 characters, alphanumeric and underscores only
        if (!preg_match('/^[a-zA-Z0-9_]{3,16}$/', $username)) {
            throw new AppException(ErrorCode::VALIDATION_USERNAME_INVALID_FORMAT);
        }
    }

    /**
     * Sanitizes and validates all sign-up input data.
     * * This is a convenient function that chains input checks and returns clean data.
     *
     * @param array<string, mixed> $data Raw input data
     * @return array<string, string> Sanitized data
     * @throws AppException If validation fails
     */
    public static function sanitizeAndValidateSignUpInput(array $data): array {
        // 1. Check for required fields
        self::validateSignUpRequiredFields($data);

        // 2. Sanitize/Trim the inputs
        $sanitized = [
            UserDataKeys::USER_NAME => trim((string)$data[UserDataKeys::USER_NAME]),
            UserDataKeys::AVATAR_ID => trim((string)$data[UserDataKeys::AVATAR_ID])
        ];
        
        // 3. Validate the format of the sanitized inputs
        self::validateUsernameFormat($sanitized[UserDataKeys::USER_NAME]);
        
        return $sanitized;
    }

    /**
     * Validate user ID format.
     * 
     * Ensures the user ID is not empty after trimming.
     *
     * @param string $userId User ID to validate
     * @return void
     * @throws AppException If validation fails
     */
    public static function validateUserId(string $userId): void {
        $userId = trim($userId);
        if (empty($userId)) {
            throw new AppException(ErrorCode::VALIDATION_USER_ID_MISSING);
        }
    }

    /**
     * Sanitize user ID.
     *
     * @param string $userId Raw user ID
     * @return string Sanitized user ID
     */
    public static function sanitizeUserId(string $userId): string {
        return trim($userId);
    }
}