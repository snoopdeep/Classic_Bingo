<?php

namespace App\Config;

use Webmozart\Assert\Assert;
use InvalidArgumentException; 

/**
 * A strongly-typed DTO for application-level configuration.
 *
 * This class validates and holds general general, non-volatile application settings.
 */
final class AppConfig {
    /**
     * The global secret key used for signing API requests (e.g., for HMAC).
     * @var string
     */
    public readonly string $secret;

    /**
     * @var string The array key used to retrieve the secret value from the configuration.
     */
    private const SECRET = 'secret';

    /**
     * AppConfig constructor.
     *
     * Validates and maps the raw application configuration array into typed properties.
     *
     * @param array<string, mixed> $config The configuration array, typically from `Config::get('app')`.
     * @throws InvalidArgumentException If validation fails (e.g., key is missing, value is empty, or length is incorrect).
     */
    public function __construct(array $config) {
        // Validation: Key must exist
        Assert::keyExists($config, self::SECRET, 'The "app.secret" key is missing in your configuration.');

        // Validation: Value must not be empty
        Assert::notEmpty($config[self::SECRET], 'The "app.secret" configuration cannot be empty.');

        // Validation: Value must meet minimum and maximum length requirements
        Assert::lengthBetween($config[self::SECRET], 32, 128, 'The "app.secret" must be between 32 and 128 characters.');

        // Assignment: If validation passes, assign the immutable property
        $this->secret = $config[self::SECRET];
    }
}