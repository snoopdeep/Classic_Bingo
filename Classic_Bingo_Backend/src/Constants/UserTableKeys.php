<?php

namespace App\Constants;
final class UserTableKeys {
    private function __construct() {}

    // Maps directly to the `users` table columns
    public const ID = 'user_id';
    public const NAME = 'user_name';
    public const AVATAR_ID = 'avatar_id';
    public const ROLE = 'role';
    public const REFRESH_TOKEN = 'refresh_token';
    public const CREATED_AT = 'created_at';
    public const LAST_LOGIN = 'last_login';
}