<?php

namespace Tests\Utils;

use App\Utils\BingoGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BingoGenerator::class)]
class BingoGeneratorTest extends TestCase {
    private BingoGenerator $generator;

    protected function setUp(): void {
        parent::setUp();
        $this->generator = new BingoGenerator();
    }

    //========================================================================
    // generateNumberSequence Tests
    //========================================================================

    #[Test]
    public function generateNumberSequenceReturns75UniqueNumbers(): void {
        $sequence = $this->generator->generateNumberSequence();

        // 1. Check if it returns exactly 75 elements
        $this->assertCount(75, $sequence);

        // 2. Check if all elements are unique
        $this->assertCount(75, array_unique($sequence), "The sequence should contain only unique numbers.");

        // 3. Sanity check: ensure all numbers from 1 to 75 are present
        sort($sequence);
        $this->assertEquals(range(1, 75), $sequence);
    }

    #[Test]
    public function generateNumberSequenceReturnsShuffledArray(): void {
        $sequence1 = $this->generator->generateNumberSequence();
        $sequence2 = $this->generator->generateNumberSequence();

        // It's astronomically unlikely for two shuffled arrays of 75 elements to be identical.
        // This test reliably checks the shuffling behavior.
        $this->assertNotEquals($sequence1, $sequence2, "Two generated sequences should not be identical.");
    }

    //========================================================================
    // generateBingoCards Tests
    //========================================================================

    #[Test]
    public function generateBingoCardsReturnsCorrectNumberOfCardsWithProperStructure(): void  {
        $totalCards = 5;
        $cards = $this->generator->generateBingoCards($totalCards);

        // 1. Check if the correct number of cards were generated
        $this->assertCount($totalCards, $cards);

        // 2. Check the structure of each card
        foreach ($cards as $index => $card) {
            $this->assertIsArray($card);
            $this->assertArrayHasKey('grid', $card);
            $this->assertArrayHasKey('daubed', $card);
            $this->assertArrayHasKey('cardId', $card);

            // Check cardId is sequential
            $this->assertSame($index, $card['cardId']);

            // Check daubed array structure
            $this->assertCount(25, $card['daubed']);
            $this->assertEquals(array_fill(0, 25, false), $card['daubed']);

            // Check grid structure
            $this->assertCount(25, $card['grid']);
        }
    }

    #[Test]
    public function generatedCardGridAdheresToAllBingoRules(): void {
        // Generate a single card to inspect its rules
        $card = $this->generator->generateBingoCards(1)[0];
        $grid = $card['grid'];

        // Rule 1: The center square (index 12) must be 'FREE'
        $this->assertSame('FREE', $grid[12]);

        // Rule 2: All 24 numbers on the card must be unique
        $numbersOnly = array_filter($grid, 'is_numeric');
        $this->assertCount(24, $numbersOnly, "Card should have 24 numbers.");
        $this->assertCount(24, array_unique($numbersOnly), "All numbers on the card must be unique.");

        // Rule 3: Each column must contain numbers within the correct range
        $columnRules = [
            'B' => ['min' => 1,  'max' => 15, 'indices' => [0, 5, 10, 15, 20]],
            'I' => ['min' => 16, 'max' => 30, 'indices' => [1, 6, 11, 16, 21]],
            'N' => ['min' => 31, 'max' => 45, 'indices' => [2, 7, /*skip 12*/ 17, 22]],
            'G' => ['min' => 46, 'max' => 60, 'indices' => [3, 8, 13, 18, 23]],
            'O' => ['min' => 61, 'max' => 75, 'indices' => [4, 9, 14, 19, 24]],
        ];

        foreach ($columnRules as $colLetter => $rules) {
            $columnNumbers = [];
            foreach ($rules['indices'] as $index) {
                $number = $grid[$index];
                $this->assertIsNumeric($number, "Column {$colLetter} contains a non-numeric value at index {$index}.");
                $this->assertGreaterThanOrEqual($rules['min'], $number, "Number in column {$colLetter} is too low.");
                $this->assertLessThanOrEqual($rules['max'], $number, "Number in column {$colLetter} is too high.");
                $columnNumbers[] = $number;
            }
            // Rule 4: Numbers within a single column must be unique
            $this->assertCount(count($columnNumbers), array_unique($columnNumbers), "Column {$colLetter} should have unique numbers.");
        }
    }
}
