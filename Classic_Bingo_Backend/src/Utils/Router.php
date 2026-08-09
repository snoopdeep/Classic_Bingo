<?php 

namespace App\Utils;

use App\Core\Container;
use App\Utils\Response;
use App\Core\Request;
use App\Enums\HttpMethods;
use App\Constants\ServerKeys;

/**
 * A URI router that maps HTTP requests to controller actions.
 *
 * This class handles route registration, dynamic parameter extraction, and the
 * execution of a middleware chain before dispatching to a controller.
 */

class Router {

    // Define internal array keys as constants
     /**
     * @var string Internal array key for the route's HTTP method.
     */
    private const KEY_METHOD = 'method';
    /**
     * @var string Internal array key for the route's raw path string.
     */
    private const KEY_PATH = 'path';
    /**
     * @var string Internal array key for the route's compiled regex pattern.
     */
    private const KEY_REGEX = 'regex';
    /**
     * @var string Internal array key for the route's controller handler.
     */
    private const KEY_HANDLER = 'handler';
    /**
     * @var string Internal array key for the route's middleware chain.
     */
    private const KEY_MIDDLEWARE = 'middleware';
    
    /**
     * @var string Delimiter used to separate middleware class from its arguments.
     */
    private const MIDDLEWARE_DELIMITER = ':';

    /**
     * @var string The key expected for response data from a controller method.
     */
    private const RESPONSE_DATA_KEY = 'data';

    /**
     * @var string The key expected for response status from a controller method.
     */
    private const RESPONSE_STATUS_KEY = 'status';

    /**
     * @var int HTTP status code for route not found.
     */
    private const HTTP_NOT_FOUND_CODE = 404;

    /**
     * @var string Error message for route not found.
     */
    private const HTTP_NOT_FOUND_MESSAGE = 'Route not found';
    /**
     * Stores all registered routes.
     * @var array<int, array<string, mixed>>
     */
    private array $routes = [];

    /**
     * Registers a route that responds to GET requests.
     *
     * @param string $path The URL path pattern (e.g., '/users/{userId}').
     * @param array{0: class-string, 1: string} $handler An array containing the controller class and method name.
     * @param array<int, class-string> $middleware An array of middleware to apply to this route.
     * @return void
     */
    public function get(string $path, array $handler, array $middleware = []): void {
        $this->addRoute(HttpMethods::GET->value, $path, $handler, $middleware);
    }

    /**
     * Registers a route that responds to POST requests.
     *
     * @param string $path The URL path pattern.
     * @param array{0: class-string, 1: string} $handler An array containing the controller class and method name.
     * @param array<int, class-string> $middleware An array of middleware to apply to this route.
     * @return void
     */
    public function post(string $path, array $handler, array $middleware = []): void {
        $this->addRoute(HttpMethods::POST->value, $path, $handler, $middleware);
    }

    /**
     * Registers a route that responds to PUT requests.
     *
     * @param string $path The URL path pattern.
     * @param array{0: class-string, 1: string} $handler An array containing the controller class and method name.
     * @param array<int, class-string> $middleware An array of middleware to apply to this route.
     * @return void
     */
     public function put(string $path, array $handler, array $middleware = []): void {
        $this->addRoute(HttpMethods::PUT->value, $path, $handler, $middleware);
    }

    /**
     * A private helper to compile and store a route definition.
     * 
     * @param string $method The HTTP method (GET, POST, etc.).
     * @param string $path The URL path pattern.
     * @param array{0: class-string, 1: string} $handler The controller handler.
     * @param array<int, class-string> $middleware The middleware chain.
     * @return void
     */
    private function addRoute(string $method, string $path, array $handler, array $middleware = []): void 
    {
        $pathRegex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        $this->routes[] = [
            self::KEY_METHOD => $method,
            self::KEY_PATH => $path,
            self::KEY_REGEX => '#^' . $pathRegex . '$#',
            self::KEY_HANDLER => $handler,
            self::KEY_MIDDLEWARE => $middleware 
        ];
    }

    /**
     * Dispatches the incoming request to the appropriate route handler.
     *
     * It finds the matching route, executes its middleware, and then calls the final controller action.
     *
     * @param Container $container The application's DI container, used to resolve controllers and middleware.
     * @return void Terminates by sending a response or a 404 error if no route is found.
     */
    public function dispatch(Container $container): void {

        $requestMethod = $_SERVER[ServerKeys::REQUEST_METHOD];
        // Extract only the path, ignoring any query string.
        $requestPath = parse_url($_SERVER[ServerKeys::REQUEST_URI], PHP_URL_PATH); 

        // Resolve the single Request instance from the container.
        $request = $container->resolve(Request::class);
        
        foreach ($this->routes as $route) {
            // Check if the method and path regex match the current request.
            if ($requestMethod === $route[self::KEY_METHOD] && preg_match($route[self::KEY_REGEX], $requestPath, $matches)) {

                // Filter the regex matches to get only the named capturing groups (our URL parameters).
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY); // ['userId' => 'abc-123'], userId is the placeHolder defined in the route. 


                // Set the extracted parameters on the Request object.
                $request->setRouteParams($params);

                // EXECUTE MIDDLEWARE CHAIN 
                foreach ($route[self::KEY_MIDDLEWARE] as $middlewareItem) {

                    // Parse middleware string, e.g., "AuthorizationMiddleware:owner:admin"
                    $parts = explode(self::MIDDLEWARE_DELIMITER,$middlewareItem); // The class name. [ AuthenticationMiddleware OR AuthorizationMiddleware]
                    $middlewareClass = $parts[0]; //  // Any remaining parts are arguments. 
                    $middlewareArgs = array_slice($parts, 1); // ['admin', 'user', 'developer', 'tester', 'moderator']

                    // Resolve the middleware instance from the container, ie build a middlewareClass ( ex : AuthenticationMiddlewar ) object
                    $middlewareInstance = $container->resolve($middlewareClass);

                    // Pass the request object and middleware-specific args to the handle method
                    $middlewareInstance->handle($request, ...$middlewareArgs);
                }

                // PROCEED TO CONTROLLER               
                [$class, $method] = $route[self::KEY_HANDLER];
                // Resolve the controller instance from the container, build a complete class object ( ex : AuthContorller)
                $controller = $container->resolve($class);

                // 1. Combine the required Request object with the URL parameter values.
                // array_values($params) turns ['sessionId' => 'abc'] into ['abc']
                $arguments = array_merge([$request], array_values($params));
                
                // 2. CAPTURE the response array from the controller method
                $responseArray = $controller->$method(...$arguments);

                // 3. SEND the final response using the data and status from the array
                Response::json($responseArray[self::RESPONSE_DATA_KEY], $responseArray[self::RESPONSE_STATUS_KEY]);

                return; // Stop processing routes once a match is found and dispatched.
             }
        }
        // If the loop finishes without finding a match, send a 404 response.
       Response::json(
            [self::RESPONSE_DATA_KEY => self::HTTP_NOT_FOUND_MESSAGE], 
            self::HTTP_NOT_FOUND_CODE
        );
    }
}