<?php
namespace App\Constants;

final class CookieConstants
{
    private function __construct() {}

    // Cookie Names
    public const REFRESH_TOKEN = 'refreshToken';

    // Cookie Options
    public const OPTION_EXPIRES = 'expires';
    public const OPTION_HTTP_ONLY = 'httponly';
    public const OPTION_SECURE = 'secure';
    public const OPTION_SAME_SITE = 'samesite';
    public const VALUE_SAMESITE_STRICT = 'Strict';
    public const VALUE_SAMESITE_LAX = 'Lax';
}