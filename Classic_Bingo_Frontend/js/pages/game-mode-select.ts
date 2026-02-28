// ============================================================================
// FILE: js/pages/game-mode-select.ts - Game Mode Selection Page (Refactored)
// ============================================================================
import { Modal } from '../modal';
import { GameMode } from '../game/types';

declare global {
    interface Window {
        sessionStorage: Storage;
        location: Location;
    }
}

export class GameModeSelectPage {
    async init(): Promise<void> {
        console.log('[GAME MODE] Initializing game mode selection page');
        this.renderModeSelection();
        this.attachEventListeners();
    }

    private renderModeSelection(): void {
    const container = document.getElementById('app');
    if (!container) return;

    container.innerHTML = `
        <div class="game-mode-page">
            <div class="game-mode-header">
                <h1>SELECT GAME MODE</h1>
            </div>
            <div class="mode-grid">
                ${this.createModeCard('vs_ai', '🤖 vs AI', 'Play against computer opponents')}
                ${this.createModeCard('multiplayer', '🌐 Multiplayer', 'Quick match with other players')}
                ${this.createModeCard('solo', '🎯 Solo', 'Casual play with 1-4 cards')}
                ${this.createModeCard('practice', '📚 Practice', 'Practice specific patterns')}
                ${this.createModeCard('pvp', '👥 PvP', 'Create room & invite friends')}
                ${this.createModeCard('tournament', '🏆 Tournament', 'Compete in tournaments')}
            </div>
            <div class="settings-button-wrapper">
                <button id="settings-btn" class="mode-control-btn">⚙️ Settings</button>
            </div>
            <div class="back-button-wrapper">
                <button id="back-btn" class="mode-control-btn">← Back</button>
            </div>
        </div>
    `;
}

    private createModeCard(mode: string, title: string, description: string): string {
        const [icon, ...restTitle] = title.split(' ');
        
        return `
            <div class="mode-card" data-mode="${mode}">
                <div class="mode-card-icon">${icon}</div>
                <h3>${restTitle.join(' ')}</h3>
                <p>${description}</p>
            </div>
        `;
    }

    private attachEventListeners(): void {
        document.querySelectorAll('.mode-card').forEach(card => {
            card.addEventListener('click', (e) => {
                const mode = (e.currentTarget as HTMLElement).dataset.mode;
                if (mode) {
                    this.selectMode(mode as GameMode);
                }
            });
            // NOTE: Hover effects removed here and moved to CSS using :hover pseudo-class.
        });

        document.getElementById('back-btn')?.addEventListener('click', () => {
            window.location.href = '/index.html';
        });

        document.getElementById('settings-btn')?.addEventListener('click', () => {
            Modal.show({ type: 'settings', message: '' });
        });
    }

    private selectMode(mode: GameMode): void {
        console.log('[GAME MODE] Selected mode:', mode);
        
        if (mode === 'multiplayer') {
            location.href = '/multiplayer-queue.html';
            return;
        }
        
        sessionStorage.setItem('selectedGameMode', mode);
        location.href = '/game-config.html';
    }
}