// ============================================================================
// FILE: js/game/storage.ts - Game-specific Storage Manager
// ============================================================================
import { GameSessionData, WinnerInfo } from './types';

const GAME_SESSION_KEY = 'gameSessionData';

export class GameStorage {
    static saveGameSession(data: GameSessionData): void {
        try {
            localStorage.setItem(GAME_SESSION_KEY, JSON.stringify(data));
            console.log('[GAME STORAGE] Game session saved:', data.sessionId);
        } catch (error) {
            console.error('[GAME STORAGE] Failed to save game session:', error);
        }
    }

    static getGameSession(): GameSessionData | null {
        try {
            const data = localStorage.getItem(GAME_SESSION_KEY);
            return data ? JSON.parse(data) : null;
        } catch (error) {
            console.error('[GAME STORAGE] Failed to retrieve game session:', error);
            return null;
        }
    }

    static updateNumberCalled(number: number): void {
        const session = this.getGameSession();
        if (session) {
            session.numberCalledSoFar.push(number);
            this.saveGameSession(session);
        }
    }

    static updateCardDaub(cardIndex: number, cellIndex: number): void {
        const session = this.getGameSession();
        if (session && session.bingoCards[cardIndex]) {
            session.bingoCards[cardIndex].daubed[cellIndex] = 1;
            this.saveGameSession(session);
        }
    }

    static markGameOver(winners: WinnerInfo[]): void {
    const session = this.getGameSession();
    if (session) {
        session.isGameOver = true;
        session.winners = winners;
        this.saveGameSession(session);
    }
}

    static clearGameSession(): void {
        localStorage.removeItem(GAME_SESSION_KEY);
        console.log('[GAME STORAGE] Game session cleared');
    }
}