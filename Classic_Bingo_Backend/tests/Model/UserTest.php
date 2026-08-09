<?php

namespace App\Tests\Models;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PDO;
use PDOStatement;
use PDOException;
use App\Models\User;
use App\Enums\ErrorCode;
use App\Handlers\ErrorCatalog;
use App\Handlers\AppException;
use App\Database\Queries\UserQueries;

/**
 * Comprehensive test suite for the User Model.
 * Tests all database operations and exception handling.
 * 
 * @package App\Tests\Models
 */
class UserTest extends TestCase
{
    /**
     * Mock PDO instance
     * 
     * @var MockObject|PDO
     */
    private MockObject|PDO $pdoMock;

    /**
     * Mock PDOStatement instance
     * 
     * @var MockObject|PDOStatement
     */
    private MockObject|PDOStatement $stmtMock;

    /**
     * User model instance being tested
     * 
     * @var User
     */
    private User $userModel;

    /**
     * Setup method called before each test
     * 
     * @return void
     */
    protected function setUp(): void  {
        // Create mock PDO and PDOStatement
        $this->pdoMock = $this->createMock(PDO::class);
        $this->stmtMock = $this->createMock(PDOStatement::class);
        
        // Instantiate User model with mocked PDO
        $this->userModel = new User($this->pdoMock);
    }

    // ==================================================================================
    // findProfileById() TESTS
    // ==================================================================================

    /**
     * Tests successful user profile retrieval by ID
     * 
     * @return void
     */
    public function testFindProfileByIdReturnsUserWhenFound(): void {
        // Arrange
        $userId = 'test-uuid-123';
        $expectedProfile = [
            'user_id' => $userId,
            'user_name' => 'testuser',
            'role' => 'user'
        ];

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->with(UserQueries::FIND_PROFILE_BY_ID)
            ->willReturn($this->stmtMock);

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->with([$userId])
            ->willReturn(true);

        $this->stmtMock
            ->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($expectedProfile);

        // Act
        $result = $this->userModel->findProfileById($userId);

        // Assert
        $this->assertEquals($expectedProfile, $result);
    }

    /**
     * Tests that null is returned when user is not found
     * 
     * @return void
     */
    public function testFindProfileByIdReturnsNullWhenUserNotFound(): void {
        // Arrange
        $userId = 'non-existent-uuid';

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->with(UserQueries::FIND_PROFILE_BY_ID)
            ->willReturn($this->stmtMock);

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->with([$userId])
            ->willReturn(true);

        $this->stmtMock
            ->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn(false); // PDO returns false when no row found

        // Act
        $result = $this->userModel->findProfileById($userId);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Tests that AppException is thrown when database error occurs
     * 
     * @return void
     */
    public function testFindProfileByIdThrowsAppExceptionOnDatabaseError(): void {
        // Arrange
        $userId = 'test-uuid-123';
        $pdoException = new PDOException('Database connection lost');

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willThrowException($pdoException);

        // Assert
        $this->expectException(AppException::class);
        $this->expectExceptionMessage(ErrorCatalog::get(ErrorCode::INFRA_DATABASE_ERROR)['message']);

        // Act
        $this->userModel->findProfileById($userId);
    }

    // ==================================================================================
    // findByUsername() TESTS
    // ==================================================================================

    /**
     * Tests successful user retrieval by username
     * 
     * @return void
     */
    public function testFindByUsernameReturnsUserWhenFound(): void {
        // Arrange
        $username = 'testuser';
        $expectedUser = [
            'user_id' => 'test-uuid-123',
            'user_name' => $username,
            'role' => 'user',
            'avatar_id' => '1'
        ];

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->with(UserQueries::FIND_BY_USERNAME)
            ->willReturn($this->stmtMock);

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->with([$username])
            ->willReturn(true);

        $this->stmtMock
            ->expects($this->once())
            ->method('fetch')
            ->willReturn($expectedUser);

        // Act
        $result = $this->userModel->findByUsername($username);

        // Assert
        $this->assertEquals($expectedUser, $result);
    }

    /**
     * Tests that null is returned when username doesn't exist
     * 
     * @return void
     */
    public function testFindByUsernameReturnsNullWhenNotFound(): void {
        // Arrange
        $username = 'nonexistent';

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->with(UserQueries::FIND_BY_USERNAME)
            ->willReturn($this->stmtMock);

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->with([$username])
            ->willReturn(true);

        $this->stmtMock
            ->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        // Act
        $result = $this->userModel->findByUsername($username);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Tests that AppException is thrown on database error
     * 
     * @return void
     */
    public function testFindByUsernameThrowsAppExceptionOnDatabaseError(): void {
        // Arrange
        $username = 'testuser';
        $pdoException = new PDOException('Query failed');

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willThrowException($pdoException);

        // Assert
        $this->expectException(AppException::class);
        $this->expectExceptionMessage(ErrorCatalog::get(ErrorCode::INFRA_DATABASE_ERROR)['message']);

        // Act
        $this->userModel->findByUsername($username);
    }

    // ==================================================================================
    // findForAuth() TESTS
    // ==================================================================================

    /**
     * Tests successful retrieval of auth data
     * 
     * @return void
     */
    public function testFindForAuthReturnsAuthDataWhenFound(): void {
        // Arrange
        $userId = 'test-uuid-123';
        $expectedAuthData = [
            'user_id' => $userId,
            'role' => 'user',
            'refresh_token' => 'hashed_token_here'
        ];

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->with(UserQueries::FIND_FOR_AUTH)
            ->willReturn($this->stmtMock);

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->with([$userId])
            ->willReturn(true);

        $this->stmtMock
            ->expects($this->once())
            ->method('fetch')
            ->willReturn($expectedAuthData);

        // Act
        $result = $this->userModel->findForAuth($userId);

        // Assert
        $this->assertEquals($expectedAuthData, $result);
    }

    /**
     * Tests that null is returned when user not found
     * 
     * @return void
     */
    public function testFindForAuthReturnsNullWhenUserNotFound(): void {
        // Arrange
        $userId = 'non-existent-uuid';

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->with(UserQueries::FIND_FOR_AUTH)
            ->willReturn($this->stmtMock);

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->with([$userId])
            ->willReturn(true);

        $this->stmtMock
            ->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        // Act
        $result = $this->userModel->findForAuth($userId);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Tests exception handling for database errors
     * 
     * @return void
     */
    public function testFindForAuthThrowsAppExceptionOnDatabaseError(): void {
        // Arrange
        $userId = 'test-uuid-123';
        $pdoException = new PDOException('Connection timeout');

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willThrowException($pdoException);

        // Assert
        $this->expectException(AppException::class);
        $this->expectExceptionMessage(ErrorCatalog::get(ErrorCode::INFRA_DATABASE_ERROR)['message']);

        // Act
        $this->userModel->findForAuth($userId);
    }

    // ==================================================================================
    // create() TESTS
    // ==================================================================================

    /**
     * Tests successful user creation
     * 
     * @return void
     */
    public function testCreateSuccessfullyCreatesNewUser(): void {
        // Arrange
        $userId = 'new-uuid-456';
        $username = 'newuser';
        $avatarId = '1';
        $hashedToken = 'hashed_refresh_token';
        
        $expectedProfile = [
            'user_id' => $userId,
            'user_name' => $username,
            'role' => 'user'
        ];

        // Mock the INSERT statement
        $this->pdoMock
            ->expects($this->exactly(2)) // prepare called twice: INSERT and SELECT
            ->method('prepare')
            ->willReturnCallback(function($query) {
                if ($query === UserQueries::CREATE) {
                    return $this->stmtMock;
                } elseif ($query === UserQueries::FIND_PROFILE_BY_ID) {
                    $findStmt = $this->createMock(PDOStatement::class);
                    $findStmt->method('execute')->willReturn(true);
                    $findStmt->method('fetch')
                        ->with(PDO::FETCH_ASSOC)
                        ->willReturn([
                            'user_id' => 'new-uuid-456',
                            'user_name' => 'newuser',
                            'role' => 'user'
                        ]);
                    return $findStmt;
                }
                return $this->stmtMock;
            });

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->with([$userId, $username, $avatarId, $hashedToken])
            ->willReturn(true);

        // Act
        $result = $this->userModel->create($userId, $username, $avatarId, $hashedToken);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($userId, $result['user_id']);
        $this->assertEquals($username, $result['user_name']);
    }

    /**
     * Tests that AppException is thrown when username is taken (unique constraint violation)
     * 
     * @return void
     */
    public function testCreateThrowsAppExceptionWhenUsernameIsTaken(): void {
        // Arrange
        $userId = 'new-uuid-456';
        $username = 'existinguser';
        $avatarId = '1';
        $hashedToken = 'hashed_token';
        
        // Create PDOException with SQLSTATE 23000 (integrity constraint violation)
        $pdoException = new PDOException('Duplicate entry');
        // Mock the getCode() method to return '23000'
        // $pdoException = $this->createConfiguredMock(PDOException::class, [
        //     'getCode' => '23000',
        //     'getMessage' => 'Duplicate entry for key users.user_name'
        // ]);

        // Use Reflection to manually set the final 'code' property (the SQLSTATE)
        $reflection = new \ReflectionProperty(PDOException::class, 'code');
        $reflection->setValue($pdoException, '23000');

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->with(UserQueries::CREATE)
            ->willReturn($this->stmtMock);

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->willThrowException($pdoException);

        // Assert
        $this->expectException(AppException::class);
        $this->expectExceptionMessage(ErrorCatalog::get(ErrorCode::AUTH_USERNAME_TAKEN)['message']);

        // Act
        $this->userModel->create($userId, $username, $avatarId, $hashedToken);
    }

    /**
     * Tests that generic database errors throw INFRA_DATABASE_ERROR
     * 
     * @return void
     */
    public function testCreateThrowsAppExceptionOnGenericDatabaseError(): void {
        // Arrange
        $userId = 'new-uuid-456';
        $username = 'newuser';
        $avatarId = '1';
        $hashedToken = 'hashed_token';
        
        $pdoException = new PDOException('General SQL error');

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->with(UserQueries::CREATE)
            ->willReturn($this->stmtMock);

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->willThrowException($pdoException);

        // Assert
        $this->expectException(AppException::class);
        $this->expectExceptionMessage(ErrorCatalog::get(ErrorCode::INFRA_DATABASE_ERROR)['message']);

        // Act
        $this->userModel->create($userId, $username, $avatarId, $hashedToken);
    }

    // ==================================================================================
    // updateRefreshToken() TESTS
    // ==================================================================================

    /**
     * Tests successful refresh token update
     * 
     * @return void
     */
    public function testUpdateRefreshTokenSuccessfullyUpdatesToken(): void {
        // Arrange
        $userId = 'test-uuid-123';
        $newHashedToken = 'new_hashed_token';

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->with(UserQueries::UPDATE_REFRESH_TOKEN)
            ->willReturn($this->stmtMock);

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->with([$newHashedToken, $userId])
            ->willReturn(true);

        // Act
        $result = $this->userModel->updateRefreshToken($userId, $newHashedToken);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Tests that false is returned when update fails
     * 
     * @return void
     */
    public function testUpdateRefreshTokenReturnsFalseOnFailure(): void {
        // Arrange
        $userId = 'test-uuid-123';
        $newHashedToken = 'new_hashed_token';

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->with(UserQueries::UPDATE_REFRESH_TOKEN)
            ->willReturn($this->stmtMock);

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->with([$newHashedToken, $userId])
            ->willReturn(false);

        // Act
        $result = $this->userModel->updateRefreshToken($userId, $newHashedToken);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Tests exception handling for database errors
     * 
     * @return void
     */
    public function testUpdateRefreshTokenThrowsAppExceptionOnDatabaseError(): void {
        // Arrange
        $userId = 'test-uuid-123';
        $newHashedToken = 'new_hashed_token';
        $pdoException = new PDOException('Update failed');

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willThrowException($pdoException);

        // Assert
        $this->expectException(AppException::class);
        $this->expectExceptionMessage(ErrorCatalog::get(ErrorCode::INFRA_DATABASE_ERROR)['message']);

        // Act
        $this->userModel->updateRefreshToken($userId, $newHashedToken);
    }

    // ==================================================================================
    // clearRefreshToken() TESTS
    // ==================================================================================

    /**
     * Tests successful token clearing
     * 
     * @return void
     */
    public function testClearRefreshTokenSuccessfullyClearsToken(): void {
        // Arrange
        $userId = 'test-uuid-123';

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->with(UserQueries::CLEAR_REFRESH_TOKEN)
            ->willReturn($this->stmtMock);

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->with([$userId])
            ->willReturn(true);

        // Act
        $result = $this->userModel->clearRefreshToken($userId);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Tests that false is returned when clearing fails
     * 
     * @return void
     */
    public function testClearRefreshTokenReturnsFalseOnFailure(): void {
        // Arrange
        $userId = 'test-uuid-123';

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->with(UserQueries::CLEAR_REFRESH_TOKEN)
            ->willReturn($this->stmtMock);

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->with([$userId])
            ->willReturn(false);

        // Act
        $result = $this->userModel->clearRefreshToken($userId);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Tests exception handling for database errors
     * 
     * @return void
     */
    public function testClearRefreshTokenThrowsAppExceptionOnDatabaseError(): void {
        // Arrange
        $userId = 'test-uuid-123';
        $pdoException = new PDOException('Clear operation failed');

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willThrowException($pdoException);

        // Assert
        $this->expectException(AppException::class);
        $this->expectExceptionMessage(ErrorCatalog::get(ErrorCode::INFRA_DATABASE_ERROR)['message']);

        // Act
        $this->userModel->clearRefreshToken($userId);
    }
}