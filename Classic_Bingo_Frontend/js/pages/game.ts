// ============================================================================
// FILE: js/pages/game.ts - Main Game Page
// ============================================================================
import { GameController } from '../game/controller';
import { GameConfig } from '../game/types';

export class GamePage {
    private gameController: GameController;

    constructor() {
        this.gameController = new GameController('game-container');
    }

    async init(): Promise<void> {
        const configStr = sessionStorage.getItem('gameConfig');
        if (!configStr) {
            console.error('[GAME PAGE] No game config found');
            window.location.href = '/game-mode.html';
            return;
        }

        const config: GameConfig = JSON.parse(configStr);
        console.log('[GAME PAGE] Starting game with config:', config);

        // Add necessary CSS animations
        this.injectStyles();

        // Start the game
        // await this.gameController.startGame(config);
        
        // Check if multiplayer mode with existing session
        if (config.gameMode === 'multiplayer' && (config as any).sessionId) {
            await this.gameController.startMultiplayerGame((config as any).sessionId);
        } else {
            // Start regular game
            await this.gameController.startGame(config);
        }
    }

    private injectStyles(): void {
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
            @keyframes pulse {
                0%, 100% { transform: scale(1); opacity: 1; }
                50% { transform: scale(1.1); opacity: 0.8; }
            }
            @keyframes slideIn {
                from { transform: translateY(-20px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            @keyframes scaleIn {
                from { transform: scale(0.8); opacity: 0; }
                to { transform: scale(1); opacity: 1; }
            }
            @keyframes checkmark {
                from { transform: scale(0); }
                to { transform: scale(1); }
            }
            .bingo-cell:hover:not([data-number="FREE"]) {
                transform: scale(1.05);
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            }
            #call-bingo-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 12px 28px rgba(240, 147, 251, 0.5);
            }
        `;
        document.head.appendChild(style);
    }
}