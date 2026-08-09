<?php

namespace App\Utils;
use App\Config\GameConfig;
use App\Constants\GameEntitiesConstants;
use App\Utils\Randomizer;
use InvalidArgumentException;


/**
 * BingoGenerator
 * * A pure, stateless utility class responsible for creating all Bingo game assets, 
 * including card grids and the sequence of numbers to be called.
 */
class BingoGenerator {

    /**
     * @var GameConfig The configuration DTO containing game settings, including winning patterns.
     */
    private GameConfig $gameConfig;

    /**
     * BingoGenerator constructor.
     *
     * @param GameConfig $gameConfig The game configuration dependency.
     */
    public function __construct(GameConfig $gameConfig) {
        $this->gameConfig = $gameConfig;
    }

     /**
     * Creates a shuffled array of Bingo numbers (1-75) using the Fisher-Yates algorithm.
     * * @return array<int, int> Shuffled integers from 1 to 75.
     */
    public function generateNumberSequence(): array {
        $numbers = range(1, 75);
        for ($i = count($numbers) - 1; $i > 0; $i--) {
            $j = Randomizer::int(0, $i); 
            [$numbers[$i], $numbers[$j]] = [$numbers[$j], $numbers[$i]]; // Modern swap
        }
        return $numbers;
    }

    /**
     * Generates an indexed array of unique Bingo cards for a complete session.
     * * @param int $totalCards The total number of cards to generate.
     * @return array<int, array<string, mixed>> Indexed array of card objects. 
     * Format: [{GRID: array, DAUBED: array, CARD_ID: int}, ...]
     */
    public function generateBingoCards(int $totalCards): array {
        $cards = [];
        for ($cardIndex = 0; $cardIndex < $totalCards; $cardIndex++) {
            $cards[] = [
                GameEntitiesConstants::GRID => $this->generateSingleBingoCard(),
                // Initialize daubed status: 1 => marked (true), 0 => unmarked (false).
                GameEntitiesConstants::DAUBED => array_fill(0, 25, 0),  
                GameEntitiesConstants::CARD_ID => $cardIndex
            ];
        }
        return $cards;
    }

   /**
     * Creates single valid bingo card with 1D array using row-major indexing
     * B(1-15), I(16-30), N(31-45), G(46-60), O(61-75) with FREE center space at index 12
     * 
     * @return array<int, int|string> 1D array with 25 elements (0-24 indices).
     * 
 */
    private function generateSingleBingoCard(): array {
        $grid = array_fill(0, 25, null);
        
        // Column number generation
        $columns = [
            'B' => $this->getRandomNumbers(1, 15, 5),
            'I' => $this->getRandomNumbers(16, 30, 5),
            'N' => $this->getRandomNumbers(31, 45, 4), // 4 numbers for N column
            'G' => $this->getRandomNumbers(46, 60, 5),
            'O' => $this->getRandomNumbers(61, 75, 5),
        ];
        
        // Fill grid using row-major indexing (index = row * 5 + col)
        for ($row = 0; $row < 5; $row++) {
            $grid[$row * 5 + 0] = $columns['B'][$row];
            $grid[$row * 5 + 1] = $columns['I'][$row];
            if ($row == 2) { // The center row
                $grid[$row * 5 + 2] = GameEntitiesConstants::FREE;
            } else {
                $nIndex = ($row < 2) ? $row : $row - 1; // Skips the center cell
                $grid[$row * 5 + 2] = $columns['N'][$nIndex];
            }
            $grid[$row * 5 + 3] = $columns['G'][$row];
            $grid[$row * 5 + 4] = $columns['O'][$row];
        }
        
        return $grid;
    }
  
     /**
     * Selects unique random numbers from a specified range.
     * * @param int $min Minimum number in range (inclusive).
     * @param int $max Maximum number in range (inclusive).  
     * @param int $count How many unique numbers to select.
     * @return array<int, int> Array of unique random integers from the range.
     */
    private function getRandomNumbers(int $min, int $max, int $count): array {
        $numbers = range($min, $max);
        shuffle($numbers);
        return array_slice($numbers, 0, $count);
    }

/**
     * Checks if a card has a winning bingo pattern based on the daubed status.
     *
     * @param array<int, int> $daubedArray Integer array of 25 elements (indices 0-24) having 0 or 1.
     * @param string|null $patternType The specific pattern group to check (e.g., 'standard', 'blackout') 
     * or null to check all patterns (competitive mode).
     * @return array{isWinner: int} An array indicating if a win was found (1 for true, 0 for false).
     * @throws InvalidArgumentException if the daubed array is the wrong size.
     */

public function checkForBingoClaim(array $daubedArray, ?string $patternType = null): array {

    // Validate input
    if (count($daubedArray) !== 25) {
        throw new InvalidArgumentException("Daubed array must contain exactly 25 elements, got " . count($daubedArray));
    }

    // Determine which patterns to check
    if ($patternType !== null) {
        // Practice mode or specific pattern request
        $winningSets = $this->gameConfig->getPatternsByType($patternType);
    } else {
        // Competitive mode: use ALL patterns defined in config
        $winningSets = $this->gameConfig->getPatterns();
    }

    // Check each potential winning square set
        foreach ($winningSets as $squareSet) {
        if ($this->allSquaresMarked($daubedArray, $squareSet)) {
            // Found a match
            return [
                GameEntitiesConstants::IS_WINNER => 1,
            ];
        }
    }
    
    // No pattern matched
        return [
            GameEntitiesConstants::IS_WINNER => 0,
        ];
}

/**
     * Checks if all squares in a set are marked (daubed)
     *
     * Treats index 12 (center FREE space) as always marked
     *
     * @param array<int, int> $daubedArray Boolean array of 25 elements
     * @param array<int, int> $squareSet Array of indices to check, e.g., [0, 1, 2, 3, 4], one hz row.. 
     * @return bool TRUE if all squares are marked
     */
    private function allSquaresMarked(array $daubedArray, array $squareSet): bool {
        foreach ($squareSet as $index) {
            // FREE space at index 12 is always considered marked
            if ($index === 12) {
                continue;
            }

            // Check if this square is marked (using the 1 fro daubed state)
            if (!isset($daubedArray[$index]) || $daubedArray[$index] !== 1) {
                return false;
            }
        }
        return true;
    }
}