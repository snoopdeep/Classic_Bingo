// ============================================================================
// FILE: js/pages/game-config.ts - Game Configuration Page (Refactored)
// ============================================================================
import { Modal } from '../modal';
export class GameConfigPage {
    constructor() {
        this.aiOpponents = 1;
        this.userCards = 1;
        this.aiCardCounts = [1];
        this.practiceWinningPattern = 'standard';
        this.practiceBallSpeed = 'normal';
        this.practiceAutoDaub = false;
    }
    async init() {
        const mode = sessionStorage.getItem('selectedGameMode');
        if (!mode) {
            window.location.href = '/game-mode.html';
            return;
        }
        this.selectedMode = mode;
        console.log('[GAME CONFIG] Initializing configuration for mode:', mode);
        // Initial render and setup
        this.renderConfigPage();
        this.attachEventListeners();
        // Ensure current state is reflected in practice controls on first render
        if (this.selectedMode === 'practice') {
            this.initializePracticeControls();
        }
    }
    initializePracticeControls() {
        // Set initial selected pattern
        document.getElementById('winning-pattern').value = this.practiceWinningPattern;
        // Set initial auto-daub state
        document.getElementById('auto-daub-toggle').checked = this.practiceAutoDaub;
        // Highlight initial speed button
        document.querySelector(`.speed-btn[data-speed="${this.practiceBallSpeed}"]`)?.classList.add('selected');
    }
    // private renderConfigPage(): void {
    //     const container = document.getElementById('app');
    //     if (!container) return;
    //     // Using CSS classes for layout and styling
    //     container.innerHTML = `
    //         <div class="config-page-container">
    //             <div class="config-header">
    //                 <h1>Configure ${this.getModeTitle()}</h1>
    //                 ${this.selectedMode === 'practice' ? this.renderPracticeBanner() : ''}
    //             </div>
    //             <div class="config-card">
    //                 ${this.selectedMode === 'practice' ? this.renderPracticeConfig() : 
    //                   this.selectedMode === 'vs_ai' ? this.renderAIConfig() : 
    //                   this.renderBasicConfig()}
    //                 <div class="config-section" style="margin-top: 30px; text-align: center;">
    //                     <button id="continue-btn" class="btn btn-primary btn-large">
    //                         ${this.selectedMode === 'practice' ? 'Start Practice' : 'Continue'}
    //                     </button>
    //                 </div>
    //             </div>
    //             <div class="settings-wrapper">
    //                 <button id="settings-btn" class="config-control-btn">⚙️ Settings</button>
    //             </div>
    //             <div class="back-wrapper">
    //                 <button id="back-btn" class="config-control-btn">← Back</button>
    //             </div>
    //         </div>
    //     `;
    // }
    renderConfigPage() {
        const container = document.getElementById('app');
        if (!container)
            return;
        container.innerHTML = `
        <div class="config-page-container">
            <div class="config-header">
                <h1>Configure ${this.getModeTitle()}</h1>
                ${this.selectedMode === 'practice' ? this.renderPracticeBanner() : ''}
                ${this.selectedMode === 'solo' ? this.renderSoloBanner() : ''}
            </div>
            <div class="config-card">
                ${this.selectedMode === 'practice' ? this.renderPracticeConfig() :
            this.selectedMode === 'solo' ? this.renderSoloConfig() :
                this.selectedMode === 'vs_ai' ? this.renderAIConfig() :
                    this.renderBasicConfig()}
                
                <div class="config-section" style="margin-top: 30px; text-align: center;">
                    <button id="continue-btn" class="btn btn-primary btn-large">
                        ${this.selectedMode === 'practice' ? 'Start Practice' :
            this.selectedMode === 'solo' ? 'Start Solo Game' : 'Continue'}
                    </button>
                </div>
            </div>
            <div class="settings-wrapper">
                <button id="settings-btn" class="config-control-btn">⚙️ Settings</button>
            </div>
            <div class="back-wrapper">
                <button id="back-btn" class="config-control-btn">← Back</button>
            </div>
        </div>
    `;
    }
    renderSoloBanner() {
        return `
        <div class="practice-banner"">
            <p>
                🎯 SOLO MODE: No Entry Fee • No Stats • Just Relax & Play
            </p>
        </div>
    `;
    }
    renderSoloConfig() {
        return `
        <div class="config-section">
            <label class="config-label">
                🎴 Number of Cards (1-4)
            </label>
            <div class="counter-controls">
                <button id="decrease-user" class="counter-button decrease">−</button>
                <span id="user-count" class="counter-display">${this.userCards}</span>
                <button id="increase-user" class="counter-button increase">+</button>
            </div>
            <p class="config-description">
                Choose how many cards you want to play with
            </p>
        </div>
    `;
    }
    renderPracticeBanner() {
        return `
            <div class="practice-banner">
                <p>
                    🎓 PRACTICE MODE: No Entry Fee • No Coin Payout • Learn at Your Own Pace
                </p>
            </div>
        `;
    }
    renderPracticeConfig() {
        return `
            <div class="config-section">
                <label class="config-label">
                    🎯 Winning Pattern
                </label>
                <select id="winning-pattern" class="config-select">
                    <option value="standard">Standard (Any 5 in a row)</option>
                    <option value="horizontal">Horizontal Line Only</option>
                    <option value="vertical">Vertical Line Only</option>
                    <option value="diagonal">Diagonal Only</option>
                    <option value="four_corners">Four Corners</option>
                    <option value="x_shape">X Shape</option>
                    <option value="u_shape">U Shape</option>
                    <option value="full_card">Full Card (Blackout)</option>
                </select>
                <p class="config-description">
                    Choose which pattern you want to practice
                </p>
            </div>

            <div class="config-section">
                <label class="config-label">
                    ⚡ Number Calling Speed
                </label>
                <div class="speed-grid">
                    ${this.renderSpeedButton('relaxed', '🐢 Relaxed', '8s')}
                    ${this.renderSpeedButton('normal', '⏱️ Normal', '4s')}
                    ${this.renderSpeedButton('fast', '⚡ Fast', '2s')}
                    ${this.renderSpeedButton('turbo', '🚀 Turbo', '1s')}
                </div>
            </div>

            <div class="config-section toggle-section">
                <div class="toggle-flex">
                    <div>
                        <label class="config-label" style="margin-bottom: 5px;">
                            🤖 Auto-Daub
                        </label>
                        <p class="config-description" style="margin: 0;">
                            Numbers are marked automatically - focus on calling BINGO!
                        </p>
                    </div>
                    <label class="switch">
                        <input type="checkbox" id="auto-daub-toggle">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <div class="config-section">
                <label class="config-label">
                    🎴 Number of Cards (Max 4)
                </label>
                <div class="counter-controls">
                    <button id="decrease-user" class="counter-button decrease">−</button>
                    <span id="user-count" class="counter-display">${this.userCards}</span>
                    <button id="increase-user" class="counter-button increase">+</button>
                </div>
            </div>
        `;
    }
    renderSpeedButton(speed, label, time) {
        // The 'selected' class will be added/removed dynamically by JS on init/click
        return `
            <button 
                class="speed-btn" 
                data-speed="${speed}"
            >
                <span>${label}</span>
                <span class="speed-time">${time}</span>
            </button>
        `;
    }
    getModeTitle() {
        const titles = {
            'vs_ai': 'AI Opponents',
            'pvp': 'PvP Match',
            'multiplayer': 'Multiplayer',
            'solo': 'Solo Mode',
            'practice': 'Practice Mode',
            'tournament': 'Tournament'
        };
        return titles[this.selectedMode] || 'Game';
    }
    renderAIConfig() {
        return `
            <div class="config-section">
                <label class="config-label">Number of AI Opponents (Max 4):</label>
                <div class="counter-controls">
                    <button id="decrease-ai" class="counter-button decrease">−</button>
                    <span id="ai-count" class="counter-display">${this.aiOpponents}</span>
                    <button id="increase-ai" class="counter-button increase">+</button>
                </div>
            </div>

            <div id="ai-cards-config" class="config-section">
                <label class="config-label">AI Cards Configuration:</label>
                <div id="ai-cards-list">
                    ${this.renderAICardsList()}
                </div>
            </div>

            <div class="config-section">
                <label class="config-label">Your Cards (Max 4):</label>
                <div class="counter-controls">
                    <button id="decrease-user" class="counter-button decrease">−</button>
                    <span id="user-count" class="counter-display">${this.userCards}</span>
                    <button id="increase-user" class="counter-button increase">+</button>
                </div>
            </div>
        `;
    }
    renderBasicConfig() {
        return `
            <div class="config-section">
                <label class="config-label">Number of Cards (Max 4):</label>
                <div class="counter-controls">
                    <button id="decrease-user" class="counter-button decrease">−</button>
                    <span id="user-count" class="counter-display">${this.userCards}</span>
                    <button id="increase-user" class="counter-button increase">+</button>
                </div>
            </div>
            <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; margin-top: 20px;">
                <p style="margin: 0; color: #666; text-align: center; font-size: 14px;">
                    ${this.getModeDescription()}
                </p>
            </div>
        `;
    }
    getModeDescription() {
        const descriptions = {
            'vs_ai': 'Compete against AI opponents in this classic bingo game!',
            'pvp': 'Challenge other players in real-time multiplayer matches!',
            'multiplayer': 'Get matched automatically with other players online!',
            'solo': 'Casual play with no pressure - just you and the bingo cards!',
            'practice': 'Perfect your strategy without the pressure of competition.',
            'tournament': 'Join tournaments and compete for prizes and glory!'
        };
        return descriptions[this.selectedMode] || 'Configure your game settings.';
    }
    renderAICardsList() {
        return this.aiCardCounts.map((count, idx) => `
            <div class="ai-card-item">
                <span class="ai-card-label">AI ${idx + 1}:</span>
                <div class="ai-card-counter">
                    <button class="decrease-ai-card ai-card-btn decrease" data-index="${idx}">−</button>
                    <span style="font-weight: bold; color: #667eea; min-width: 20px; text-align: center;">${count}</span>
                    <button class="increase-ai-card ai-card-btn increase" data-index="${idx}">+</button>
                </div>
            </div>
        `).join('');
    }
    attachEventListeners() {
        // Practice mode specific listeners
        if (this.selectedMode === 'practice') {
            this.attachPracticeListeners();
        }
        else {
            // AI/Regular mode listeners
            document.getElementById('decrease-ai')?.addEventListener('click', () => {
                if (this.aiOpponents > 1) {
                    this.aiOpponents--;
                    this.aiCardCounts.pop();
                    this.updateUI();
                }
            });
            document.getElementById('increase-ai')?.addEventListener('click', () => {
                if (this.aiOpponents < 4) {
                    this.aiOpponents++;
                    this.aiCardCounts.push(1);
                    this.updateUI();
                }
            });
            this.attachAICardListeners();
        }
        // User card controls (common for all modes)
        document.getElementById('decrease-user')?.addEventListener('click', () => {
            if (this.userCards > 1) {
                this.userCards--;
                this.updateUserCount();
            }
        });
        document.getElementById('increase-user')?.addEventListener('click', () => {
            if (this.userCards < 4) {
                this.userCards++;
                this.updateUserCount();
            }
        });
        // Continue button
        document.getElementById('continue-btn')?.addEventListener('click', () => {
            this.proceedToGame();
        });
        // Back button
        document.getElementById('back-btn')?.addEventListener('click', () => {
            window.location.href = '/game-mode.html';
        });
        // Settings button
        document.getElementById('settings-btn')?.addEventListener('click', () => {
            Modal.show({ type: 'settings', message: '' });
        });
    }
    attachPracticeListeners() {
        // Winning pattern dropdown
        const patternSelect = document.getElementById('winning-pattern');
        if (patternSelect) {
            patternSelect.addEventListener('change', (e) => {
                this.practiceWinningPattern = e.target.value;
            });
            // Ensure the initial value is set for the TS property
            this.practiceWinningPattern = patternSelect.value;
        }
        // Speed buttons
        document.querySelectorAll('.speed-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const target = e.currentTarget;
                const speed = target.dataset.speed;
                // 1. Update TS state
                this.practiceBallSpeed = speed;
                // 2. Update UI (toggle 'selected' class)
                document.querySelectorAll('.speed-btn').forEach(b => b.classList.remove('selected'));
                target.classList.add('selected');
            });
        });
        // Auto-daub toggle
        const autoDaubToggle = document.getElementById('auto-daub-toggle');
        if (autoDaubToggle) {
            autoDaubToggle.addEventListener('change', (e) => {
                this.practiceAutoDaub = e.target.checked;
            });
            // Ensure the initial value is set for the TS property
            this.practiceAutoDaub = autoDaubToggle.checked;
        }
    }
    attachAICardListeners() {
        document.querySelectorAll('.decrease-ai-card').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const index = parseInt(e.currentTarget.dataset.index || '0');
                if (this.aiCardCounts[index] > 1) {
                    this.aiCardCounts[index]--;
                    this.updateAICardsList();
                }
            });
        });
        document.querySelectorAll('.increase-ai-card').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const index = parseInt(e.currentTarget.dataset.index || '0');
                if (this.aiCardCounts[index] < 4) {
                    this.aiCardCounts[index]++;
                    this.updateAICardsList();
                }
            });
        });
    }
    updateUI() {
        const aiCountEl = document.getElementById('ai-count');
        if (aiCountEl)
            aiCountEl.textContent = this.aiOpponents.toString();
        this.updateAICardsList();
    }
    updateUserCount() {
        const userCountEl = document.getElementById('user-count');
        if (userCountEl)
            userCountEl.textContent = this.userCards.toString();
    }
    updateAICardsList() {
        const listEl = document.getElementById('ai-cards-list');
        if (listEl) {
            listEl.innerHTML = this.renderAICardsList();
            this.attachAICardListeners();
        }
    }
    // private proceedToGame(): void {
    //     let config: GameConfig;
    //     if (this.selectedMode === 'practice') {
    //         config = {
    //             gameMode: 'practice',
    //             winningPattern: this.practiceWinningPattern,
    //             ballSpeed: this.practiceBallSpeed,
    //             autoDaub: this.practiceAutoDaub,
    //             numberOfAIOpponents: 0,
    //             numberOfCards: [this.userCards]
    //         };
    //     } else {
    //         config = {
    //             gameMode: this.selectedMode,
    //             numberOfAIOpponents: this.selectedMode === 'vs_ai' ? this.aiOpponents : 0,
    //             numberOfCards: this.selectedMode === 'vs_ai'
    //                 ? [...this.aiCardCounts, this.userCards]
    //                 : [this.userCards]
    //         };
    //     }
    //     console.log('[GAME CONFIG] Proceeding with config:', config);
    //     sessionStorage.setItem('gameConfig', JSON.stringify(config));
    //     window.location.href = '/game.html';
    // }
    proceedToGame() {
        let config;
        if (this.selectedMode === 'practice') {
            config = {
                gameMode: 'practice',
                winningPattern: this.practiceWinningPattern,
                ballSpeed: this.practiceBallSpeed,
                autoDaub: this.practiceAutoDaub,
                numberOfAIOpponents: 0,
                numberOfCards: [this.userCards]
            };
        }
        else if (this.selectedMode === 'solo') {
            // Solo mode configuration
            config = {
                gameMode: 'solo',
                numberOfAIOpponents: 0,
                numberOfCards: [this.userCards]
            };
        }
        else {
            config = {
                gameMode: this.selectedMode,
                numberOfAIOpponents: this.selectedMode === 'vs_ai' ? this.aiOpponents : 0,
                numberOfCards: this.selectedMode === 'vs_ai'
                    ? [...this.aiCardCounts, this.userCards]
                    : [this.userCards]
            };
        }
        console.log('[GAME CONFIG] Proceeding with config:', config);
        sessionStorage.setItem('gameConfig', JSON.stringify(config));
        window.location.href = '/game.html';
    }
}
