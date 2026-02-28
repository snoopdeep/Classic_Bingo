<?php

// allow CORS 
header("Access-Control-Allow-Origin: *");


try {
    // load Composer's autoloader...
    require_once __DIR__ . '/../vendor/autoload.php';

    // Load environment variables.
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad(); 

    // Load and dispatch routes
    $router = require_once __DIR__ . '/../src/Routes/api.php';
    $router->dispatch();

} catch (Exception $e) {
    
    // return generic error response;;;
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => 'Something went wrong. Please try again later.'
    ]);
}