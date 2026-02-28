// ============================================================================
// FILE: js/utils.ts
// ============================================================================

import { CONFIG } from './config';

export interface ValidationResult {
    valid: boolean;
    error?: string;
}

class Utils {
    public validateUsername(username: string): ValidationResult {
        if (!username || username.trim().length === 0) {
            return { valid: false, error: 'Username is required' };
        }
        
        if (username.length < 3 || username.length > 25) {
            return { valid: false, error: 'Username must be 3-25 characters' };
        }
        
        if (!/^[a-zA-Z0-9_]+$/.test(username)) {
            return { valid: false, error: 'Username must be alphanumeric (and underscores)' };
        }
        
        return { valid: true };
    }
    
    public showError(elementId: string, message: string): void {
        const errorElement = document.getElementById(elementId) as HTMLElement | null;
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        }
    }
    
    public hideError(elementId: string): void {
        const errorElement = document.getElementById(elementId) as HTMLElement | null;
        if (errorElement) {
            errorElement.textContent = '';
            errorElement.style.display = 'none';
        }
    }
    
    public setButtonLoading(buttonId: string, isLoading: boolean, originalText: string = 'Submit'): void {
        const button = document.getElementById(buttonId) as HTMLButtonElement | null;
        if (button) {
            button.disabled = isLoading;
            button.textContent = isLoading ? 'Loading...' : originalText;
        }
    }
    
    public showLoading(duration: number = CONFIG.LOADING_DURATION): Promise<void> {
        return new Promise((resolve) => {
            const loadingDiv = document.createElement('div');
            loadingDiv.id = 'loading-screen';
            loadingDiv.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                z-index: 999999;
            `;
            
            const spinner = document.createElement('div');
            spinner.className = 'loading-spinner';
            spinner.style.cssText = `
                width: 60px;
                height: 60px;
                border: 5px solid rgba(255, 255, 255, 0.3);
                border-top-color: #fff;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin-bottom: 20px;
            `;
            
            const text = document.createElement('div');
            text.className = 'loading-text';
            text.textContent = 'Loading...';
            text.style.cssText = `
                font-size: 24px;
                font-weight: 600;
                color: white;
                animation: pulse 1.5s ease-in-out infinite;
            `;
            
            // Add keyframes
            const style = document.createElement('style');
            style.textContent = `
                @keyframes spin { to { transform: rotate(360deg); } }
                @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
            `;
            document.head.appendChild(style);
            
            loadingDiv.appendChild(spinner);
            loadingDiv.appendChild(text);
            document.body.appendChild(loadingDiv);
            
            setTimeout(() => {
                loadingDiv.remove();
                style.remove();
                resolve();
            }, duration);
        });
    }
}

export const UIUtils = new Utils();