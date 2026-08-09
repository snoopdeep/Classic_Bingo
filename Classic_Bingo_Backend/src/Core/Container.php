<?php

namespace App\Core;

use Closure;
use Exception;

/**
 * A simple DI container for managing class dependencies.
 *
 * This container handles the registration (binding) of services and their
 * resolution, including support for singleton instances. It allows for a
 * decoupled architecture where objects receive their dependencies instead of
 * creating them.
 */

class Container {
    /**
     * @var string The array key for a service's resolver function.
     */
    private const KEY_RESOLVER = 'resolver';

    /**
     * @var string The array key for a service's singleton status flag.
     */
    private const KEY_SINGLETON = 'singleton';

    /**
     * Holds all registered service bindings.
     *
     * @var array<string, array{resolver: Closure, singleton: bool}>
     */
    protected array $bindings = [];

    /**
     * Holds all resolved singleton instances to prevent re-instantiation.
     *
     * @var array<string, mixed>
     */
    protected array $instances = [];

    /**
     * Binds a resolver to the container.
     *
     * @param string $key The identifier for the service (e.g., class name).
     * @param Closure $resolver A function that creates the service instance.
     * @param bool $singleton If true, the service will only be created once.
     */
    public function bind(string $key, Closure $resolver, bool $singleton = false): void {
        $this->bindings[$key] = [self::KEY_RESOLVER => $resolver, self::KEY_SINGLETON => $singleton];
    }

    /**
     * Binds a service that should only be instantiated once (a singleton).
     * This is a convenience method that calls `bind()` with the singleton flag set to true.
     *
     * @param string  $key      The identifier for the service.
     * @param Closure $resolver A function that creates the service instance.
     * @return void
     */
    public function singleton(string $key, Closure $resolver): void {
        $this->bind($key, $resolver, true);
    }

    /**
     * Resolves a service from the container.
     *
     * @param string $key The identifier for the service.
     * @return mixed The resolved service instance.
     * @throws Exception If the service cannot be resolved.
     */
    public function resolve(string $key)   {
        if (!isset($this->bindings[$key])) {
            throw new Exception("No binding found for '{$key}'");
        }

        // If it's a singleton and we already have an instance, return it.
        if ($this->bindings[$key][self::KEY_SINGLETON] && isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        // Otherwise, create a new instance.
        $resolver = $this->bindings[$key][self::KEY_RESOLVER];
        $instance = $resolver($this);

        // If it's a singleton, store the new instance.
        if ($this->bindings[$key][self::KEY_SINGLETON]) {
            $this->instances[$key] = $instance;
        }

        return $instance;
    }
}