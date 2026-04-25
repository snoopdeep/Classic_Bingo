<?php

$origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://127.0.0.1:8080';
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Signature, X-Timestamp");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


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