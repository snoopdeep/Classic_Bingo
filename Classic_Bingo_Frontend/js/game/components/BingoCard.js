// ============================================================================
// FILE: js/game/components/BingoCard.ts - Individual Card Component
// ============================================================================
export class BingoCardComponent {
    // Helper to get the color associated with a column/number range
    getBallColor(column) {
        // Colors match the BINGO ball colors (B: 1-15, I: 16-30, N: 31-45, G: 46-60, O: 61-75)
        switch (column) {
            case 'B': return '#E53E3E'; // Red
            case 'I': return '#3182CE'; // Blue
            case 'N': return '#38A169'; // Green
            case 'G': return '#D69E2E'; // Gold/Yellow
            case 'O': return '#805AD5'; // Purple
            default: return '#667eea'; // Fallback
        }
    }
    render(card, cardIndex) {
        const columns = ['B', 'I', 'N', 'G', 'O'];
        // Use actual server cardId instead of local array index**
        const actualCardId = card.cardId;
        // return `
        //     <div class="bingo-card-wrapper">
        //         <div class="card-header">Card #${card.cardId}</div>
        //         <div class="bingo-grid">
        //             ${columns.map(col => {
        //                 const color = this.getBallColor(col);
        //                 return `
        //                     <div class="column-header" style="background-color: ${color};">${col}</div>
        //                 `;
        //             }).join('')}
        //             ${card.grid.map((num, cellIdx) => {
        //                 const isDaubed = card.daubed[cellIdx] === 1;
        //                 const isFree = num === 'FREE';
        //                 return `
        //                     <div 
        //                         class="bingo-cell ${isDaubed ? 'daubed' : ''} ${isFree ? 'free' : ''}"
        //                         data-card="${cardIndex}"
        //                         data-cell="${cellIdx}"
        //                         data-number="${num}"
        //                     >
        //                         ${num}
        //                         ${isDaubed ? '<div class="checkmark">✓</div>' : ''}
        //                     </div>
        //                 `;
        //             }).join('')}
        //         </div>
        //         <button class="card-bingo-btn" data-card-index="${cardIndex}">
        //             BINGO
        //         </button>
        //     </div>
        // `;
        return `
        <div class="bingo-card-wrapper">
            <div class="card-header">Card #${actualCardId + 1}</div>
            <div class="bingo-grid">
                ${columns.map(col => {
            const color = this.getBallColor(col);
            return `
                        <div class="column-header" style="background-color: ${color};">${col}</div>
                    `;
        }).join('')}
                
                ${card.grid.map((num, cellIdx) => {
            const isDaubed = card.daubed[cellIdx] === 1;
            const isFree = num === 'FREE';
            return `
                        <div 
                            class="bingo-cell ${isDaubed ? 'daubed' : ''} ${isFree ? 'free' : ''}"
                            data-card-id="${actualCardId}"
                            data-cell="${cellIdx}"
                            data-number="${num}"
                        >
                            ${num}
                            ${isDaubed ? '<div class="checkmark">✓</div>' : ''}
                        </div>
                    `;
        }).join('')}
            </div>
            
            <button class="card-bingo-btn" data-card-id="${actualCardId}">
                BINGO
            </button>
        </div>
    `;
    }
    highlightCell(cardId, cellIndex) {
        const cell = document.querySelector(`[data-card-id="${cardId}"][data-cell="${cellIndex}"]`);
        if (cell && !cell.classList.contains('daubed')) {
            cell.classList.add('daubed');
            cell.innerHTML += '<div class="checkmark">✓</div>';
        }
    }
}
