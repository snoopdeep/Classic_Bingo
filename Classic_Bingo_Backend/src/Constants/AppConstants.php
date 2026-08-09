<?php

namespace App\Constants;

final class AppConstants {
    // Project/Bootstrapping
    public const PROJECT_ROOT_NAME = 'PROJECT_ROOT';
    public const PROJECT_ROOT_VALUE = PROJECT_ROOT; // Will be defined during runtime

    public const ROUTES_API_PATH = '/../src/Routes/api.php';
    public const SERVER_REQUEST_METHOD = 'REQUEST_METHOD';
    public const EXCEPTION_HANDLER_METHOD = 'handle';

    // Environment/Logging
    public const ENV_LOG_PATH = 'LOG_PATH';
    public const DEFAULT_LOG_PATH = 'logs/app.log';
    public const LOG_LEVEL_INFO = 'INFO';
    public const LOG_MSG_APP_INITIALIZED = 'App Initialized..';

    // HTTP/CORS
    public const CORS_ORIGIN = 'http://localhost:8080';
    public const CORS_METHODS = 'GET, POST, PUT, DELETE, OPTIONS';
    public const CORS_ALLOWED_HEADERS = 'Content-Type, Authorization, X-Signature, X-Timestamp';
    public const CORS_CREDENTIALS = 'true';
    public const CONTENT_TYPE_JSON = 'application/json';
    public const HTTP_NO_CONTENT = 204;
}