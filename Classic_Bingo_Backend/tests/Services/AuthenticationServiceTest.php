<?php

namespace App\Tests\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use App\Services\AuthenticationService;
use App\Models\User;
use App\Config\JwtConfig;
use App\Constants\UserDataKeys;
use App\Enums\Avatar;
use App\Enums\ErrorCode;
use App\Handlers\AppException;
use App\Handlers\ErrorCatalog;
use App\Tests\DataProviders\AuthDataProvider;
use Exception;

/**
 * Comprehensive test suite for the AuthenticationService.
 * This class tests business logic paths excluding validation (handled by AuthValidator).
 * 
 * @package App\Tests\Services
 */
class AuthenticationServiceTest extends TestCase {
    /**
     * Mock object for the User model to simulate database interactions.
     * 
     * @var MockObject|User
     */
    private MockObject|User $userModelMock;

    /**
     * JWT configuration object used by the authentication service.
     * 
     * @var JwtConfig
     */
    private JwtConfig $jwtConfig;

    /**
     * The authentication service instance being tested.
     * 
     * @var AuthenticationService
     */
    private AuthenticationService $authService;

    /**
     * This method is called before each test execution.
     * It sets up a fresh environment for each test to ensure isolation.
     * 
     * @return void
     */
    protected function setUp(): void {

        // Create a mock object of the User model to simulate database interactions.
        $this->userModelMock = $this->createMock(User::class);
        
        // Create a JWT configuration object with test credentials. These values are used to generate tokens during the authentication process.
        $this->jwtConfig = new JwtConfig([
            'secret' => 'test-secret-key',
            'access_token_expiration' => 3600,
            'refresh_token_expiration' => 86400
        ]);

        // Instantiate the authentication service with our mocked User model dependency.
        $this->authService = new AuthenticationService(
            $this->userModelMock,
            $this->jwtConfig
        );
    }

    // ==================================================================================
    // SIGN UP GUEST USER TESTS- Business Logic Only
    // ==================================================================================

     /**
     * Tests that signup fails when an invalid avatar_id is provided.
     * @return void
     */

      public function testSignUpGuestUserFailsWithInvalidAvatarId(): void {
        // Arrange: Prepare data with an invalid avatar_id (doesn't exist in Avatar enum)
        $invalidData = [
            UserDataKeys::USER_NAME => 'valid_user',
            UserDataKeys::AVATAR_ID => 999, // Invalid avatar ID
        ];

        // Assert: Expect AppException with VALIDATION_AVATAR_INVALID error code
        $this->expectException(AppException::class);
        $this->expectExceptionMessage(ErrorCatalog::get(ErrorCode::VALIDATION_AVATAR_INVALID)['message']);// ErrorCode::VALIDATION_AVATAR_INVALID

        // Act: Attempt to sign up with invalid avatar
        $this->authService->signUpGuestUser($invalidData);
    }


    /**
     * Tests that signup fails when the username is already taken.
     * 
     * Expected: AppException with AUTH_USERNAME_TAKEN error code.
     * 
     * @return void
     */
    public function testSignUpGuestUserFailsWhenUsernameIsTaken(): void {
        // Arrange: Prepare valid input data
        $validData = [
            UserDataKeys::USER_NAME => 'existing_user',
            UserDataKeys::AVATAR_ID => Avatar::default()->value,
        ];
        
        // Configure mock: Username already exists in database
        $this->userModelMock
            ->expects($this->once())
            ->method('findByUsername')
            ->with('existing_user')
            ->willReturn(['user_name' => 'existing_user']);

        // Assert: Expect AppException with AUTH_USERNAME_TAKEN error code
        $this->expectException(AppException::class);
        $this->expectExceptionMessage(ErrorCatalog::get(ErrorCode::AUTH_USERNAME_TAKEN)['message']); // ErrorCode::AUTH_USERNAME_TAKEN

        // Act: Attempt to sign up with taken username
        $this->authService->signUpGuestUser($validData);
    }

    /**
     * Tests successful user creation with valid input data.
     * 
     * @return void
     */
    public function testSignUpGuestUserSucceedsWithValidData(): void {
        // Arrange: Prepare valid input data
        $validData = [
            UserDataKeys::USER_NAME => 'new_user',
            UserDataKeys::AVATAR_ID => Avatar::default()->value,
        ];

        // Expected user record from database
        $createdUserRecord = [
            UserDataKeys::USER_ID => 'test-uuid-12345',
            UserDataKeys::USER_NAME => 'new_user',
            UserDataKeys::USER_ROLE => 'user',
        ];

        // Configure mock: Username is available (returns null)
        $this->userModelMock
            ->expects($this->once())
            ->method('findByUsername')
            ->with('new_user')
            ->willReturn(null);
        
        // Configure mock: User creation succeeds
        $this->userModelMock
            ->expects($this->once())
            ->method('create')
            ->willReturn($createdUserRecord);

        // Act: Call sign-up method
        $result = $this->authService->signUpGuestUser($validData);

        // Assert: Verify successful response structure
        $this->assertEquals(201, $result['status'], 'Expected 201 Created status');
        $this->assertArrayHasKey('accessToken', $result['data'], 'Response should contain access token');
        $this->assertArrayHasKey('user', $result['data'], 'Response should contain user data');
        
        // Verify user data structure
        $this->assertEquals('test-uuid-12345', $result['data']['user'][UserDataKeys::USER_ID]);
        $this->assertEquals('new_user', $result['data']['user'][UserDataKeys::USER_NAME]);
        
        // Verify access token is a non-empty string
        $this->assertIsString($result['data']['accessToken']);
        $this->assertNotEmpty($result['data']['accessToken']);
    }


     /**
     * Tests that the service properly handles database creation failures.
     * 
     * @return void
     */
    public function testSignUpGuestUserThrowsExceptionWhenDatabaseCreateFails(): void {
        // Arrange: Prepare valid input data
        $validData = [
            UserDataKeys::USER_NAME => 'new_user',
            UserDataKeys::AVATAR_ID => Avatar::default()->value,
        ];
        
        // Configure mock: Username is available
        $this->userModelMock
            ->expects($this->once())
            ->method('findByUsername')
            ->willReturn(null);
        
        // Configure mock: Database creation fails (returns null)
        $this->userModelMock
            ->expects($this->once())
            ->method('create')
            ->willReturn(null);

        // Assert: Expect Exception for database failure
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Failed to create user account.");

        // Act: Attempt to create user
        $this->authService->signUpGuestUser($validData);
    }

    // ==================================================================================
    // 2 ::  LOGIN GUEST USER TESTS
    // ==================================================================================

     /**
     * Tests the business rule where a user attempts to log in with a user_id
     * that does not exist in the database.
     * Expected: Throws AppException with RESOURCE_USER_NOT_FOUND.
     */
    public function testLoginGuestUserFailsWhenUserNotFound(): void {
        // Arrange: Prepare data for a user that doesn't exist.
        $nonExistentUserId = 'non-existent-uuid-404';
        $loginData = [UserDataKeys::USER_ID => $nonExistentUserId];
        
        // Configure the mock: findForAuth returns null, simulating user not found.
        $this->userModelMock->method('findForAuth')
            ->with($nonExistentUserId)
            ->willReturn(null);

        // Assert: Expect our custom AppException to be thrown.
        $this->expectException(AppException::class);

        try {
            // Act: Attempt to log in with the non-existent user ID.
            $this->authService->loginGuestUser($loginData);
        } catch (AppException $e) {
            // Assert: Verify that the correct error code is set on the exception.
            $this->assertSame(ErrorCode::RESOURCE_USER_NOT_FOUND, $e->errorCode);
            // Re-throw to satisfy the expectException assertion.
            throw $e;
        }
    }

    /**
     * Tests the successful login path for an existing user.
     * Verifies that dependencies are called and the correct response is returned.
     */
    public function testLoginGuestUserSucceedsWithValidUserId(): void {
        // Arrange: Define an existing user's ID and the record to be returned by the model.
        $existingUserId = 'existing-uuid-123';
        $loginData = [UserDataKeys::USER_ID => $existingUserId];
        
        $foundUserRecord = [
            UserDataKeys::USER_ID => $existingUserId,
            UserDataKeys::USER_ROLE => 'user'
        ];

        // Configure mock: findForAuth returns the user record.
        $this->userModelMock->method('findForAuth')
            ->with($existingUserId)
            ->willReturn($foundUserRecord);
        
        // Configure mock: Expect the refresh token to be updated exactly once.
        $this->userModelMock->expects($this->once())
            ->method('updateRefreshToken')
            ->with($this->equalTo($existingUserId), $this->isType('string'));

        // Act: Call the login method.
        $result = $this->authService->loginGuestUser($loginData);

        // Assert: Verify the response is correct for a successful login.
        $this->assertEquals(200, $result['status']);
        $this->assertArrayHasKey('accessToken', $result['data']);
        $this->assertIsString($result['data']['accessToken']);
        $this->assertNotEmpty($result['data']['accessToken']);
    }
}