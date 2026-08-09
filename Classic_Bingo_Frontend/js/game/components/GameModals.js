// ============================================================================
// FILE: js/game/components/GameModals.ts - Modal Components
// ============================================================================
export class GameModals {
    showResultModal(isWin, winners, onClose) {
        const modal = document.createElement('div');
        modal.className = 'game-modal-overlay';
        let winnersDisplay = '';
        if (winners.length === 0) {
            winnersDisplay = 'No winners this round';
        }
        else if (winners.length === 1) {
            const winner = winners[0];
            winnersDisplay = winner.type === 'user'
                ? 'You won!'
                : `AI (Card ${winner.cardIndex + 1}) won`;
        }
        else {
            const winnersList = winners.map(w => w.type === 'user'
                ? 'You'
                : `AI (Card ${w.cardIndex + 1})`).join(' & ');
            winnersDisplay = `Draw: ${winnersList}`;
        }
        modal.innerHTML = `
            <div class="game-modal-content">
                <div class="modal-icon">${isWin ? '🎉' : ''}</div>
                <h2 class="modal-title ${isWin ? 'win' : 'lose'}">
                    ${isWin ? 'BINGO!' : 'Game Over'}
                </h2>
                <p class="modal-message">${winnersDisplay}</p>
                ${winners.length > 0 ? this.renderWinnerDetails(winners) : ''}
                <button id="return-home-btn" class="modal-btn">
                    Return to Home
                </button>
            </div>
        `;
        document.body.appendChild(modal);
        document.getElementById('return-home-btn')?.addEventListener('click', () => {
            modal.remove();
            onClose();
        });
    }
    showWarningModal(message, onContinue) {
        const modal = document.createElement('div');
        modal.className = 'game-modal-overlay warning-modal';
        modal.innerHTML = `
            <div class="game-modal-content">
                <div class="modal-icon">⚠️</div>
                <h3 class="modal-title">Invalid Bingo</h3>
                <button id="continue-btn" class="modal-btn">
                    Continue Game
                </button>
            </div>
        `;
        // <p class="modal-message">${message}</p>
        document.body.appendChild(modal);
        document.getElementById('continue-btn')?.addEventListener('click', () => {
            modal.remove();
            onContinue();
        });
    }
    renderWinnerDetails(winners) {
        if (winners.length === 0)
            return '';
        return `
            <div class="winner-details">
                <div class="winner-details-title">Winner Details:</div>
                ${winners.map((winner, idx) => `
                    <div class="winner-item">
                        <div>
                            <span class="winner-name">
                                ${winner.type === 'user' ? '👤 You' : `🤖 AI ${winner.cardIndex + 1}`}
                            </span>
                            <span class="winner-card-info">
                                Card #${winner.cardIndex + 1}
                            </span>
                        </div>
                        <div class="winner-time">
                            ${new Date(winner.timestamp * 1000).toLocaleTimeString()}
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }
}
