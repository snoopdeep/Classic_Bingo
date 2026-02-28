<?php 

use App\Controllers\AuthController;
use App\Utils\Router;

$router = new Router();

// public routes
$router->post('/api/v1/auth/guest', [AuthController::class, 'createGuest']);

// protected routes /// (no middleware here, handled in controller, can be added here??)
$router->get('/api/v1/auth/me', [AuthController::class, 'getMe']);
$router->get('/api/v1/auth/user/{userId}', [AuthController::class, 'getUser']);
$router->post('/api/v1/auth/logout', [AuthController::class, 'logout']);

return $router;