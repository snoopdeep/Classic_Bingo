<?php

namespace App\Tests\Unit\Utils;

use App\Config\GameConfig;
use App\Utils\BingoGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit test suite for BingoGenerator's core logic.
 * 
 * @covers \App\Utils\BingoGenerator::checkForBingoClaim
 * @covers \App\Utils\BingoGenerator::allSquaresMarked
 */
final class BingoGeneratorBingoCheckTest extends TestCase {
    private const FREE_SPACE_INDEX = 12;
    private BingoGenerator $bingoGenerator;

    protected function setUp(): void {
        // Define the patterns...
        $patternsData = [
            'line_horizontal' => [
                'name' => 'Horizontal Line',
                'squares' => [
                    [0, 1, 2, 3, 4],          // Row 1 (No free space)
                    [10, 11, 12, 13, 14],     // Row 3 (Includes FREE space at 12)
                ]
            ],
            'diagonal' => [
                'name' => 'Diagonal',
                'squares' => [
                    [0, 6, 12, 18, 24],       // Diagonal 1 (Includes FREE space at 12)
                ]
            ],
            'four_corners' => [
                'name' => 'Four Corners',
                'squares' => [
                    [0, 4, 20, 24]            // Corner pattern (No free space)
                ]
            ],
        ];

        // Create configuration 
        $minimalConfig = [
            'call_interval' => 4,
            // Minimal mode config required by GameConfig validation
            'modes' => ['test_mode' => ['baseCost' => 1, 'extraCardCost' => 1]], 
            'patterns' => $patternsData, 
        ];

        // Inject a REAL instance of the final class
        $realGameConfig = new GameConfig($minimalConfig); 

        // Initialize the class under test
        $this->bingoGenerator = new BingoGenerator($realGameConfig);
    }

    // ===========================
    // Data Providers 
    // ===========================

    public static function provideWinningDaubedArrays(): array {
        // 1. Horizontal Line (Row 1 - No Free Space needed)
        $h1Daubed = array_fill(0, 25, false);
        foreach ([0, 1, 2, 3, 4] as $index) { $h1Daubed[$index] = true; }
        
        // 2. Horizontal Line (Row 3 - Only 4 marks needed, index 12 is FREE)
        $h3Daubed = array_fill(0, 25, false);
        foreach ([10, 11, 13, 14] as $index) { $h3Daubed[$index] = true; }
        
        // 3. Diagonal (Only 4 marks needed, index 12 is FREE)
        $d1Daubed = array_fill(0, 25, false);
        foreach ([0, 6, 18, 24] as $index) { $d1Daubed[$index] = true; }
        
        // 4. Four Corners (All 4 marks needed)
        $c4Daubed = array_fill(0, 25, false);
        foreach ([0, 4, 20, 24] as $index) { $c4Daubed[$index] = true; }

        return [
            'Horizontal Line (Top Row)' => [$h1Daubed, 'line_horizontal', 'Horizontal Line', [0, 1, 2, 3, 4]],
            'Horizontal Line (Middle Row, FREE)' => [$h3Daubed, 'line_horizontal', 'Horizontal Line', [10, 11, 12, 13, 14]],
            'Diagonal (0, 6, 12-FREE, 18, 24)' => [$d1Daubed, 'diagonal', 'Diagonal', [0, 6, 12, 18, 24]],
            'Four Corners' => [$c4Daubed, 'four_corners', 'Four Corners', [0, 4, 20, 24]],
        ];
    }

    public static function provideNonWinningDaubedArrays(): array {
        // Base empty card
        $emptyDaubed = array_fill(0, 25, false);

        // Horizontal line missing one
        $hMissingDaubed = array_fill(0, 25, false);
        foreach ([0, 1, 2, 3] as $index) { $hMissingDaubed[$index] = true; }

        // Diagonal missing one (not free space)
        $dMissingDaubed = array_fill(0, 25, false);
        foreach ([0, 6, 12, 18] as $index) { $dMissingDaubed[$index] = true; } // 24 is missing

        // Four corners missing one (index 24 is false)
        $c4MissingDaubed = array_fill(0, 25, false);
        foreach ([0, 4, 20] as $index) { $c4MissingDaubed[$index] = true; }

        // Only the free space is implicitly marked
        $freeOnlyDaubed = array_fill(0, 25, false);
        
        return [
            'Empty Card' => [$emptyDaubed],
            'Horizontal Line Missing One' => [$hMissingDaubed],
            'Diagonal Missing One' => [$dMissingDaubed],
            'Four Corners Missing One' => [$c4MissingDaubed],
            'Only Free Space Marked (False Bingo)' => [$freeOnlyDaubed],
        ];
    }

    // ===================================================================
    // Tests
    // ===================================================================

    #[Test]
    #[DataProvider('provideWinningDaubedArrays')]
    public function it_returns_winner_true_for_valid_bingo_patterns(
        array $daubedArray,
        string $expectedId,
        string $expectedName,
        array $expectedSquares
    ): void {
        $result = $this->bingoGenerator->checkForBingoClaim($daubedArray);

        $this->assertTrue($result['isWinner'], "Should have detected a winning pattern.");
        $this->assertSame($expectedId, $result['patternId']);
        $this->assertSame($expectedName, $result['patternName']);
        $this->assertSame($expectedSquares, $result['matchedSquares']);
    }

    #[Test]
    #[DataProvider('provideNonWinningDaubedArrays')]
    public function it_returns_winner_false_for_non_winning_patterns(array $daubedArray): void {
        $result = $this->bingoGenerator->checkForBingoClaim($daubedArray);

        $this->assertFalse($result['isWinner'], "Should NOT have detected a winning pattern.");
        $this->assertNull($result['patternId']);
    }

    #[Test]
    public function it_throws_exception_for_invalid_daubed_array_size(): void {
        // Edge Case: Incorrect array size (24 instead of 25)
        $invalidDaubedArray = array_fill(0, 24, true); 

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Daubed array must contain exactly 25 elements, got 24');

        $this->bingoGenerator->checkForBingoClaim($invalidDaubedArray);
    }
}
