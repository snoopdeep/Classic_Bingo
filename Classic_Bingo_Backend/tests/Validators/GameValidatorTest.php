<?php

namespace Tests\Validators;

use App\Enums\ErrorCode;
use App\Enums\GameMode;
use App\Handlers\AppException;
use App\Handlers\ErrorCatalog;
use App\Validators\GameValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GameValidator::class)]
class GameValidatorTest extends TestCase {

    //========================================================================
    // validateStartGameInput Tests
    //========================================================================

    #[Test]
    #[DataProvider('provideInvalidStartGameData')]
    public function validateStartGameInputThrowsExceptionForInvalidData(array $data, ErrorCode $expectedErrorCode): void {
        $expectedMessage = ErrorCatalog::get($expectedErrorCode)['message'];

        $this->expectException(AppException::class);
        $this->expectExceptionMessage($expectedMessage);

        GameValidator::validateStartGameInput($data);
    }

    public static function provideInvalidStartGameData(): array {
        return [
            'missing gameMode' => [
                [],
                ErrorCode::VALIDATION_GAME_INVALID_REQUEST
            ],
            'unsupported gameMode' => [
                ['gameMode' => 'invalid_mode'],
                ErrorCode::VALIDATION_GAME_MODE_UNSUPPORTED
            ],
            'vs_ai mode missing numberOfAIOpponents' => [
                ['gameMode' => GameMode::VS_AI->value, 'numberOfCards' => [1]],
                ErrorCode::VALIDATION_GAME_INVALID_REQUEST
            ],
            'vs_ai mode with non-numeric numberOfAIOpponents' => [
                ['gameMode' => GameMode::VS_AI->value, 'numberOfAIOpponents' => 'three', 'numberOfCards' => [1]],
                ErrorCode::VALIDATION_GAME_INVALID_REQUEST
            ],
            'vs_ai mode missing numberOfCards' => [
                ['gameMode' => GameMode::VS_AI->value, 'numberOfAIOpponents' => 2],
                ErrorCode::VALIDATION_GAME_INVALID_REQUEST
            ],
            'vs_ai mode with non-array numberOfCards' => [
                ['gameMode' => GameMode::VS_AI->value, 'numberOfAIOpponents' => 2, 'numberOfCards' => 'not-an-array'],
                ErrorCode::VALIDATION_GAME_INVALID_REQUEST
            ],
        ];
    }

    #[Test]
    #[DataProvider('provideValidStartGameData')]
    public function validateStartGameInputSucceedsWithValidData(array $validData): void {
        // This test succeeds if no exception is thrown.
        GameValidator::validateStartGameInput($validData);
        $this->assertTrue(true);
    }

    public static function provideValidStartGameData(): array {
        return [
            'valid vs_ai mode' => [
                [
                    'gameMode' => GameMode::VS_AI->value,
                    'numberOfAIOpponents' => 3,
                    'numberOfCards' => [1, 2, 3]
                ]
            ],
            'valid solo mode' => [
                ['gameMode' => GameMode::SOLO->value]
            ],
            'valid practice mode' => [
                ['gameMode' => GameMode::PRACTICE->value]
            ],
        ];
    }


    //========================================================================
    // validateDabbedNumberInput Tests
    //========================================================================

    #[Test]
    #[DataProvider('provideInvalidDabbedNumberData')]
    public function validateDabbedNumberInputThrowsExceptionForInvalidData(array $data, ErrorCode $expectedErrorCode): void {
        $expectedMessage = ErrorCatalog::get($expectedErrorCode)['message'];

        $this->expectException(AppException::class);
        $this->expectExceptionMessage($expectedMessage);

        GameValidator::validateDabbedNumberInput($data);
    }

    public static function provideInvalidDabbedNumberData(): array {
        return [
            'missing dabbedNumber' => [['cardIndex' => 0], ErrorCode::VALIDATION_GAME_INVALID_REQUEST],
            'empty dabbedNumber' => [['dabbedNumber' => '', 'cardIndex' => 0], ErrorCode::VALIDATION_GAME_INVALID_REQUEST],
            'non-numeric dabbedNumber' => [['dabbedNumber' => 'abc', 'cardIndex' => 0], ErrorCode::VALIDATION_GAME_INVALID_REQUEST],
            'dabbedNumber too low (0)' => [['dabbedNumber' => 0, 'cardIndex' => 0], ErrorCode::VALIDATION_GAME_INVALID_REQUEST],
            'dabbedNumber too high (76)' => [['dabbedNumber' => 76, 'cardIndex' => 0], ErrorCode::VALIDATION_GAME_INVALID_REQUEST],
            'missing cardIndex' => [['dabbedNumber' => 10], ErrorCode::VALIDATION_GAME_INVALID_REQUEST],
            'non-numeric cardIndex' => [['dabbedNumber' => 10, 'cardIndex' => 'a'], ErrorCode::VALIDATION_GAME_INVALID_REQUEST],
            'negative cardIndex' => [['dabbedNumber' => 10, 'cardIndex' => -1], ErrorCode::VALIDATION_GAME_INVALID_REQUEST],
            'cardIndex too high' => [['dabbedNumber' => 10, 'cardIndex' => 5], ErrorCode::VALIDATION_GAME_INVALID_REQUEST],
        ];
    }

    #[Test]
    #[DataProvider('provideValidDabbedNumberData')]
    public function validateDabbedNumberInputSucceedsWithValidData(array $validData): void {
        // This test succeeds if no exception is thrown.
        GameValidator::validateDabbedNumberInput($validData);
        $this->assertTrue(true);
    }
    
    public static function provideValidDabbedNumberData(): array {
        return [
            'valid data with integer values' => [['dabbedNumber' => 25, 'cardIndex' => 2]],
            'valid data with numeric string for cardIndex' => [['dabbedNumber' => 30, 'cardIndex' => '3']],
            'boundary low dabbedNumber' => [['dabbedNumber' => 1, 'cardIndex' => 0]],
            'boundary high dabbedNumber' => [['dabbedNumber' => 75, 'cardIndex' => 4]],
            'boundary low cardIndex' => [['dabbedNumber' => 50, 'cardIndex' => 0]],
            'boundary high cardIndex' => [['dabbedNumber' => 60, 'cardIndex' => 4]],
        ];
    }

}
