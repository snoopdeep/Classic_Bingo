<?php

namespace App\Core;

use App\Enums\AppEnvironment;
use Symfony\Component\Yaml\Yaml;

/**
 * The central configuration service for the application.
 *
 * This class is responsible for loading configuration from YAML files, merging
 * environment-specific overrides, and resolving placeholders against system
 * environment variables. It provides a single point of access for all
 * configuration values.
 */
final class Config {
    /**
     * Stores the final, merged, and resolved configuration parameters.
     *
     * @var array<string, mixed>
     */
    private array $parameters = [];
    
    private const APP_ENV = 'APP_ENV';
    private const RESOLVE_ENV_VARS = 'resolveEnvVars';

    /**
     * Initializes the configuration service by loading and processing all config files.
     *
     * @param string $configDirectory The absolute path to the configuration directory.
     * @return void
     */
    public function __construct(string $configDirectory) {
        // 1. Load all base .yml files
        $configValues = [];
        $baseFiles = glob($configDirectory . '/*.yml');
        
        // Exclude environment-specific files from the initial load
        $baseFiles = array_filter($baseFiles, fn($file) => !str_contains($file, '_'));

        foreach ($baseFiles as $filePath) {
            $filename = pathinfo($filePath, PATHINFO_FILENAME);
            $configValues[$filename] = Yaml::parseFile($filePath);
        }

        // 2. Check the environment and load overrides
        $environment = strtolower($_ENV[self::APP_ENV] ?? AppEnvironment::DEVELOPMENT);
        $overridePath = $configDirectory . "/parameters_{$environment}.yml";

        if (file_exists($overridePath)) {

            $overrideValues = Yaml::parseFile($overridePath);
            // check if any content is there, if there then yaml::parseFile will return an array else it will be empty array. 
            if (is_array($overrideValues)) {
                $configValues = array_replace_recursive($configValues, $overrideValues);
            }
        }
        
        // 3. Process environment variables
        array_walk_recursive($configValues, [$this, self::RESOLVE_ENV_VARS]);
        $this->parameters = $configValues;
    }

    /**
     * Retrieves a top-level configuration key.
    *
     * @param string $key The top-level configuration key to retrieve.
     * @param mixed|null $default The default value to return if the key is not found.
     * @return mixed The configuration array or value, or the default if not found.
     */
    public function get(string $key, $default = null) {
        return $this->parameters[$key] ?? $default;
    }

     /**
     * Recursively resolves environment variable placeholders in a given value.
     *
     * This method searches for the pattern `${VAR_NAME:default_value}` and replaces
     * it with the corresponding value from `$_ENV` or the provided default.
     *
     * @param mixed &$value The configuration value to process (passed by reference).
     * @return void
     */
    private function resolveEnvVars(&$value): void {
        if (is_string($value)) {
            $value = preg_replace_callback(
                '/\${(.*?)}/',
                function ($matches) {
                    [$envKey, $defaultValue] = array_pad(explode(':', $matches[1], 2), 2, null);
                    return $_ENV[$envKey] ?? $defaultValue;
                },
                $value
            );
        }
    }
}