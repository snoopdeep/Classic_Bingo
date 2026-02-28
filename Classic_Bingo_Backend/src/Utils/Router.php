<?php 

namespace App\Utils;

use App\Utils\Response;

class Router 
{
    private array $routes = [];

    public function get(string $path, array $handler): void 
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void 
    {
        $this->addRoute('POST', $path, $handler);
    }

    private function addRoute(string $method, string $path, array $handler): void 
    {
        // path :: /api/v1/auth/user/{userId} 
        // pathRegex :: #^/api/v1/auth/user/(?P<userId>[^/]+)$#' // put the params in special char, to compare the path
        $pathRegex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'regex' => '#^' . $pathRegex . '$#',
            'handler' => $handler
        ];
    }

    public function dispatch(): void 
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        foreach ($this->routes as $route) {
            if ($requestMethod === $route['method'] && preg_match($route['regex'], $requestPath, $matches)) {
                // Remove full match and keep only named groups
                array_shift($matches);
                
                [$class, $method] = $route['handler'];
                $controller = new $class();
                
                // call controller method with parameters
                if (empty($matches)) {
                    $controller->$method();
                } else {
                    $controller->$method($matches);
                }
                return;
            }
        }
        
        Response::json(['error' => 'Route not found'], 404);
    }
}
