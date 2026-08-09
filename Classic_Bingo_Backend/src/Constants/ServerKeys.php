<?php 
// containes all the important params of $_SERVER GLOBAL OBJECT. 
namespace App\Constants;

final class ServerKeys{
    private function __construct() {}

    public const REQUEST_METHOD = 'REQUEST_METHOD';
    public const REQUEST_URI = 'REQUEST_URI';
    public const HTTP_AUTHORIZATION = 'HTTP_AUTHORIZATION';
    public const HTTP_X_SIGNATURE = 'HTTP_X_SIGNATURE';
    public const HTTP_X_TIMESTAMP = 'HTTP_X_TIMESTAMP';
}