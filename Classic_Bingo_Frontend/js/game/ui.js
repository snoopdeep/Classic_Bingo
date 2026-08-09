// ============================================================================
// FILE: js/game/ui.ts - Main UI Renderer
// ============================================================================
import { NumberDisplay } from './components/NumberDisplay';
import { BingoCardComponent } from './components/BingoCard';
import { GameModals } from './components/GameModals';
export class GameUI {
    constructor(containerId) {
        const element = document.getElementById(containerId);
        if (!element) {
            throw new Error(`Container element '${containerId}' not found`);
        }
        this.container = element;
        this.numberDisplay = new NumberDisplay('number-display-container');
        this.cardComponent = new BingoCardComponent();
        this.modals = new GameModals();
    }
    showCountdown(callback) {
        let count = 3;
        this.container.innerHTML = `
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div style="font-size: 120px; font-weight: bold; color: white; animation: pulse 1s ease-in-out;" id="countdown-number">${count}</div>
                <div style="font-size: 24px; color: white; margin-top: 20px;">Game starts in...</div>
            </div>
        `;
        const interval = setInterval(() => {
            count--;
            const countdownEl = document.getElementById('countdown-number');
            if (countdownEl) {
                countdownEl.textContent = count > 0 ? count.toString() : 'GO!';
            }
            if (count === 0) {
                clearInterval(interval);
                setTimeout(callback, 500);
            }
        }, 1000);
    }
    renderGameScreen(cards, calledNumbers) {
        const cardCountClass = `cards-${cards.length}`;
        this.container.innerHTML = `
            <div class="game-container">
                <!-- Header -->
                <div class="game-header">
                    <h1 class="game-title">Classic Bingo</h1>
                    <button id="exit-game-btn" class="exit-game-btn">Exit Game</button>
                </div>

                <!-- Number Display Bar -->
                ${this.numberDisplay.render(calledNumbers)}

                <!-- Bingo Cards Grid -->
                <div id="cards-container" class="cards-grid ${cardCountClass}">
                    ${cards.map((card) => this.cardComponent.render(card, card.cardId)).join('')}
                </div>
            </div>
        `;
    }
    // ${cards.map((card, idx) => this.cardComponent.render(card, idx)).join('')}
    updateNumberDisplay(numbers) {
        this.numberDisplay.update(numbers);
    }
    // highlightCell(cardIndex: number, cellIndex: number): void {
    //     this.cardComponent.highlightCell(cardIndex, cellIndex);
    // }
    highlightCell(cardId, cellIndex) {
        this.cardComponent.highlightCell(cardId, cellIndex);
    }
    showResultModal(isWin, winners) {
        this.modals.showResultModal(isWin, winners, () => {
            window.location.href = '/index.html';
        });
    }
    showWarningModal(message, onContinue) {
        this.modals.showWarningModal(message, onContinue);
    }
}
