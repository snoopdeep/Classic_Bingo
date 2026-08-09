<?php

namespace App\Config;

use App\Constants\GameConfigKeys;
use Webmozart\Assert\Assert;

/**
 * A strongly-typed DTO for the complete game configuration.
 * It validates and holds all settings from game.yml.
 */
final class GameConfig {

    /** The interval for calling new numbers, in seconds. */
    public readonly int $callInterval;
    
    /** Configuration for different game modes (e.g., costs). */
    public readonly array $modes;

    /** The validated list of all winning patterns. */
    private readonly array $patterns;

    // Practice Mode properties:
    private readonly array $patternMetadata;
    private readonly array $practiceConfig;



    /**
     * Validates and maps the raw game configuration array.
     *
     * @param array<string, mixed> $config The configuration array, from `Config::get('game')`.
     * @throws \InvalidArgumentException If validation fails.
     */
    public function __construct(array $config) {
        
        // 1. Validate and assign call_interval (with type casting)
        Assert::keyExists($config, GameConfigKeys::CALL_INTERVAL, 'The "call_interval" key is missing in game.yml');
        $callIntervalValue = (int) $config[GameConfigKeys::CALL_INTERVAL];
        Assert::positiveInteger($callIntervalValue, 'The "call_interval" must be a positive integer.');
        $this->callInterval = $callIntervalValue;

        // 2. Validate and assign modes
        Assert::keyExists($config, GameConfigKeys::MODES, 'The "modes" key is missing in game.yml');
        Assert::isArray($config[GameConfigKeys::MODES], 'The "modes" key must be an array.');
        Assert::notEmpty($config[GameConfigKeys::MODES], 'The "modes" array cannot be empty.');

        //Validate the structure of each mode
        foreach ($config[GameConfigKeys::MODES] as $modeKey => $modeSettings) {
            Assert::keyExists($modeSettings, GameConfigKeys::BASE_COST, "baseCost is required for mode '{$modeKey}'.");
            Assert::integer($modeSettings[GameConfigKeys::BASE_COST], "baseCost for mode '{$modeKey}' must be an integer.");
            Assert::keyExists($modeSettings, GameConfigKeys::EXTRA_CARD_COST, "extraCardCost is required for mode '{$modeKey}'.");
            Assert::integer($modeSettings[GameConfigKeys::EXTRA_CARD_COST], "extraCardCost for mode '{$modeKey}' must be an integer.");
        }
        $this->modes = $config[GameConfigKeys::MODES];

        // 3. Validate and assign patterns
        Assert::keyExists($config, GameConfigKeys::PATTERNS, 'The "patterns" key is missing in game.yml');
        Assert::isArray($config[GameConfigKeys::PATTERNS], 'The "patterns" key must be an array.');
        $this->patterns = $config[GameConfigKeys::PATTERNS];

        // 4. Validate and assign pattern_metadata
        Assert::keyExists($config, GameConfigKeys::PATTERN_METADATA, 'The "pattern_metadata" key is missing in game.yml');
        Assert::isArray($config[GameConfigKeys::PATTERN_METADATA], 'The "pattern_metadata" must be an array.');
        $this->patternMetadata = $config[GameConfigKeys::PATTERN_METADATA];

        // 5. Validate and assign practice config
        Assert::keyExists($config, GameConfigKeys::PRACTICE, 'The "practice" key is missing in game.yml');
        Assert::isArray($config[GameConfigKeys::PRACTICE], 'The "practice" must be an array.');
        Assert::keyExists($config[GameConfigKeys::PRACTICE], 'ball_speeds', 'The "ball_speeds" is required in practice config.');
        Assert::keyExists($config[GameConfigKeys::PRACTICE], 'available_patterns', 'The "available_patterns" is required in practice config.');
        $this->practiceConfig = $config[GameConfigKeys::PRACTICE];

        }

      /**
         * Gets all available winning sets .
         *
         * @return array An array of arrays, where each inner array is a set of winning square indices.
     */
    public function getPatterns(): array {
        return $this->patterns;
    }
    /**
     * Gets the configuration for a specific game mode.
     *
     * @param string $mode The game mode (e.g., 'vs_ai').
     * @return array|null The settings array or null if not found.
     */
    public function getModeConfig(string $mode): ?array {
        return $this->modes[$mode] ?? null;
    }

    /**
     * Gets the winning pattern indices for a specific pattern type.
     * 
     * @param string $patternType The pattern type (e.g., 'standard', 'four_corners')
     * @return array Array of winning square sets for this pattern
     */
    public function getPatternsByType(string $patternType): array {
        if (!isset($this->patternMetadata[$patternType])) {
            throw new \InvalidArgumentException("Unknown pattern type: {$patternType}");
        }
        
        $indices = $this->patternMetadata[$patternType]['indices'];
        $selectedPatterns = [];
        
        foreach ($indices as $index) {
            if (isset($this->patterns[$index])) {
                $selectedPatterns[] = $this->patterns[$index];
            }
        }
        
        return $selectedPatterns;
    }

    /**
     * Gets practice mode configuration.
     */
    public function getPracticeConfig(): array {
        return $this->practiceConfig;
    }

    /**
     * Gets available ball speeds for practice mode.
     */
    public function getPracticeBallSpeeds(): array {
        return $this->practiceConfig['ball_speeds'];
    }

    /**
     * Gets available patterns for practice mode.
     */
    public function getAvailablePracticePatterns(): array {
        $patterns = [];
        foreach ($this->practiceConfig['available_patterns'] as $patternType) {
            if (isset($this->patternMetadata[$patternType])) {
                $patterns[$patternType] = [
                    'name' => $this->patternMetadata[$patternType]['name'],
                    'description' => $this->patternMetadata[$patternType]['description']
                ];
            }
        }
        return $patterns;
    }

}