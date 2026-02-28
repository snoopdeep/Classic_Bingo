// ============================================================================
// FILE: js/pages/signup.ts - Signup Page Logic (BEST SOLUTION)
// ============================================================================

import { UIUtils } from '../utils';
import { Auth } from '../auth';
import { CONFIG } from '../config';
import { Storage } from '../storage';

export class SignupPage {
    private selectedAvatarId: number | null = null; 
    private isSubmitting: boolean = false;
    private formListenerAttached: boolean = false;
    private pageInstance: symbol; 

    constructor() {
        this.pageInstance = Symbol('SignupPageInstance');
    }

    public async init(): Promise<void> {
        console.log('[SIGNUP LOG] Initializing Signup Page.');
        
        if (Storage.isAuthenticated()) { 
            console.log('[SIGNUP LOG] Active session found. Redirecting to /index.html');
            window.location.href = '/index.html';
            return;
        }

        if (Storage.getUserId()) {
            console.log('[SIGNUP LOG] Cached User ID found, but no active session.');
        } else {
            console.log('[SIGNUP LOG] No User ID found. Rendering signup form.');
        }
        
        this.renderAvatars();
        this.attachFormListener();
    }
    
    private renderAvatars(): void {
        const avatarGrid = document.getElementById('avatar-grid');
        if (!avatarGrid) return;
        
        avatarGrid.innerHTML = '';
        
        CONFIG.AVATARS.forEach((avatar, index) => {
            const avatarDiv = document.createElement('div');
            avatarDiv.className = 'avatar-item';
            
            const fallbackSVG = encodeURIComponent(`
                <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80">
                    <circle cx="40" cy="40" r="40" fill="#ddd"/>
                    <text x="50%" y="50%" text-anchor="middle" dy=".3em" font-size="25">${avatar.id}</text>
                </svg>
            `.trim().replace(/\s+/g, ' '));
            
            avatarDiv.innerHTML = `
                <img 
                    src="assets/avatars/${avatar.name}.png" 
                    alt="${avatar.name}"
                    style="width: 80px; height: 80px; border-radius: 50%;"
                    onerror="this.src='data:image/svg+xml;charset=UTF-8,${fallbackSVG}'"
                />
                <p>${avatar.name.replace(/_/g, ' ')}</p>
            `;
            
            avatarDiv.addEventListener('click', () => {
                document.querySelectorAll('.avatar-item').forEach(item => {
                    (item as HTMLElement).style.border = '2px solid transparent';
                });
                
                avatarDiv.style.border = '2px solid #28a745';
                this.selectedAvatarId = avatar.id;
                UIUtils.hideError('error-message');
            });
            
            avatarGrid.appendChild(avatarDiv);

            if (index === 0) {
                avatarDiv.style.border = '2px solid #28a745';
                this.selectedAvatarId = avatar.id;
            }
        });
    }
    
    /**
     *  BEST FIX: Prevent duplicate listener attachment
     */
    private attachFormListener(): void {
        if (this.formListenerAttached) {
            console.log('[SIGNUP LOG] Form listener already attached, skipping');
            return;
        }

        const form = document.getElementById('signup-form');
        if (!form) {
            console.error('[SIGNUP LOG] Signup form not found');
            return;
        }

        form.addEventListener('submit', (e) => this.handleSignup(e));
        this.formListenerAttached = true;
        
        console.log('[SIGNUP LOG] Form submit listener attached');
    }
    
    /**
     *  BEST FIX: Comprehensive duplicate prevention
     */
    private async handleSignup(e: Event): Promise<void> {
        e.preventDefault();
        e.stopPropagation();
        
        if (this.isSubmitting) {
            console.warn('[SIGNUP LOG] Signup in progress, ignoring duplicate');
            return;
        }
        
        const usernameInput = document.getElementById('username') as HTMLInputElement;
        const username = usernameInput?.value?.trim();
        
        if (!username) {
            UIUtils.showError('error-message', 'Username is required');
            return;
        }
        
        if (this.selectedAvatarId === null) {
            UIUtils.showError('error-message', 'Please select an avatar');
            return;
        }
        
        const validation = UIUtils.validateUsername(username);
        if (!validation.valid) {
            UIUtils.showError('error-message', validation.error || 'Invalid username');
            return;
        }
        
        const avatarStringName = CONFIG.AVATARS.find(a => a.id === this.selectedAvatarId)?.name;

        if (!avatarStringName) {
            UIUtils.showError('error-message', 'Selected avatar is invalid.');
            return;
        }

        UIUtils.hideError('error-message');

        this.isSubmitting = true;
        
        const submitButton = document.getElementById('signup-btn') as HTMLButtonElement;
        if (submitButton) {
            submitButton.disabled = true;
        }

        UIUtils.setButtonLoading('signup-btn', true, 'Continue to Bingo');

        console.log('[SIGNUP LOG] Submitting signup:', username, avatarStringName);
        
        try {
            const result = await Auth.signup(username, avatarStringName);
            
            if (result.success) {
                console.log('[SIGNUP LOG] Signup successful. Redirecting...'); 
                
                UIUtils.setButtonLoading('signup-btn', true, 'Redirecting...');
                
                await new Promise(resolve => setTimeout(resolve, 100));
                
                window.location.href = '/index.html';
            } else {
                if (submitButton) {
                    submitButton.disabled = false;
                }
                UIUtils.setButtonLoading('signup-btn', false, 'Continue to Bingo');
                UIUtils.showError('error-message', result.error || 'Signup failed');
                
                this.isSubmitting = false;
            }
        } catch (error) {
            console.error('[SIGNUP LOG] Unexpected error:', error);
            
            if (submitButton) {
                submitButton.disabled = false;
            }
            UIUtils.setButtonLoading('signup-btn', false, 'Continue to Bingo');
            UIUtils.showError('error-message', 'An unexpected error occurred. Please try again.');
            
            this.isSubmitting = false;
        }
    }
}