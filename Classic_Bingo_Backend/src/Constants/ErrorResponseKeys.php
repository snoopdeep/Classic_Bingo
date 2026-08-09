<?php

namespace App\Constants;

final class ErrorResponseKeys
{
    private function __construct() {}

    public const ERROR = 'error';
    // public const ERROR_CODE = 'error_code';
    public const EXCEPTION = 'exception';
    public const ERROR_MESSAGE = 'message';
    public const ERROR_FILE = 'file';
    public const ERROR_LINE = 'line';
    public const TRACE = 'trace';
    public const CORRELATION_ID = 'correlationId';
    public const ERROR_CODE = 'code';
    public const EXCEPTION_PARAMS = 'params';
    public const ERROR_CLASS = 'errorClass';
}