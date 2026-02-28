// ============================================================================
// FILE: js/game/types.ts - Game Type Definitions
// ============================================================================

export interface WinnerInfo {
    userId: string;
    cardIndex: number;
    timestamp: number;
    type: 'ai' | 'user';
}

export interface BingoCard {
    grid: (number | string)[];
    daubed: number[];
    cardId: number;
}

export interface GameSessionData {
    sessionId: string;
    bingoCards: BingoCard[];
    callInterval: number;
    numberCalledSoFar: number[];
    isGameOver: boolean;
    winners: WinnerInfo[];
}

export interface NextNumberResponse {
    success: boolean;
    data: {
        calledNumbers: number[];
        isGameOver: boolean;
        winner: WinnerInfo[];
        autoDaub?: Array<{ cardIndex: number; cellIndex: number }>;
    };
}

export interface DaubResponse {
    success: boolean;
    data: {
        sessionId: string;
        userId: string;
        cardIndex: number;
        daubedNumber: number;
        daubedIndex: number;
        success: boolean;
    };
}

export interface BingoResponse {
    success: boolean;
    data?: {
        claimValid?: boolean;
        isGameOver?: boolean;
        message?: string;
        winners?: WinnerInfo[];
        data?: {
            isGameOver?: boolean;
            winners?: WinnerInfo[];
        };
    };
    message?: string;
    code?: string;
}

export type GameMode = 'vs_ai' | 'pvp' | 'multiplayer' | 'solo' | 'practice' | 'tournament';

export type WinningPattern = 'standard' |'four_corners';
export type BallSpeed = 'relaxed' | 'normal' | 'fast' | 'turbo';

export interface GameConfig {
    gameMode: GameMode;
    numberOfAIOpponents: number;
    numberOfCards: number[]; 
    winningPattern?: WinningPattern; 
    ballSpeed?: BallSpeed;           
    autoDaub?: boolean;              
}