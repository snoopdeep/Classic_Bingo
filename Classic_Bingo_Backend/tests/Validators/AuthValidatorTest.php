<?php

namespace Tests\Validators;

use App\Constants\UserDataKeys;
use App\Enums\ErrorCode;
use App\Handlers\AppException;
use App\Handlers\ErrorCatalog; 
use App\Validators\AuthValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuthValidator::class)]
class AuthValidatorTest extends TestCase {


    //========================================================================
    // validateSignUpInput Tests
    //========================================================================

    #[Test] #attribute tells PHPUnit to run this method as a test. 
    #[DataProvider('provideInvalidSignUpData')]
    public function validateSignUpInputThrowsExceptionForInvalidData(array $data, ErrorCode $expectedErrorCode): void {
     
        $expectedMessage = ErrorCatalog::get($expectedErrorCode)['message'];

        $this->expectException(AppException::class); # PHPUnit assertion.
        $this->expectExceptionMessage($expectedMessage); # assertion

        AuthValidator::validateSignUpInput($data); #action
    }

    public static function provideInvalidSignUpData(): array {
        return [
            'missing username' => [[UserDataKeys::AVATAR_ID => 'avatar1'], ErrorCode::VALIDATION_MISSING_FIELDS],
            'missing avatar_id' => [[UserDataKeys::USER_NAME => 'test_user'], ErrorCode::VALIDATION_MISSING_FIELDS],
            'empty username' => [[UserDataKeys::USER_NAME => '', UserDataKeys::AVATAR_ID => 'avatar1'], ErrorCode::VALIDATION_MISSING_FIELDS],
            'empty avatar_id' => [[UserDataKeys::USER_NAME => 'test_user', UserDataKeys::AVATAR_ID => ''], ErrorCode::VALIDATION_MISSING_FIELDS],
            'username too short' => [[UserDataKeys::USER_NAME => 'ab', UserDataKeys::AVATAR_ID => 'avatar1'], ErrorCode::VALIDATION_USERNAME_INVALID_FORMAT],
            'username too long' => [[UserDataKeys::USER_NAME => 'a_very_long_username_that_is_invalid', UserDataKeys::AVATAR_ID => 'avatar1'], ErrorCode::VALIDATION_USERNAME_INVALID_FORMAT],
            'username with invalid characters' => [[UserDataKeys::USER_NAME => 'user-name!', UserDataKeys::AVATAR_ID => 'avatar1'], ErrorCode::VALIDATION_USERNAME_INVALID_FORMAT],
        ];
    }

    #[Test]
    public function validateSignUpInputSucceedsWithValidData(): void  {
        $data = [
            UserDataKeys::USER_NAME => 'valid_user123',
            UserDataKeys::AVATAR_ID => 'avatar_id_1'
        ];

        AuthValidator::validateSignUpInput($data);
        $this->assertTrue(true);
    }

    //========================================================================
    // sanitizeSignUpInput Tests
    //========================================================================

    #[Test]
    public function sanitizeSignUpInputTrimsWhitespace(): void {
        $data = [
            UserDataKeys::USER_NAME => '  user_with_spaces  ',
            UserDataKeys::AVATAR_ID => '  avatar_id_with_spaces  '
        ];
        $expected = [
            UserDataKeys::USER_NAME => 'user_with_spaces',
            UserDataKeys::AVATAR_ID => 'avatar_id_with_spaces'
        ];
        $this->assertEquals($expected, AuthValidator::sanitizeSignUpInput($data));
    }

    //========================================================================
    // validateUserId Tests
    //========================================================================

    #[Test]
    public function validateUserIdThrowsExceptionForMissingUserId(): void  {
        $expectedMessage = ErrorCatalog::get(ErrorCode::VALIDATION_USER_ID_MISSING)['message'];

        $this->expectException(AppException::class); # assertion
        $this->expectExceptionMessage($expectedMessage); #assertion

        AuthValidator::validateUserId([]);
    }

    #[Test]
    public function validateUserIdSucceedsWithValidData(): void  {
        $data = [UserDataKeys::USER_ID => 'some-uuid-string'];
        AuthValidator::validateUserId($data);
        $this->assertTrue(true);
    }

    //========================================================================
    // sanitizeUserId Tests
    //========================================================================

    #[Test]
    public function sanitizeUserIdTrimsWhitespace(): void {
        $data = [UserDataKeys::USER_ID => '  some-uuid-string  '];
        $expected = [UserDataKeys::USER_ID => 'some-uuid-string'];
        $this->assertEquals($expected, AuthValidator::sanitizeUserId($data));
    }

    //========================================================================
    // validateUserId Tests
    //========================================================================

    #[Test]
    #[DataProvider('provideInvalidUserIds')]
    public function validateUserIdThrowsExceptionForInvalidId(string $userId): void  {
       
        $expectedMessage = ErrorCatalog::get(ErrorCode::VALIDATION_USER_ID_MISSING)['message'];

        $this->expectException(AppException::class);
        $this->expectExceptionMessage($expectedMessage);

        AuthValidator::validateUserId($userId);
    }

    public static function provideInvalidUserIds(): array {
        return [
            'empty string' => [''],
            'whitespace only' => ['   '],
        ];
    }

    #[Test]
    public function validateUserIdSucceedsWithValidId(): void {
        AuthValidator::validateUserId('some-valid-id');
        $this->assertTrue(true);
    }

    //========================================================================
    // sanitizeUserId Tests
    //========================================================================

    #[Test]
    public function sanitizeUserIdTrimsWhitespace(): void  {
        $this->assertEquals('some-valid-id', AuthValidator::sanitizeUserId('  some-valid-id  '));
    }
}
