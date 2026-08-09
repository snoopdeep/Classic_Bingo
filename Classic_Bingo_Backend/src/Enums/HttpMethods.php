<?php 
namespace App\Enums;

enum HttpMethods : string {
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT'; 
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
    case OPTIONS = 'OPTIONS';
    case HEAD = 'HEAD'; 

    /**
     * Get an array of all HTTP Methods values.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}