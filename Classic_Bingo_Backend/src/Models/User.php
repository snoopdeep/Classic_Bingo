<?php
namespace App\Models;

use PDOException;
use App\Database\Queries\UserQueries;
use App\Database\Database;
use App\Enums\ErrorCode;
use App\Handlers\AppException;
use Exception;
/**
 * User Model - Data Mapper for the 'users' table.
 *
 * This class is responsible for all database operations related to users.
 */
class User {

    /**
     * The Database wrapper instance, providing PDO and transaction methods.
     * @var Database
     */
    private Database $dbAccess; 

    /**
     * Injects the Database wrapper dependency.
     *
     * @param Database $dbAccess The Database wrapper instance.
     */
    public function __construct(Database $dbAccess) {
        $this->dbAccess = $dbAccess;
    }

    /**
     * Fetches a user's public profile data by their unique ID.
     *
     * @param string $userId The UUID of the user to find.
     * @return array<string, mixed>|null An associative array containing the user's profile (`user_id`, `user_name`, `role`) or null if not found.
     * @throws AppException
     */
    public function findProfileById(string $userId): ?array {
        try{
            // The fetchOne method in the wrapper throws PDOException on failure.
            $result = $this->dbAccess->fetchOne(UserQueries::FIND_PROFILE_BY_ID, [$userId]);
            return $result ?: null;
        }catch(PDOException $e){
            // Correctly catching PDOException and wrapping it.
            throw new AppException(ErrorCode::INFRA_DATABASE_ERROR, [], $e);
        }
    }

    /**
     * Finds a user by their unique userName.
     *
     * @param string $userName The userName to search for.
     * @return array<string, mixed>|null An associative array of the user's data or null if a user with that name does not exist.
     * @throws AppException
     */
    public function findByUsername(string $userName): ?array {
        try {
            $result = $this->dbAccess->fetchOne(UserQueries::FIND_BY_USERNAME, [$userName]);
            return $result ?: null;
        } catch (PDOException $e) {
            throw new AppException(ErrorCode::INFRA_DATABASE_ERROR, [], $e);
        }
    }

    /**
     * Fetches accessToken of a user required for authentication.
     * For internal user only
     * * @param string $userId The UUID of the user.
     * @return array<string, mixed>|null An associative array of the user's auth data or null if not found.
     * @throws AppException
     */
    public function findForAuth(string $userId): ?array {
        try {
            $result = $this->dbAccess->fetchOne(UserQueries::FIND_FOR_AUTH, [$userId]);
            return $result ?: null;
        } catch (PDOException $e) {
            throw new AppException(ErrorCode::INFRA_DATABASE_ERROR, [], $e);
        }
    }

    /**
     * Creates a new user record along with initial wallet and stats in a single atomic transaction.
     *
     * @param string $userId The new user's UUID.
     * @param string $username The new user's chosen username.
     * @param string $avatarId The ID of the user's chosen avatar.
     * @param string $hashedRefreshToken The bcrypt hash of the initial refresh token.
     * @return array<string, mixed>|null The newly created user's profile data upon success.
     * @throws AppException
     */
    public function create(string $userId, string $username, string $avatarId, string $hashedRefreshToken): ?array {
        try {
            // 1. START TRANSACTION (Can throw PDOException)
            $this->dbAccess->beginTransaction();

            // A: Insert into USERS table (Can throw PDOException)
            $this->dbAccess->execute(
                UserQueries::CREATE_USER, 
                [$userId, $username, $avatarId, $hashedRefreshToken]
            );

            // B: Insert into USER_WALLET table
            $this->dbAccess->execute(UserQueries::CREATE_WALLET, [$userId]);

            // C: Insert into USER_STAT table
            $this->dbAccess->execute(UserQueries::CREATE_STAT, [$userId]);

            // 2. COMMIT TRANSACTION (Can throw PDOException)
            $this->dbAccess->commit();

            // 3. Read back the new profile data (findProfileById handles its own exceptions)
            return $this->findProfileById($userId);

        } catch (PDOException $e) {
            // Rollback is correctly called in the exception block.
            $this->dbAccess->rollback(); 

            // Check for unique constraint violation on username (SQLSTATE '23000')
            if ($e->getCode() === '23000') {
                throw new AppException(ErrorCode::AUTH_USERNAME_TAKEN, ['username' => $username], $e);
            }

            // For all other database errors, throw a generic error.
            throw new AppException(ErrorCode::INFRA_DATABASE_ERROR, [], $e);

        } catch (Exception $e) {
             // Catching generic non-PDO errors during transaction/rollback
            $this->dbAccess->rollback();
            throw new AppException(ErrorCode::INFRA_UNEXPECTED_ERROR, [], $e);
        }
    }

    /**
     * Updates the stored refresh token hash for a specific user.
     * Used during login and token refresh operations.
     *
     * @param string $userId The ID of the user to update.
     * @param string $hashedRefreshToken The new hashed refresh token.
     * @return bool True on successful update (one row affected), false on failure (zero or more than one row affected).
     * @throws AppException
     */
    public function updateRefreshToken(string $userId, string $hashedRefreshToken): bool {
        try {
            // Use the wrapper's execute method
            $affectedRows = $this->dbAccess->execute(
                UserQueries::UPDATE_REFRESH_TOKEN, 
                [$hashedRefreshToken, $userId]
            );
            return $affectedRows === 1;
        } catch (PDOException $e) {
            throw new AppException(ErrorCode::INFRA_DATABASE_ERROR, [], $e);
        }
    }
    
     /**
     * Clears the refresh token hash for a user.
     * This is used for logout, invalidating the user's ability to refresh their session.
     *
     * @param string $userId The ID of the user whose token should be cleared.
     * @return bool True on successful update (one row affected), false on failure.
     * @throws AppException
     */
    public function clearRefreshToken(string $userId): bool {
        try {
            // Use the wrapper's execute method
            $affectedRows = $this->dbAccess->execute(UserQueries::CLEAR_REFRESH_TOKEN, [$userId]);
            return $affectedRows === 1;
        } catch (PDOException $e) {
            throw new AppException(ErrorCode::INFRA_DATABASE_ERROR, [], $e);
        }
    }

    /**
 * Fetches complete user profile including wallet and stats in a single query.
 *
 * @param string $userId The UUID of the user.
 * @return array<string, mixed>|null Complete profile data or null if not found.
 * @throws AppException
 */
public function findCompleteProfileById(string $userId): ?array {
    try {
        $result = $this->dbAccess->fetchOne(UserQueries::FIND_COMPLETE_PROFILE_BY_ID, [$userId]);
        return $result ?: null;
    } catch (PDOException $e) {
        throw new AppException(ErrorCode::INFRA_DATABASE_ERROR, [], $e);
    }
}

}
