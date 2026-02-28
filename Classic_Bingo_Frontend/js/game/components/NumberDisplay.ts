// ============================================================================
// FILE: js/game/components/NumberDisplay.ts - Number Calling Component
// ============================================================================

export class NumberDisplay {
    private container: HTMLElement | null = null;

    constructor(containerId: string) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.warn(`NumberDisplay: Container '${containerId}' not found`);
        }
    }

    private getBallColor(number: number): string {
        // Classic bingo color scheme based on letter columns
        if (number >= 1 && number <= 15) return '#E53E3E'; // B - Red
        if (number >= 16 && number <= 30) return '#3182CE'; // I - Blue
        if (number >= 31 && number <= 45) return '#38A169'; // N - Green
        if (number >= 46 && number <= 60) return '#D69E2E'; // G - Gold/Yellow
        if (number >= 61 && number <= 75) return '#805AD5'; // O - Purple
        return '#667eea'; // Fallback
    }

    private getBallLetter(number: number): string {
        if (number >= 1 && number <= 15) return 'B';
        if (number >= 16 && number <= 30) return 'I';
        if (number >= 31 && number <= 45) return 'N';
        if (number >= 46 && number <= 60) return 'G';
        if (number >= 61 && number <= 75) return 'O';
        return '';
    }

    render(calledNumbers: number[]): string {
        const recentNumbers = calledNumbers.slice(-6).reverse();
        
        return `
            <div class="number-display-bar">
                <div class="number-display-label"></div>
                // Latest Called Numbers:
                <div id="number-display-container" class="number-display-container">
                    ${recentNumbers.length > 0
                        ? recentNumbers.map((n, index) => {
                            const color = this.getBallColor(n);
                            const letter = this.getBallLetter(n);
                            return `
                                <div class="number-ball" style="--ball-color: ${color}; animation-delay: ${index * 0.1}s;">
                                    <div class="ball-letter">${letter}</div>
                                    <div class="ball-number">${n}</div>
                                </div>
                            `;
                        }).join('')
                        : '<div class="number-display-empty">Waiting for first number...</div>'
                    }
                </div>
            </div>
        `;
    }

    update(numbers: number[]): void {
        const recentNumbers = numbers.slice(-6).reverse();
        const displayContainer = document.getElementById('number-display-container');
        
        if (displayContainer) {
            displayContainer.innerHTML = recentNumbers.map((n, index) => {
                const color = this.getBallColor(n);
                const letter = this.getBallLetter(n);
                return `
                    <div class="number-ball" style="--ball-color: ${color}; animation-delay: ${index * 0.1}s;">
                        <div class="ball-letter">${letter}</div>
                        <div class="ball-number">${n}</div>
                    </div>
                `;
            }).join('');
        }
    }
}