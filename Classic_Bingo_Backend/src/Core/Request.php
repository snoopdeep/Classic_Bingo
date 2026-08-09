<?php

namespace App\Core;

use stdClass;

/**
 * Request:  A core Data Transfer Object (DTO) that encapsulates all incoming request data, 
 * including the JSON body, route parameters, and authenticated user payload.
 */
class Request {

    /**
     * @var string The stream wrapper used to read raw POST data (JSON body).
     */
    private const PHP_INPUT_STREAM = 'php://input';

    /**
     * @var array<string, mixed> The deserialized JSON body of the request.
     */
    private array $body;

    /**
     * @var array<string, string> Key-value pairs of dynamic parameters extracted from the URI by the router.
     */
    private array $routeParams;

    /**
     * @var stdClass|null The decoded payload of the authenticated user (e.g., JWT claims).
     */
    private ?stdClass $authUser = null;

    /**
     * Request constructor.
     * * Reads and decodes the raw JSON input stream into the $body property.
     */
    public function __construct() {

        // Reads raw request body from php://input, decodes it as an associative array, 
        // and defaults to an empty array if the body is empty or invalid.
        $this->body = json_decode(file_get_contents(self::PHP_INPUT_STREAM), true) ?? [];
        $this->routeParams = []; // Initialize as empty
    }

 /**
     * Sets the route parameters extracted from the URI by the router.
     *
     * @param array<string, string> $params Associative array of parameters (e.g., ['userId' => '123']).
     * @return void
     */
    public function setRouteParams(array $params): void {
        $this->routeParams = $params;
    }

    // --- Accessor Methods ---

    /**
     * Retrieves the deserialized JSON request body.
     *
     * @return array<string, mixed> The request body data.
     */
    public function getBody(): array {
        return $this->body;
    }

    /**
     * Retrieves all dynamic parameters extracted from the URI.
     *
     * @return array<string, string> All route parameters.
     */
    public function getRouteParams(): array {
        return $this->routeParams;
    }

    /**
     * Retrieves a single route parameter by key.
     *
     * @param string $key The name of the parameter.
     * @return string|null The parameter value, or null if the key does not exist.
     */
    public function getRouteParam(string $key): ?string {
        return $this->routeParams[$key] ?? null;
    }

    /**
     * Sets the authenticated user payload (usually a JWT claim set) after successful middleware processing.
     *
     * @param stdClass $user The decoded user object.
     * @return void
     */
    public function setAuthUser(stdClass $user): void {
        $this->authUser = $user;
    }

    /**
     * Retrieves the authenticated user payload.
     *
     * @return stdClass|null The authenticated user object, or null if the request is unauthenticated.
     */
    public function getAuthUser(): ?stdClass {
        return $this->authUser;
    }
}