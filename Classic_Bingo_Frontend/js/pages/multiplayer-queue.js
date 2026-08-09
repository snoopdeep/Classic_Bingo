// ============================================================================
// FILE: js/pages/multiplayer-queue.ts - COMPLETE REWRITE
// ============================================================================
import { GameAPI } from "../game/api";
import { GameStorage } from "../game/storage";
export class MultiplayerQueuePage {
    constructor() {
        this.sessionId = '';
        this.statusPolling = null;
    }
    async init() {
        this.showCardSelection();
    }
    showCardSelection() {
        const container = document.getElementById('app');
        if (!container)
            return;
        container.innerHTML = `
            <div class="multiplayer-queue-container">
                <h1>Multiplayer Bingo</h1>
                <div class="card-selection">
                    <label>Select Number of Cards (1-4):</label>
                    <div class="counter-controls">
                        <button id="decrease-cards" class="counter-button">−</button>
                        <span id="card-count" class="counter-display">1</span>
                        <button id="increase-cards" class="counter-button">+</button>
                    </div>
                </div>
                <button id="join-queue-btn" class="btn-primary">Find Match</button>
                <button id="back-btn" class="back-btn">← Back</button>
            </div>
        `;
        let cardCount = 1;
        document.getElementById('decrease-cards')?.addEventListener('click', () => {
            if (cardCount > 1) {
                cardCount--;
                document.getElementById('card-count').textContent = cardCount.toString();
            }
        });
        document.getElementById('increase-cards')?.addEventListener('click', () => {
            if (cardCount < 4) {
                cardCount++;
                document.getElementById('card-count').textContent = cardCount.toString();
            }
        });
        document.getElementById('join-queue-btn')?.addEventListener('click', async () => {
            await this.joinQueue(cardCount);
        });
        document.getElementById('back-btn')?.addEventListener('click', () => {
            window.location.href = '/game-mode.html';
        });
    }
    async joinQueue(numberOfCards) {
        try {
            // Show loading state
            const btn = document.getElementById('join-queue-btn');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Finding Match...';
            }
            const response = await GameAPI.joinMultiplayerQueue(numberOfCards);
            if (response.success) {
                this.sessionId = response.data.sessionId;
                // Store only player's own cards with their actual server cardIds**
                const playerCards = response.data.sessionData.bingoCards;
                // Store session data
                const sessionData = {
                    sessionId: this.sessionId,
                    bingoCards: playerCards,
                    callInterval: response.data.sessionData.callInterval,
                    numberCalledSoFar: [],
                    isGameOver: false,
                    winners: []
                };
                GameStorage.saveGameSession(sessionData);
                console.log('[MULTIPLAYER] Joined queue, sessionId:', this.sessionId);
                this.showMatchmaking();
                this.startStatusPolling();
            }
        }
        catch (error) {
            console.error('[MULTIPLAYER] Failed to join queue:', error);
            alert(error.message || 'Failed to join matchmaking queue. Please try again.');
            // Reset button
            const btn = document.getElementById('join-queue-btn');
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Find Match';
            }
        }
    }
    showMatchmaking() {
        const container = document.getElementById('app');
        if (!container)
            return;
        container.innerHTML = `
            <div class="multiplayer-queue-container">
                <h1>Finding Match...</h1>
                <div class="matchmaking-status">
                    <div class="loading-spinner"></div>
                    <p id="status-message">Looking for available room...</p>
                    <div class="timer-display">
                        <p>Game starts in: <span id="timer">--</span>s</p>
                    </div>
                    <div class="players-waiting" id="players-list">
                        <p>Waiting for players...</p>
                    </div>
                </div>
                <button id="cancel-btn" class="btn-secondary">Cancel</button>
            </div>
        `;
        document.getElementById('cancel-btn')?.addEventListener('click', () => {
            this.stopStatusPolling();
            GameStorage.clearGameSession();
            window.location.href = '/game-mode.html';
        });
    }
    startStatusPolling() {
        this.statusPolling = window.setInterval(async () => {
            await this.pollStatus();
        }, 1000); // todo :: Poll every 2 seconds
        // Initial poll
        this.pollStatus();
    }
    stopStatusPolling() {
        if (this.statusPolling) {
            clearInterval(this.statusPolling);
            this.statusPolling = null;
        }
    }
    async pollStatus() {
        try {
            const response = await GameAPI.getMultiplayerStatus(this.sessionId);
            if (response.success) {
                const { participants, currentCount, maxPlayers, timeRemaining, isActive } = response.data;
                console.log('[MULTIPLAYER] Status update:', { currentCount, timeRemaining, isActive });
                // Update status message
                const statusMsg = document.getElementById('status-message');
                if (statusMsg) {
                    if (currentCount === 1) {
                        statusMsg.textContent = 'Waiting for players to join...';
                    }
                    else {
                        statusMsg.textContent = `${currentCount}/${maxPlayers} players ready`;
                    }
                }
                // Update timer
                const timer = document.getElementById('timer');
                if (timer) {
                    timer.textContent = timeRemaining.toString();
                }
                // Update players list
                const playersList = document.getElementById('players-list');
                if (playersList) {
                    if (participants.length === 0) {
                        playersList.innerHTML = '<p>Waiting for players...</p>';
                    }
                    else {
                        playersList.innerHTML = `
                            <p>Players in room:</p>
                            ${participants.map((p, index) => `<div class="player-item">Player ${index + 1}</div>`).join('')}
                        `;
                    }
                }
                // Check if game started
                if (isActive) {
                    console.log('[MULTIPLAYER] Game started! Redirecting to game...');
                    this.stopStatusPolling();
                    // Fetch fresh session data before redirecting
                    const freshSession = await GameAPI.getMultiplayerStatus(this.sessionId);
                    // Update stored session with latest data
                    const existingSession = GameStorage.getGameSession();
                    if (existingSession) {
                        GameStorage.saveGameSession({
                            ...existingSession,
                            isGameOver: false,
                            winners: []
                        });
                    }
                    // Store config for game page
                    sessionStorage.setItem('gameConfig', JSON.stringify({
                        gameMode: 'multiplayer',
                        sessionId: this.sessionId
                    }));
                    window.location.href = '/game.html';
                }
            }
        }
        catch (error) {
            console.error('[MULTIPLAYER] Status polling error:', error);
            // Check if session expired
            if (error.message && error.message.includes('expired')) {
                this.stopStatusPolling();
                GameStorage.clearGameSession();
                alert('Session expired - not enough players joined in time');
                window.location.href = '/game-mode.html';
            }
        }
    }
}
