// ============================================================================
// FILE: js/modal.ts
// ============================================================================
import { Storage } from './storage';
class ModalManager {
    constructor() {
        this.overlay = null;
        this.modal = null;
        this.currentType = 'alert';
        this.loginCallback = null;
        this.logoutCallback = null;
    }
    setLoginCallback(callback) {
        this.loginCallback = callback;
    }
    setLogoutCallback(callback) {
        this.logoutCallback = callback;
    }
    init() {
        if (this.overlay)
            return;
        this.overlay = document.createElement('div');
        this.overlay.className = 'modal-overlay';
        this.overlay.style.cssText = `
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 99999;
            justify-content: center;
            align-items: center;
        `;
        this.modal = document.createElement('div');
        this.modal.className = 'modal';
        this.modal.style.cssText = `
            background: white;
            padding: 30px;
            border-radius: 15px;
            min-width: 300px;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        `;
        this.overlay.appendChild(this.modal);
        document.body.appendChild(this.overlay);
    }
    escapeHtml(s) {
        return s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
    showLoginModal(message) {
        this.init();
        this.currentType = 'login';
        const userId = Storage.getUserId() || 'N/A';
        if (this.modal) {
            this.modal.className = 'modal'; // Reset class for default modal styling
            this.modal.innerHTML = `
                <h2 style="margin-top: 0; color: #dc3545;">Session Expired</h2>
                <p style="color: #666; margin-bottom: 20px;">${message}</p>
                <div style="margin-bottom: 20px; padding: 10px; border: 1px dashed #ccc; border-radius: 5px;">
                    <label style="font-size: 14px; color: #555;">User ID (cannot be changed):</label>
                    <input 
                        type="text" 
                        id="modal-userId" 
                        readonly 
                        value="${userId}"
                        style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; background-color: #f8f9fa; margin-top: 5px;"
                    />
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button 
                        id="modal-login-btn" 
                        style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600;"
                    >Sign In</button>
                </div>
            `;
            const loginBtn = document.getElementById('modal-login-btn');
            loginBtn?.addEventListener('click', () => this.handleLogin());
        }
        this.showOverlay();
    }
    showProfileModal(data) {
        this.init();
        this.currentType = 'profile';
        // Data preparation
        const fullUserId = data.userId || 'N/A';
        const truncatedUserId = fullUserId.substring(0, 12) + '...';
        const joinedDate = data.joinedAt ? new Date(data.joinedAt).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        }) : 'N/A';
        const fallbackSVG = encodeURIComponent(`
            <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
                <circle cx="50" cy="50" r="50" fill="%23007bff"/>
                <text x="50%" y="50%" text-anchor="middle" dy=".3em" font-size="40" fill="white">${data.userName.charAt(0).toUpperCase()}</text>
            </svg>
        `.trim().replace(/\s+/g, ' '));
        if (this.modal) {
            // Use the profile-modal class for external styling
            this.modal.className = 'modal profile-modal';
            this.modal.innerHTML = `
                <button class="modal-close" id="profile-modal-close-btn">×</button>
                
                <div class="profile-header">
                    <img 
                        src="assets/avatars/${data.avatarId}.png" 
                        alt="${data.userName}"
                        class="profile-avatar-large"
                        onerror="this.src='data:image/svg+xml;charset=UTF-8,${fallbackSVG}'"
                    />
                    <h2 class="profile-username">${this.escapeHtml(data.userName)}</h2>
                    
                    <div class="profile-userid-container">
                        <p class="profile-userid" id="profile-user-id" data-full-id="${fullUserId}">
                            ID: ${truncatedUserId}
                        </p>
                        <button class="copy-btn" id="copy-user-id-btn" title="Copy User ID">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                        </button>
                    </div>

                    <p class="profile-joined">Joined: ${joinedDate}</p>
                </div>
                
                <div class="profile-stats">
                    <div class="stat-row">
                        <div class="stat-item">
                            <div class="stat-icon">💰</div>
                            <div class="stat-label">Coins</div>
                            <div class="stat-value">${data.bingoCoins || 0}</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon">🎲</div>
                            <div class="stat-label">Dice</div>
                            <div class="stat-value">${data.dice || 0}</div>
                        </div>
                    </div>
                    
                    <div class="stat-divider"></div>
                    
                    <div class="stat-row">
                        <div class="stat-item">
                            <div class="stat-label">Total Games</div>
                            <div class="stat-value">${data.totalGames || 0}</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Wins</div>
                            <div class="stat-value stat-win">${data.totalWins || 0}</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Losses</div>
                            <div class="stat-value stat-loss">${data.totalLosses || 0}</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Draws</div>
                            <div class="stat-value">${data.totalDraws || 0}</div>
                        </div>
                    </div>
                    
                    <div class="stat-divider"></div>
                    
                    <div class="stat-row">
                        <div class="stat-item">
                            <div class="stat-label">Current Streak</div>
                            <div class="stat-value stat-streak">🔥 ${data.currentWinStreak || 0}</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Best Streak</div>
                            <div class="stat-value stat-best">⭐ ${data.bestWinStreak || 0}</div>
                        </div>
                    </div>
                </div>
                
                <button class="btn btn-primary" id="profile-modal-close-bottom-btn" style="width: 100%;">
                    Close
                </button>
            `;
            // Attach copy and close listeners
            const copyButton = document.getElementById('copy-user-id-btn');
            const userIdElement = document.getElementById('profile-user-id');
            const modalInstance = this;
            copyButton?.addEventListener('click', async () => {
                const idToCopy = userIdElement?.getAttribute('data-full-id');
                if (idToCopy) {
                    try {
                        await navigator.clipboard.writeText(idToCopy);
                        copyButton.innerHTML = `<span style="color: #4CAF50; font-size: 12px; font-weight: 600;">Copied!</span>`;
                        setTimeout(() => {
                            copyButton.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>`;
                        }, 1500);
                    }
                    catch (err) {
                        modalInstance.show({ title: 'Copy Failed', message: 'The browser blocked clipboard access.', type: 'alert' });
                    }
                }
            });
            document.getElementById('profile-modal-close-btn')?.addEventListener('click', () => this.hide());
            document.getElementById('profile-modal-close-bottom-btn')?.addEventListener('click', () => this.hide());
        }
        this.showOverlay();
    }
    showSettingsModal() {
        this.init();
        this.currentType = 'settings';
        if (this.modal) {
            this.modal.className = 'modal'; // Reset class for default modal styling
            this.modal.innerHTML = `
                <h2 style="margin-top: 0; color: #667eea;">Settings</h2>
                <div style="margin: 20px 0;">
                    <button 
                        id="modal-logout-btn" 
                        style="width: 100%; padding: 15px; background: #dc3545; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 600; margin-bottom: 10px;"
                    >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                        Logout
                    </button>
                    <p style="color: #666; font-size: 12px; text-align: center; margin-top: 15px;">
                        More settings coming soon...
                    </p>
                </div>
                <button 
                    id="modal-cancel-btn" 
                    style="width: 100%; padding: 12px; background: #6c757d; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600;"
                >Cancel</button>
            `;
            const logoutBtn = document.getElementById('modal-logout-btn');
            logoutBtn?.addEventListener('click', () => {
                this.hide();
                this.show({
                    title: 'Confirm Logout',
                    message: 'Are you sure you want to log out?',
                    type: 'confirm',
                    onConfirm: () => {
                        if (this.logoutCallback) {
                            this.logoutCallback();
                        }
                    }
                });
            });
            const cancelBtn = document.getElementById('modal-cancel-btn');
            cancelBtn?.addEventListener('click', () => this.hide());
        }
        this.showOverlay();
    }
    show(options) {
        this.init();
        // Handle simple string message as login modal (for expired session/cached user)
        if (typeof options === 'string') {
            this.showLoginModal(options);
            return;
        }
        // Route to specific modal types
        if (options.type === 'login') {
            this.showLoginModal(options.message); // Existing logic for cached user re-login (Welcome Back banner)
            return;
        }
        if (options.type === 'login-manual') {
            this.showManualLoginModal(options);
            return;
        }
        // Use profileData only if provided
        if (options.type === 'profile' && options.profileData) {
            this.showProfileModal(options.profileData);
            return;
        }
        if (options.type === 'settings') {
            this.showSettingsModal();
            return;
        }
        // Default alert/confirm modal
        this.currentType = options.type || 'alert';
        const title = options.title || 'Notice';
        if (this.modal) {
            this.modal.className = 'modal'; // Reset class for default modal styling
            this.modal.innerHTML = `
                <h2 style="margin-top: 0; color: #007bff;">${title}</h2>
                <p style="color: #666; margin-bottom: 20px; white-space: pre-wrap;">${options.message}</p>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    ${this.currentType === 'confirm' ? `
                        <button 
                            id="modal-cancel-btn" 
                            style="padding: 10px 20px; background: #f44336; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600;"
                        >Cancel</button>
                    ` : ''}
                    <button 
                        id="modal-ok-btn" 
                        style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600;"
                    >${this.currentType === 'confirm' ? 'Confirm' : 'OK'}</button>
                </div>
            `;
            const okBtn = document.getElementById('modal-ok-btn');
            okBtn?.addEventListener('click', () => {
                if (options.onConfirm)
                    options.onConfirm();
                this.hide();
            });
            if (this.currentType === 'confirm') {
                const cancelBtn = document.getElementById('modal-cancel-btn');
                cancelBtn?.addEventListener('click', () => {
                    if (options.onCancel)
                        options.onCancel();
                    this.hide();
                });
            }
        }
        this.showOverlay();
    }
    hide() {
        if (this.overlay) {
            this.overlay.style.display = 'none';
        }
    }
    showOverlay() {
        if (this.overlay) {
            this.overlay.style.display = 'flex';
        }
    }
    async handleLogin() {
        const userIdInput = document.getElementById('modal-userId');
        const loginBtn = document.getElementById('modal-login-btn');
        const userId = userIdInput.value.trim();
        if (!userId || userId === 'N/A') {
            this.show({ message: 'User ID not found. Please navigate to the signup page.', type: 'alert' });
            return;
        }
        if (!this.loginCallback) {
            console.error('Login callback not set!');
            return;
        }
        loginBtn.disabled = true;
        loginBtn.textContent = 'Logging in...';
        const result = await this.loginCallback(userId);
        if (result.success) {
            this.hide();
            window.location.reload();
        }
        else {
            this.show({
                title: 'Login Failed',
                message: 'Sign-in failed: ' + (result.error || 'Server error.'),
                type: 'alert',
                onConfirm: () => {
                    loginBtn.disabled = false;
                    loginBtn.textContent = 'Sign In';
                }
            });
        }
    }
    showManualLoginModal(options) {
        this.init();
        this.currentType = 'login-manual';
        if (this.modal) {
            this.modal.className = 'modal';
            this.modal.innerHTML = `
            <h2 style="margin-top: 0; color: #007bff;">${options.title || 'Sign In'}</h2>
            <p style="color: #666; margin-bottom: 20px; white-space: pre-wrap;">${options.message}</p>
            <div style="margin-bottom: 20px; padding: 10px; border: 1px dashed #ccc; border-radius: 5px;">
                <label style="font-size: 14px; color: #555;">Enter User ID:</label>
                <input 
                    type="text" 
                    id="modal-manual-userId" 
                    placeholder="Paste your 36-character User ID"
                    style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; margin-top: 5px;"
                />
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button 
                    id="modal-cancel-btn" 
                    style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600;"
                >Cancel</button>
                <button 
                    id="modal-manual-login-btn" 
                    style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600;"
                >Sign In</button>
            </div>
        `;
            const loginBtn = document.getElementById('modal-manual-login-btn');
            loginBtn?.addEventListener('click', () => this.handleManualLogin());
            const cancelBtn = document.getElementById('modal-cancel-btn');
            cancelBtn?.addEventListener('click', () => this.hide());
        }
        this.showOverlay();
    }
    async handleManualLogin() {
        const userIdInput = document.getElementById('modal-manual-userId');
        const loginBtn = document.getElementById('modal-manual-login-btn');
        const userId = userIdInput.value.trim();
        if (!userId) {
            this.show({ message: 'Please enter a User ID.', type: 'alert' });
            return;
        }
        if (!this.loginCallback) {
            console.error('Login callback not set!');
            return;
        }
        loginBtn.disabled = true;
        loginBtn.textContent = 'Logging in...';
        const result = await this.loginCallback(userId);
        if (result.success) {
            this.hide();
            window.location.reload();
        }
        else {
            // Use the default alert modal via show()
            this.show({
                title: 'Login Failed',
                message: 'Sign-in failed: ' + (result.error || 'Server error.'),
                type: 'alert',
                onConfirm: () => {
                    // Re-enable the manual login button after the alert modal closes
                    const manualLoginBtn = document.getElementById('modal-manual-login-btn');
                    if (manualLoginBtn) {
                        manualLoginBtn.disabled = false;
                        manualLoginBtn.textContent = 'Sign In';
                    }
                }
            });
        }
    }
}
export const Modal = new ModalManager();
