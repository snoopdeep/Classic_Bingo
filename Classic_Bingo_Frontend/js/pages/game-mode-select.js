// ============================================================================
// FILE: js/pages/game-mode-select.ts - Game Mode Selection Page (Refactored)
// ============================================================================
import { Modal } from '../modal';
export class GameModeSelectPage {
    async init() {
        console.log('[GAME MODE] Initializing game mode selection page');
        this.renderModeSelection();
        this.attachEventListeners();
    }
    renderModeSelection() {
        const container = document.getElementById('app');
        if (!container)
            return;
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
    createModeCard(mode, title, description) {
        const [icon, ...restTitle] = title.split(' ');
        return `
            <div class="mode-card" data-mode="${mode}">
                <div class="mode-card-icon">${icon}</div>
                <h3>${restTitle.join(' ')}</h3>
                <p>${description}</p>
            </div>
        `;
    }
    attachEventListeners() {
        document.querySelectorAll('.mode-card').forEach(card => {
            card.addEventListener('click', (e) => {
                const mode = e.currentTarget.dataset.mode;
                if (mode) {
                    this.selectMode(mode);
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
    selectMode(mode) {
        console.log('[GAME MODE] Selected mode:', mode);
        if (mode === 'multiplayer') {
            location.href = '/multiplayer-queue.html';
            return;
        }
        sessionStorage.setItem('selectedGameMode', mode);
        location.href = '/game-config.html';
    }
}
