// ============================================================================
// FILE: js/game/api.ts - Game API Client
// ============================================================================
import { API } from '../api';
export class GameAPI {
    static async startGame(config) {
        // For practice mode, send flat structure
        if (config.gameMode === 'practice') {
            return API.request('/api/v1/game/start', {
                method: 'POST',
                body: {
                    gameMode: 'practice',
                    winningPattern: config.winningPattern,
                    ballSpeed: config.ballSpeed,
                    autoDaub: config.autoDaub,
                    numberOfCards: config.userCards || config.numberOfCards[0]
                }
            });
        }
        // For solo mode, send simple structure
        if (config.gameMode === 'solo') {
            return API.request('/api/v1/game/start', {
                method: 'POST',
                body: {
                    gameMode: 'solo',
                    numberOfCards: config.numberOfCards[0]
                }
            });
        }
        // Regular modes (vs_ai, pvp, multiplayer)
        return API.request('/api/v1/game/start', {
            method: 'POST',
            body: {
                gameMode: config.gameMode,
                numberOfAIOpponents: config.numberOfAIOpponents,
                numberOfCards: config.numberOfCards
            }
        });
    }
    static async getNextNumber(sessionId) {
        return API.request(`/api/v1/game/${sessionId}/next-number`, {
            method: 'GET'
        });
    }
    /**
     * Daub a number on a bingo card
     * Uses the shared API client that handles authentication automatically
     */
    static async daubNumber(sessionId, daubedNumber, cardIndex) {
        return API.request(`/api/v1/game/${sessionId}/daubedNumber`, {
            method: 'POST',
            body: {
                daubedNumber,
                cardIndex
            }
        });
    }
    static async claimBingo(sessionId, cardIndex) {
        return API.request(`/api/v1/game/${sessionId}/bingo`, {
            method: 'POST',
            body: {
                cardIndex
            }
        });
    }
    // complete game and persist results
    static async completeGame(sessionId) {
        return API.request(`/api/v1/game/${sessionId}/complete`, {
            method: 'POST',
            body: {}
        });
    }
    // MULTIPLAYER API METHODS
    static async joinMultiplayerQueue(numberOfCards) {
        return API.request('/api/v1/multiplayer/queue', {
            method: 'POST',
            body: {
                numberOfCards
            }
        });
    }
    static async getMultiplayerStatus(sessionId) {
        return API.request(`/api/v1/multiplayer/${sessionId}/status`, {
            method: 'GET'
        });
    }
}
