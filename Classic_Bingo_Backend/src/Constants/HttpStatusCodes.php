<?php

namespace App\Constants;

/**
 * A central definition for common HTTP status codes.
 */
final class HttpStatusCodes
{
    private function __construct() {}

    public const OK = 200;
    public const CREATED = 201;

    public const BAD_REQUEST = 400;
    public const UNAUTHORIZED = 401;
    public const FORBIDDEN = 403;
    public const NOT_FOUND = 404;
    public const CONFLICT = 409;

    public const INTERNAL_SERVER_ERROR = 500;
}
