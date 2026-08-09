<?php

namespace App\Validators;

use App\Enums\ErrorCode;
use App\Constants\GameEntitiesConstants;
use App\Enums\GameMode;
use App\Handlers\AppException;

/**
 * GameValidator - Pure input validation for game-related endpoints.
 */
class GameValidator
{
    // CONSTANT 
    private const GAME_MODE = 'gameMode';
    private const DAUBED_NUMBER = 'dabbedNumber';
    private const CARD_INDEX = 'cardIndex';
    /**
     * Validate the input for starting a new game.
     *
     * @param array<string, mixed> $data The raw request body.
     * @return void
     * @throws AppException If validation fails.
     */
    public static function validateStartGameInput(array $data): void {
        if (empty($data[self::GAME_MODE])) {
            throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, ['field' => self::GAME_MODE, 'reason' => 'required']);
        }

        // Check if the provided gameMode is a valid enum case
        if (GameMode::tryFrom($data[self::GAME_MODE]) === null) {
            throw new AppException(ErrorCode::VALIDATION_GAME_MODE_UNSUPPORTED, ['mode' => $data[self::GAME_MODE]]);
        }

        // Mode-specific validation
        // 1: vs_ai mode input validation
        if ($data[self::GAME_MODE] === GameMode::VS_AI->value) {
            self::validateAIModeInputData($data);
        } 
        // 2: practice mode input validation
        if($data[self::GAME_MODE] === GameMode::PRACTICE->value){
            self::validatePracticeModeInputData($data);
        }
    }

private static function validateSoloModeInputData(array $requestData):void{

     if (!isset($requestData[GameEntitiesConstants::NUMBER_OF_CARDS])) {
                throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, [
                    'field' => GameEntitiesConstants::NUMBER_OF_CARDS
                ]);
            }
            $cardCount = $requestData[GameEntitiesConstants::NUMBER_OF_CARDS];
            if (!is_int($cardCount) || $cardCount < 1 || $cardCount > 4) {
                throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, [
                    'field' => GameEntitiesConstants::NUMBER_OF_CARDS,
                    'value' => $cardCount,
                    'constraint' => 'Must be integer between 1 and 4'
                ]);
            }
}
public static function validatePvPCreateInput(array $data): void {
    if (!isset($data[GameEntitiesConstants::NUMBER_OF_CARDS]) || !is_numeric($data[GameEntitiesConstants::NUMBER_OF_CARDS])) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, [
            'reason' => 'cardCount is required'
        ]);
    }
    
    $cardCount = (int)$data[GameEntitiesConstants::NUMBER_OF_CARDS];
    if ($cardCount < 1 || $cardCount > 6) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, [
            'reason' => 'cardCount must be 1-6'
        ]);
    }
}

public static function validatePvPJoinInput(array $data): void {
    if (!isset($data['joinCode']) || empty(trim($data['joinCode']))) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, [
            'reason' => 'joinCode is required'
        ]);
    }
    
    if (!isset($data['numberOfCards']) || !is_numeric($data['numberOfCards'])) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, [
            'reason' => 'cardCount is required'
        ]);
    }
    
    $cardCount = (int)$data['numberOfCards'];
    if ($cardCount < 1 || $cardCount > 6) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, [
            'reason' => 'cardCount must be 1-6'
        ]);
    }
}

    // vs_ai mode input validation 
    private static function validateAIModeInputData(array $data): void{
        if (empty($data['numberOfAIOpponents']) || !is_numeric($data['numberOfAIOpponents'])) {
                throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, ['field' => 'numberOfAIOpponents', 'reason' => 'required and must be a number']);
            }
            if (empty($data['numberOfCards']) || !is_array($data['numberOfCards'])) {
                 throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, ['field' => 'numberOfCards', 'reason' => 'required and must be an array']);
            }
    }

    private static function validatePracticeModeInputData(array $data):void{
        // Validate winning pattern
        if (!isset($data['winningPattern']) || !is_string($data['winningPattern'])) {
            throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, 
                ['field' => 'winningPattern', 'reason' => 'Required string']);
        }
        // should go into the business logic
        // if (!in_array($data['winningPattern'], $availablePatterns)) {
        // throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST,
        //     ['field' => 'winningPattern', 'reason' => 'Invalid pattern. Available: ' . implode(', ', $availablePatterns)]);
        //  }

        // Validate ball speed
    if (!isset($data['ballSpeed']) || !is_string($data['ballSpeed'])) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST,
            ['field' => 'ballSpeed', 'reason' => 'Required string']);
    }

    // $validSpeeds = array_keys($gameConfig->getPracticeBallSpeeds());
    // if (!in_array($data['ballSpeed'], $validSpeeds)) {
    //     throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST,
    //         ['field' => 'ballSpeed', 'reason' => 'Invalid speed. Available: ' . implode(', ', $validSpeeds)]);
    // }

    // Validate auto-daub
    if (!isset($data['autoDaub']) || !is_bool($data['autoDaub'])) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST,
            ['field' => 'autoDaub', 'reason' => 'Required boolean']);
    }

    // Validate number of cards
    if (!isset($data['numberOfCards']) || !is_int($data['numberOfCards'])) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST,
            ['field' => 'numberOfCards', 'reason' => 'Required integer']);
    }

    // $maxCards = $practiceConfig['max_cards'] ?? 4;
    // if ($data['numberOfCards'] < 1 || $data['numberOfCards'] > $maxCards) {
    //     throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST,
    //         ['field' => 'numberOfCards', 'reason' => "Must be between 1 and {$maxCards}"]);
    // }


    }


/**
     * Validate the input for dabbing a number.
     *
     * @param array<string, mixed> $data The raw request body.
     * @return void
     * @throws AppException If validation fails.
 */
public static function validateDabbedNumberInput(array $data): void {

    if (!isset($data[GameEntitiesConstants::DAUBED_NUMBER]) || $data[GameEntitiesConstants::DAUBED_NUMBER] === '') {
        throw new AppException(
            ErrorCode::VALIDATION_GAME_INVALID_REQUEST, 
            ['field' => 'dabbedNumber', 'reason' => 'required and cannot be empty']
        );
    }

    if (!isset($data[self::CARD_INDEX]) || !is_numeric($data[self::CARD_INDEX])) {
        throw new AppException(
            ErrorCode::VALIDATION_GAME_INVALID_REQUEST, 
            ['field' => 'cardIndex', 'reason' => 'required and must be a number']
        );
    }

    $dabbedNumber = $data[GameEntitiesConstants::DAUBED_NUMBER];
    $cardIndex = (int)$data[self::CARD_INDEX];
    
    // Validate dabbedNumber is between 1-75
    if (!is_numeric($dabbedNumber) || $dabbedNumber < 1 || $dabbedNumber > 75) {
        throw new AppException(
            ErrorCode::VALIDATION_GAME_INVALID_REQUEST, 
            ['field' => 'dabbedNumber', 'reason' => 'must be a number between 1 and 75']
        );
    }

    // Validate cardIndex is non-negative
    if ($cardIndex < 0) {
        throw new AppException(
            ErrorCode::VALIDATION_GAME_INVALID_REQUEST, 
            ['field' => 'cardIndex', 'reason' => 'must be a non-negative number']
        );
    }
    // todo :: // Adjust number based on your system limits
    // Validate cardIndex is within reasonable bounds (upper bound check)
    // if ($cardIndex > 4) { 
    //     throw new AppException(
    //         ErrorCode::VALIDATION_GAME_INVALID_REQUEST, 
    //         ['field' => 'cardIndex', 'reason' => 'cardIndex is unreasonably large']
    //     );
    // }
}


public static function validateBingoClaimInput(array $data): void {
    
    if (!isset($data[self::CARD_INDEX]) || !is_numeric($data[self::CARD_INDEX])) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST,
            ['field' => 'cardIndex', 'reason' => 'required and must be a number']);
    }
    
    $cardIndex = (int)$data[self::CARD_INDEX];
    if ($cardIndex < 0) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST,
            ['field' => 'cardIndex', 'reason' => 'must be non-negative']);
    }
}

public static function validateMultiplayerQueueInput(array $data): void {
    if (!isset($data[GameEntitiesConstants::NUMBER_OF_CARDS]) || !is_numeric($data[GameEntitiesConstants::NUMBER_OF_CARDS])) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, [
            'reason' => 'numberOfCards is required'
        ]);
    }
    
    $cardCount = (int)$data[GameEntitiesConstants::NUMBER_OF_CARDS];
    if ($cardCount < 1 || $cardCount > 6) {
        throw new AppException(ErrorCode::VALIDATION_GAME_INVALID_REQUEST, [
            'reason' => 'numberOfCards must be 1-6'
        ]);
    }
}

}