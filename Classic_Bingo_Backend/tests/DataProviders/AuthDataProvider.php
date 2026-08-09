<?php

namespace App\Tests\DataProviders;

use App\Constants\UserDataKeys;
use App\Enums\Avatar;
use App\Enums\ErrorCode;

class AuthDataProvider
{
    /**
     * Provides datasets for various invalid input scenarios using Error Codes.
     * Each dataset contains:
     * 1. The input data array for the service method.
     * 2. The expected HTTP status code (e.g., 400 for Bad Request).
     * 3. The expected ErrorCode enum value in the JSON response.
     */
    public static function invalidInputProvider(): array {
        return [
            'missing username' => [
                'data' => [UserDataKeys::AVATAR_ID => Avatar::default()->value],
                'expectedStatus' => 400,
                'expectedErrorCode' => ErrorCode::VALIDATION_MISSING_FIELDS->value
            ],
            'missing avatar_id' => [
                'data' => [UserDataKeys::USER_NAME => 'guest123'],
                'expectedStatus' => 400,
                'expectedErrorCode' => ErrorCode::VALIDATION_MISSING_FIELDS->value
            ],
            'username too short' => [
                'data' => [UserDataKeys::USER_NAME => 'ab', UserDataKeys::AVATAR_ID => Avatar::default()->value],
                'expectedStatus' => 400,
                'expectedErrorCode' => ErrorCode::VALIDATION_USERNAME_INVALID_FORMAT->value
            ],
            'username too long' => [
                'data' => [UserDataKeys::USER_NAME => 'a_very_long_username_that_exceeds_the_limit', UserDataKeys::AVATAR_ID => Avatar::default()->value],
                'expectedStatus' => 400,
                'expectedErrorCode' => ErrorCode::VALIDATION_USERNAME_INVALID_FORMAT->value
            ],
            'username with invalid characters' => [
                'data' => [UserDataKeys::USER_NAME => 'guest-123!', UserDataKeys::AVATAR_ID => Avatar::default()->value],
                'expectedStatus' => 400,
                'expectedErrorCode' => ErrorCode::VALIDATION_USERNAME_INVALID_FORMAT->value
            ],
            'invalid avatar_id' => [
                'data' => [UserDataKeys::USER_NAME => 'guest123', UserDataKeys::AVATAR_ID => 'non-existent-avatar'],
                'expectedStatus' => 400,
                'expectedErrorCode' => ErrorCode::VALIDATION_AVATAR_INVALID->value
            ],
        ];
    }
}
