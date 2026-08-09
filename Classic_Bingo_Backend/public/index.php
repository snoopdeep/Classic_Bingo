<?php


use App\Core\Container;
use App\Handlers\ExceptionHandler;
use App\Utils\Logger;
use App\Utils\Router;
use App\Contexts\CoreContext;
use App\Contexts\AuthContext;
use App\Contexts\GameContext;
use App\Enums\HttpMethods;
//Constants for configuration
use App\Constants\AppConstants;
use App\Constants\Headers; 

// 1: load Composer's autoloader...
require_once __DIR__ . '/../vendor/autoload.php';
echo "hii";

// 2: Define the absolute path to the project root
define(AppConstants::PROJECT_ROOT_NAME, dirname(__DIR__));

// 3: Load environment variables.
$dotenv = Dotenv\Dotenv::createImmutable(AppConstants::PROJECT_ROOT_VALUE);
$dotenv->safeLoad();

// 4: Initialize the static Logger service.
$logPath = $_ENV[AppConstants::ENV_LOG_PATH] ?? AppConstants::DEFAULT_LOG_PATH;
Logger::init(logFilePath: AppConstants::PROJECT_ROOT_VALUE . '/' . $logPath);

// 5: Set a global exception handler to catch all uncaught exceptions.
set_exception_handler([ExceptionHandler::class, AppConstants::EXCEPTION_HANDLER_METHOD]);

// 6: Set global headers for CORS and content type

$origin = $_SERVER['HTTP_ORIGIN'] ?? AppConstants::CORS_ORIGIN;
header(Headers::ACCESS_CONTROL_ALLOW_ORIGIN . ': ' . $origin);
header(Headers::ACCESS_CONTROL_ALLOW_METHODS . ': ' . AppConstants::CORS_METHODS);
header(Headers::ACCESS_CONTROL_ALLOW_HEADERS . ': ' . AppConstants::CORS_ALLOWED_HEADERS);
header(Headers::ACCESS_CONTROL_ALLOW_CREDENTIALS . ': ' . AppConstants::CORS_CREDENTIALS);
header(Headers::CONTENT_TYPE . ': ' . AppConstants::CONTENT_TYPE_JSON);



// Handle OPTIONS preflight requests
const REQUEST_METHOD = AppConstants::SERVER_REQUEST_METHOD;
if ($_SERVER[REQUEST_METHOD] === HttpMethods::OPTIONS->value) {
    http_response_code(204);
    // Logger::log('INFO', 'Handled OPTIONS preflight request with 204 status.');
    exit(0);
}

Logger::log(AppConstants::LOG_LEVEL_INFO, AppConstants::LOG_MSG_APP_INITIALIZED);

// 7. Setup Container and Register Service Contexts
$container = new Container();

CoreContext::bind($container);
AuthContext::bind($container);
GameContext::bind($container);

// 8. Load and Dispatch Routes
$router = $container->resolve(Router::class);
require_once __DIR__ . AppConstants::ROUTES_API_PATH;
$router->dispatch($container);