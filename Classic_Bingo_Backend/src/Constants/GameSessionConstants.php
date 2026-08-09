<?php

namespace App\Constants;

/**
 * GameSessionConstants - Defines all magic strings, keys, and values used 
 * within the GameSessionData class to prevent typos and improve refactoring.
 */
final class GameSessionConstants {
    // --- JSON/ARRAY KEYS (PROPERTIES) ---
    public const KEY_SESSION_ID = 'sessionId';
    public const KEY_SESSION_TYPE = 'sessionType';
    public const KEY_IS_ACTIVE = 'isActive';
    public const KEY_CREATED_AT = 'createdAt';
    public const KEY_STARTED_AT = 'startedAt';
    public const KEY_GAME_END_TIME = 'gameEndTime';

    // Practice mode
    public const KEY_IS_PRACTICE_MODE = 'isPracticeMode';
    public const KEY_PRACTICE_WIN_PATTERN = 'practiceWinPattern';
    public const KEY_PRACTICE_AUTO_DAUB = 'practiceAutoDaub';
    public const KEY_PRACTICE_BALL_SPEED = 'practiceBallSpeed';

    // PvP fields 
    public const HOST_USER_ID = 'hostUserId';
    public const JOIN_CODE = 'joinCode';
    public const MIN_PLAYERS = 'minPlayers';

    // RealTime Multiplayer Mode 
    public const GRACE_END_TIME = 'graceEndTime';

    // Participants/Lobby
    public const KEY_PARTICIPANTS = 'participants';
    public const KEY_MAX_PLAYERS = 'maxPlayers';
    public const KEY_PRICE_POOL = 'pricePool';
    public const KEY_ENTRY_COST = 'entryCost';

    // Number Calling
    public const KEY_NUMBERS_TO_CALL = 'numbersToCall';
    public const KEY_NUMBERS_CALLED_SO_FAR = 'numbersCalledSoFar';
    public const KEY_CURRENT_NUMBER_INDEX = 'currentNumberIndex';
    public const KEY_CALL_INTERVAL = 'callInterval';
    public const KEY_LAST_CALL_TIME = 'lastCallTime';

    // Cards
    public const KEY_BINGO_CARDS = 'bingoCards';
    public const KEY_PLAYER_CARDS = 'playerCards';
    public const KEY_CARD_GRID = 'grid';
    public const KEY_CARD_DAUBED = 'daubed';
    
    // Results
    public const KEY_WINNERS = 'winners';
    public const KEY_GAME_RESULTS = 'gameResults';
    public const KEY_PENDING_AI_CLAIMS = 'pendingAIClaims';
    
    // --- NESTED ARRAY KEYS (e.g., within participants array) ---
    public const KEY_PARTICIPANT_TYPE = 'type';
    public const KEY_PARTICIPANT_NUM_CARDS = 'numberOfCards';
    public const KEY_PARTICIPANT_JOINED_AT = 'joinedAt';

    // --- DISCRETE STRING VALUES (TYPES & PATTERNS) ---
    // Session Types
    public const TYPE_PVP = 'pvp';
    public const TYPE_VS_AI = 'vs_ai';
    public const TYPE_SOLO = 'solo';
    public const TYPE_PRACTICE = 'practice';
    public const TYPE_TOURNAMENT = 'tournament';

    // Participant Types
    public const PARTICIPANT_TYPE_USER = 'user';
    public const PARTICIPANT_TYPE_AI = 'ai';

    // Practice Win Patterns
    public const WIN_PATTERN_STANDARD = 'standard';
    public const WIN_PATTERN_FOUR_CORNERS = 'four_corners';
    public const WIN_PATTERN_X = 'X';
}
