<?php

namespace App\Constants;

final class DbConfigKeys
{
    /** Private constructor to prevent instantiation. */
    private function __construct() {}

    public const HOST = 'host';
    public const PORT = 'port';
    public const DBNAME = 'dbname';
    public const CHARSET = 'charset';
    public const USER = 'user';
    public const PASSWORD = 'password';
    public const DATABASE = 'database';
    public const SCHEME = 'scheme';
    public const TIMEOUT = 'timeout';
    public const TCP = 'tcp';

    // Key for the generated DSN string
    public const DSN = 'dsn';
}