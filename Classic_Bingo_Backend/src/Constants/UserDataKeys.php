<?php 

namespace App\Constants;

final class UserDataKeys{

    private function __construct(){}

    public const USER_ID = 'userId'; //  Maps to DB 'user_id'
    public const USER_NAME = 'userName'; //  Maps to DB 'user_name'
    public const AVATAR_ID = 'avatarId'; //  Maps to DB 'avatar_id'
    public const USER_ROLE = 'role';
    public const REFRESH_TOKEN = 'refreshToken';
    public const ACCESS_TOKEN = 'accessToken';
    public const SESSION_ID = 'sessionId';
    public const CREATED_AT = 'createdAt';
}