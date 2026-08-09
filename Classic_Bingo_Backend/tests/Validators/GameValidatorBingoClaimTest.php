<?php

namespace App\Tests\Unit\Validators;

use App\Handlers\AppException;
use App\Enums\ErrorCode;
use App\Validators\GameValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit test suite for GameValidator's bingo claim validation.
 *
 * @covers \App\Validators\GameValidator::validateBingoClaimInput
 */
final class GameValidatorBingoClaimTest extends TestCase
{
    // ===================
    // Data Providers ||| validateBingoClaimInput
    // ======================

    public static function provideInvalidCardIndexInput(): array {
        return [
            'Missing cardIndex' => [
                [],
                'cardIndex',
                'required and must be a number',
            ],
            'cardIndex is null' => [
                ['cardIndex' => null],
                'cardIndex',
                'required and must be a number',
            ],
            'cardIndex is non-numeric string' => [
                ['cardIndex' => 'A'],
                'cardIndex',
                'required and must be a number',
            ],
            'cardIndex is empty string' => [
                ['cardIndex' => ''],
                'cardIndex',
                'required and must be a number',
            ],
            'cardIndex is negative integer' => [
                ['cardIndex' => -1],
                'cardIndex',
                'must be non-negative',
            ],
            'cardIndex is negative numeric string' => [
                ['cardIndex' => '-5'],
                'cardIndex',
                'must be non-negative',
            ],
        ];
    }

    public static function provideValidCardIndexInput(): array {
        return [
            'Zero index as int' => [['cardIndex' => 0]],
            'Positive index as int' => [['cardIndex' => 1]],
            'Positive index as numeric string' => [['cardIndex' => '99']],
            'Additional fields present' => [['cardIndex' => 1, 'other' => 'data']],
        ];
    }

    // ======================
    // Tests 
    // =========================

    #[Test]
    #[DataProvider('provideValidCardIndexInput')]
    public function it_passes_validation_with_valid_card_index(array $input): void {
        $this->expectNotToPerformAssertions();
        GameValidator::validateBingoClaimInput($input);
    }

    #[Test]
    #[DataProvider('provideInvalidCardIndexInput')]
    public function it_throws_app_exception_for_invalid_card_index(array $input, string $expectedField, string $expectedReason): void {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(400); 
        try {
            GameValidator::validateBingoClaimInput($input);
        } catch (AppException $e) {
            $this->assertArrayHasKey('field', $e->getDetails());
            $this->assertSame($expectedField, $e->getDetails()['field']);
            $this->assertStringContainsString($expectedReason, $e->getDetails()['reason']);
            throw $e;
        }
    }
}