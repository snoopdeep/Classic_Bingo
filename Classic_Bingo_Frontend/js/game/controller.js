// ============================================================================
// FILE: js/game/controller.ts - UPDATED Controller
// ============================================================================
import { GameUI } from './ui';
import { GameAPI } from './api';
import { GameStorage } from './storage';
import { Storage } from '../storage';
export class GameController {
    constructor(containerId) {
        this.numberCallInterval = null;
        this.sessionData = null;
        this.gameUI = new GameUI(containerId);
    }
    async startGame(config) {
        try {
            console.log('[GAME] Starting game with config:', config);
            this.showLoadingMessage('Hold on, starting the game...');
            const response = await GameAPI.startGame(config);
            if (!response.success) {
                throw new Error('Failed to start game');
            }
            this.sessionData = {
                sessionId: response.data.sessionId,
                bingoCards: response.data.sessionData.bingoCards,
                callInterval: response.data.sessionData.callInterval,
                numberCalledSoFar: [],
                isGameOver: false,
                winners: []
            };
            GameStorage.saveGameSession(this.sessionData);
            this.gameUI.showCountdown(() => this.initGameScreen());
        }
        catch (error) {
            console.error('[GAME] Failed to start game:', error);
            alert('Failed to start game. Please try again.');
            window.location.href = '/index.html';
        }
    }
    //method for multiplayer
    async startMultiplayerGame(sessionId) {
        try {
            console.log('[GAME] Starting multiplayer game with sessionId:', sessionId);
            // Load session data from storage (saved during queue join)
            this.sessionData = GameStorage.getGameSession();
            if (!this.sessionData || this.sessionData.sessionId !== sessionId) {
                throw new Error('Session data not found');
            }
            // Show countdown and initialize game screen
            this.gameUI.showCountdown(() => this.initGameScreen());
        }
        catch (error) {
            console.error('[GAME] Failed to start multiplayer game:', error);
            alert('Failed to start game. Please try again.');
            window.location.href = '/index.html';
        }
    }
    showLoadingMessage(message) {
        const container = document.getElementById('game-container');
        if (container) {
            container.innerHTML = `
                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);">
                    <div style="width: 60px; height: 60px; border: 5px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    <div style="color: white; font-size: 20px; margin-top: 20px;">${message}</div>
                </div>
            `;
        }
    }
    initGameScreen() {
        if (!this.sessionData)
            return;
        this.gameUI.renderGameScreen(this.sessionData.bingoCards, this.sessionData.numberCalledSoFar);
        this.attachEventListeners();
        this.startNumberCallingLoop();
    }
    attachEventListeners() {
        // Cell click for daubing
        document.querySelectorAll('.bingo-cell').forEach(cell => {
            cell.addEventListener('click', (e) => this.handleCellClick(e));
        });
        // Individual card bingo buttons - **FIX: Use cardId**
        document.querySelectorAll('.card-bingo-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const target = e.currentTarget;
                const cardId = parseInt(target.dataset.cardId || '0');
                this.handleBingoClaim(cardId);
            });
        });
        // Exit button
        document.getElementById('exit-game-btn')?.addEventListener('click', () => {
            if (confirm('Are you sure you want to exit the game?')) {
                this.stopNumberCallingLoop();
                GameStorage.clearGameSession();
                window.location.href = '/index.html';
            }
        });
    }
    startNumberCallingLoop() {
        if (!this.sessionData)
            return;
        const interval = this.sessionData.callInterval * 1000;
        this.numberCallInterval = window.setInterval(async () => {
            await this.fetchNextNumber();
        }, interval);
        this.fetchNextNumber();
    }
    stopNumberCallingLoop() {
        if (this.numberCallInterval) {
            clearInterval(this.numberCallInterval);
            this.numberCallInterval = null;
        }
    }
    async fetchNextNumber() {
        if (!this.sessionData || this.sessionData.isGameOver) {
            this.stopNumberCallingLoop();
            return;
        }
        try {
            const response = await GameAPI.getNextNumber(this.sessionData.sessionId);
            if (response.success) {
                const newNumber = response.data.calledNumbers[0];
                this.sessionData.numberCalledSoFar.push(newNumber);
                GameStorage.updateNumberCalled(newNumber);
                this.gameUI.updateNumberDisplay(this.sessionData.numberCalledSoFar);
                // Handle auto-daubed cells (practice mode with auto-daub ON)
                if (response.data.autoDaub && Array.isArray(response.data.autoDaub)) {
                    console.log('[GAME] Processing auto-daubed cells for number:', newNumber);
                    response.data.autoDaub.forEach((daub) => {
                        // **FIX: cardIndex from server is actually cardId**
                        const serverCardId = daub.cardIndex;
                        const localIndex = this.sessionData.bingoCards.findIndex(c => c.cardId === serverCardId);
                        if (localIndex === -1)
                            return;
                        // Update session data using local index
                        if (this.sessionData && this.sessionData.bingoCards?.[localIndex]) {
                            this.sessionData.bingoCards[localIndex].daubed[daub.cellIndex] = 1;
                        }
                        // Update storage with local index
                        GameStorage.updateCardDaub(localIndex, daub.cellIndex);
                        // Update UI with server cardId
                        this.gameUI.highlightCell(serverCardId, daub.cellIndex);
                        console.log('[GAME] Auto-daubed:', {
                            number: newNumber,
                            serverCardId: serverCardId,
                            localIndex: localIndex,
                            cellIndex: daub.cellIndex
                        });
                    });
                }
                if (response.data.isGameOver) {
                    this.handleGameOver(response.data.winner);
                }
            }
        }
        catch (error) {
            console.error('[GAME] Failed to fetch next number:', error);
        }
    }
    async handleCellClick(event) {
        const cell = event.currentTarget;
        const cardId = parseInt(cell.dataset.cardId || '0');
        const cellIndex = parseInt(cell.dataset.cell || '0');
        const number = cell.dataset.number;
        if (!this.sessionData || number === 'FREE')
            return;
        const daubedNumber = parseInt(number || '0');
        // Find local index for storage update
        const localCardIndex = this.sessionData.bingoCards.findIndex(c => c.cardId === cardId);
        if (localCardIndex === -1) {
            console.error('[GAME] Card not found:', cardId);
            return;
        }
        try {
            const response = await GameAPI.daubNumber(this.sessionData.sessionId, daubedNumber, cardId // Send actual server cardId
            );
            console.log('daub:Response :: ', response);
            // The response structure from backend is: { success: true, data: {...} }
            if (response.success && response.data) {
                // Update local storage using local index
                if (this.sessionData && this.sessionData.bingoCards?.[localCardIndex]) {
                    this.sessionData.bingoCards[localCardIndex].daubed[cellIndex] = 1;
                }
                GameStorage.updateCardDaub(localCardIndex, cellIndex);
                this.gameUI.highlightCell(cardId, cellIndex); // UI uses cardId
                console.log('[GAME] Valid daub:', daubedNumber, {
                    cardId,
                    cellIndex,
                    daubedIndex: response.data.daubedIndex
                });
                return;
            }
            else {
                console.log('[GAME] Invalid daub - unexpected response format:', response);
            }
        }
        catch (error) {
            console.error('[GAME] Failed to daub number:', error);
        }
    }
    async handleBingoClaim(cardId) {
        if (!this.sessionData)
            return;
        console.log(`[GAME] Bingo claimed for card ${cardId}`);
        try {
            // const response = await GameAPI.claimBingo(this.sessionData.sessionId, cardIndex);
            // Send actual server cardId
            const response = await GameAPI.claimBingo(this.sessionData.sessionId, cardId);
            if (response.success || response.data?.claimValid) {
                const winners = response.data?.winners ||
                    response.data?.data?.winners ||
                    response.winners ||
                    [];
                this.handleGameOver(winners);
            }
            else {
                this.gameUI.showWarningModal(response.message || 'No winning pattern detected on this card', () => console.log('[GAME] Continuing game after invalid bingo'));
            }
        }
        catch (error) {
            console.error('[GAME] Failed to claim bingo:', error);
            this.gameUI.showWarningModal(error.message || 'Failed to verify bingo claim', () => console.log('[GAME] Continuing game'));
        }
    }
    async handleGameOver(winners) {
        if (!this.sessionData)
            return;
        this.stopNumberCallingLoop();
        GameStorage.markGameOver(winners);
        const currentUserId = Storage.getUser()?.userId || '';
        const isWin = winners.some(winner => winner.userId === currentUserId && winner.type === 'user');
        // Call completion API to persist results to database
        try {
            console.log('[GAME] Calling completion API for session:', this.sessionData.sessionId);
            const completionResponse = await GameAPI.completeGame(this.sessionData.sessionId);
            if (completionResponse.success) {
                console.log('[GAME] Game results saved to database successfully');
            }
            else {
                console.warn('[GAME] Failed to save game results:', completionResponse.message);
            }
        }
        catch (error) {
            console.error('[GAME] Error calling completion API:', error);
            // Continue to show result modal even if DB update fails
        }
        // Show result modal
        this.gameUI.showResultModal(isWin, winners);
    }
}
