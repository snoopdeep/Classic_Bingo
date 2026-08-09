<?php 

namespace App\Constants; 

final class HttpHeaders{
    /** Private constructor to prevent instantiation. */
    private function __construct() {}

    public const CONTENT_TYPE = 'Content-Type';
    public const APP_JSON = 'application/json';
}