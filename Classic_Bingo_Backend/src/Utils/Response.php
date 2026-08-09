<?php

namespace App\Utils;

use App\Constants\HttpHeaders;

/**
 * A simple static utility for sending standardized JSON responses.
 */
class Response {

    /**
     * Sends a JSON response or captures it if in test mode.
     *
     * @param array<string, mixed> $data The associative array to be encoded as JSON.
     * @param int $statusCode The HTTP status code for the response.
     * @return void
     */
    public static function json(array $data, int $statusCode = 200): void  {

        // A check to prevent "headers already sent" errors when running from console.
        if (!headers_sent()) {
            http_response_code($statusCode);
            header(HttpHeaders::CONTENT_TYPE . ':' . HttpHeaders::APP_JSON);
        }
        
        echo json_encode($data);
        
        // exit();
    }
}
