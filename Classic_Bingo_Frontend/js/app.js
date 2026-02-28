// ============================================================================
// FILE: js/config.ts - Global Configuration
// ============================================================================
const CONFIG = {
    // Backend API Base URL
    // Update this according to your local setup (e.g. http://localhost/Classic_Bingo/public, http://localhost:8000, etc.)
    API_BASE_URL: 'http://localhost:8000',
    // Secret Key for HMAC signature in API requests
    // Should match the JWT secret key defined in the backend
    HMAC_SECRET: 'default-secret-key',
    // Simulated Loading duration in milliseconds
    LOADING_DURATION: 1000,
    // Endpoints referenced across frontend
    ENDPOINTS: {
        SIGNUP: '/api/v1/auth/guest',
        LOGIN: '/api/v1/auth/login', // Adjust if a different URL is required by backend
        LOGOUT: '/api/v1/auth/logout',
        USER: '/api/v1/auth/user',
        REFRESH: '/api/v1/auth/refresh'
    },
    // Avatars available for signup
    AVATARS: [
        { id: 1, name: 'avatar1' },
        { id: 2, name: 'avatar2' },
        { id: 3, name: 'avatar3' },
        { id: 4, name: 'avatar4' },
        { id: 5, name: 'avatar5' },
        { id: 6, name: 'avatar6' },
        { id: 7, name: 'avatar7' },
        { id: 8, name: 'avatar8' }
    ]
};

// ============================================================================
// FILE: js/storage.ts - Auth-specific Storage Manager
// ============================================================================
const ACCESS_TOKEN_KEY = 'accessToken';
const USER_KEY = 'user';
class StorageService {
    // setting accessToken to localStorage with key 'accessToken'
    setAccessToken(token) {
        try {
            localStorage.setItem(ACCESS_TOKEN_KEY, token);
        }
        catch (error) {
            console.error('Failed to store access token:', error);
        }
    }
    // fetching accessToken from the localStorage
    getAccessToken() {
        try {
            return localStorage.getItem(ACCESS_TOKEN_KEY);
        }
        catch (error) {
            console.error('Failed to retrieve access token:', error);
            return null;
        }
    }
    // setting user [ userId, avatarId, userName, role and joinedAt]
    setUser(user) {
        try {
            localStorage.setItem(USER_KEY, JSON.stringify(user));
        }
        catch (error) {
            console.error('Failed to store user data:', error);
        }
    }
    // get the whole userData.
    getUser() {
        try {
            const userData = localStorage.getItem(USER_KEY);
            return userData ? JSON.parse(userData) : null;
        }
        catch (error) {
            console.error('Failed to retrieve user data:', error);
            return null;
        }
    }
    // get the userId from the localStorage
    getUserId() {
        const user = this.getUser();
        return user?.userId || null;
    }
    // check if user is authenticated by checking it accessToken in localStorage
    isAuthenticated() {
        return this.getAccessToken() !== null;
    }
    // Clears only session-related items (accessToken, ). PRESERVE userId.
    // used when user logout 
    clearSession() {
        localStorage.removeItem('accessToken');
        // remove any other session-only keys, e.g. authFlags
        // localStorage.removeItem('auth_expires_at');
        // try {
        //     localStorage.removeItem(ACCESS_TOKEN_KEY);
        // } catch (error) {
        //     console.error('Failed to clear access token:', error);
        // }
    }
    // Full wipe: when user got deleted.
    clearAll() {
        localStorage.clear();
        // this.clearSession();
        // try {
        //     localStorage.removeItem(USER_KEY);
        // } catch (error) {
        //     console.error('Failed to clear user data:', error);
        // }
    }
}
const Storage = new StorageService();

// ============================================================================
// FILE: js/api.ts - HMAC-signed HTTP Client (BEST SOLUTION)
// ============================================================================
/**
 * Custom Error class to wrap the structured server response.
 */
class ApiError extends Error {
    constructor(status, serverResponse, message) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.serverResponse = serverResponse;
    }
}
/**
 * Canonical JSON stringification for consistent HMAC generation
 */
function canonicalJsonStringify(obj) {
    if (!obj)
        return '';
    const sortedKeys = Object.keys(obj).sort();
    let parts = [];
    for (const key of sortedKeys) {
        const value = obj[key];
        const stringifiedValue = JSON.stringify(value);
        parts.push(`"${key}":${stringifiedValue}`);
    }
    return `{${parts.join(',')}}`;
}
class ApiClient {
    constructor() {
        this.isRefreshing = false;
        this.refreshQueue = [];
        this.onSessionExpired = null;
        this.sessionExpiredScheduled = false;
    }
    /**
     * Set callback for session expiration
     */
    setSessionExpiredCallback(callback) {
        this.onSessionExpired = callback;
    }
    /**
     * Generate HMAC signature for request
     */
    async generateHMAC(method, path, body = null) {
        const timestamp = Math.floor(Date.now() / 1000);
        let actualPath = path;
        let queryString = '';
        if (path.includes('?')) {
            const [pathPart, queryPart] = path.split('?');
            actualPath = pathPart;
            const params = new URLSearchParams(queryPart);
            const sortedParams = Array.from(params.entries()).sort((a, b) => a[0].localeCompare(b[0]));
            queryString = sortedParams.map(([key, value]) => `${key}=${value}`).join('&');
        }
        const bodyString = canonicalJsonStringify(body);
        const canonicalString = `${method}\n${actualPath}\n${queryString}\n${timestamp}\n${bodyString}`;
        const encoder = new TextEncoder();
        const keyData = encoder.encode(CONFIG.HMAC_SECRET);
        const messageData = encoder.encode(canonicalString);
        const cryptoKey = await crypto.subtle.importKey('raw', keyData, { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
        const signature = await crypto.subtle.sign('HMAC', cryptoKey, messageData);
        const hashArray = Array.from(new Uint8Array(signature));
        const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
        return { signature: hashHex, timestamp };
    }
    /**
     * Make API request with HMAC signature and automatic token refresh
     * Automatically unwraps { data, status } responses
     */
    async request(endpoint, options = {}) {
        const method = options.method || 'GET';
        const body = options.body || null;
        const url = CONFIG.API_BASE_URL + endpoint;
        const { signature, timestamp } = await this.generateHMAC(method, endpoint, body);
        const headers = {
            'Content-Type': 'application/json',
            'X-Signature': signature,
            'X-Timestamp': timestamp.toString()
        };
        if (!options.skipAuth) {
            const token = Storage.getAccessToken();
            if (token) {
                headers['Authorization'] = `Bearer ${token}`;
            }
        }
        const fetchOptions = {
            method,
            headers,
            credentials: 'include'
        };
        const canonicalBody = canonicalJsonStringify(body);
        if (canonicalBody) {
            fetchOptions.body = canonicalBody;
        }
        try {
            const response = await fetch(url, fetchOptions);
            const contentType = response.headers.get('content-type');
            const isJson = contentType && contentType.includes('application/json');
            const data = isJson ? await response.json() : {};
            console.log(`[API LOG] Response received for ${method} ${endpoint}: Status ${response.status}`);
            console.log(`[API LOG] Response Body:`, data);
            if (!response.ok) {
                const errorCode = (data && (data.error?.code || data.errorCode || data.code || data.error));
                if (response.status === 401 && endpoint !== CONFIG.ENDPOINTS.REFRESH && !options.skipAuth) {
                    const isExpired = errorCode === 'ERR_01_AUTH_ACCESS_TOKEN_EXPIRED' || errorCode === 'AUTH_ACCESS_TOKEN_EXPIRED';
                    const isInvalid = errorCode === 'ERR_01_AUTH_ACCESS_TOKEN_INVALID' || errorCode === 'AUTH_ACCESS_TOKEN_INVALID';
                    const isMissing = errorCode === 'ERR_01_AUTH_ACCESS_TOKEN_MISSING' || errorCode === 'AUTH_ACCESS_TOKEN_MISSING';
                    if (isExpired) {
                        console.warn('[API LOG] Access token EXPIRED, attempting refresh...');
                        const refreshed = await this.refreshToken(endpoint, options);
                        if (refreshed) {
                            console.log('[API LOG] Token refreshed. Retrying request.');
                            return this.request(endpoint, options);
                        }
                        console.error('[API LOG] Refresh failed. Session expired.');
                    }
                    if (isInvalid || isMissing) {
                        console.warn('[API LOG] Access token INVALID or MISSING.');
                    }
                    else if (!isExpired) {
                        console.warn('[API LOG] Unknown 401 code:', errorCode);
                    }
                    Storage.clearSession();
                    this.triggerSessionExpired();
                    const msg = errorCode
                        ? `Server Error [${errorCode}]: ${response.status} ${response.statusText}`
                        : `HTTP Error: ${response.status} ${response.statusText}`;
                    throw new ApiError(response.status, data, msg);
                }
                const errorMessage = errorCode
                    ? `Server Error [${errorCode}]: ${response.status} ${response.statusText}`
                    : `HTTP Error: ${response.status} ${response.statusText}`;
                throw new ApiError(response.status, data, errorMessage);
            }
            // Auto-unwrap { data, status } wrapper
            if (data && typeof data === 'object' && 'data' in data && 'status' in data) {
                console.log('[API LOG] Unwrapping response.data');
                return data.data;
            }
            return data;
        }
        catch (error) {
            console.error('[API CATCH] Request failed:', error);
            throw error;
        }
    }
    /**
     * Refresh access token using refresh token cookie
     */
    async refreshToken(failedEndpoint, failedOptions) {
        if (this.isRefreshing) {
            return new Promise((resolve) => {
                this.refreshQueue.push(() => resolve(true));
            });
        }
        this.isRefreshing = true;
        try {
            const oldToken = Storage.getAccessToken();
            const { signature, timestamp } = await this.generateHMAC('POST', CONFIG.ENDPOINTS.REFRESH, null);
            const headers = {
                'Content-Type': 'application/json',
                'X-Signature': signature,
                'X-Timestamp': timestamp.toString()
            };
            if (oldToken) {
                headers['Authorization'] = `Bearer ${oldToken}`;
            }
            const response = await fetch(CONFIG.API_BASE_URL + CONFIG.ENDPOINTS.REFRESH, {
                method: 'POST',
                headers,
                credentials: 'include'
            });
            const contentType = response.headers.get('content-type');
            const data = (contentType && contentType.includes('application/json')) ? await response.json() : {};
            console.log(`[API LOG] Refresh Response Status: ${response.status}`);
            console.log(`[API LOG] Refresh Response Body:`, data);
            if (response.ok) {
                //Handle wrapped response for refresh endpoint too
                const accessToken = data.data?.accessToken || data.accessToken;
                if (accessToken) {
                    Storage.setAccessToken(accessToken);
                    this.refreshQueue.forEach(callback => callback());
                    this.refreshQueue = [];
                    return true;
                }
            }
            console.error('Token refresh failed:', data?.error?.code || response.statusText);
            return false;
        }
        catch (error) {
            console.error('Network error during token refresh:', error);
            return false;
        }
        finally {
            this.isRefreshing = false;
        }
    }
    triggerSessionExpired() {
        if (this.sessionExpiredScheduled)
            return;
        this.sessionExpiredScheduled = true;
        setTimeout(() => {
            try {
                if (this.onSessionExpired)
                    this.onSessionExpired();
            }
            finally {
                // Keep flag set to prevent duplicate modals
            }
        }, 0);
    }
    resetSessionExpiredNotification() {
        this.sessionExpiredScheduled = false;
    }
}
const API = new ApiClient();

// ============================================================================
// FILE: js/modal.ts
// ============================================================================
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
const Modal = new ModalManager();

// ============================================================================
// FILE: js/auth.ts - Authentication Logic (BEST SOLUTION)
// ============================================================================
class AuthService {
    constructor() {
        this.signupInProgress = false;
        Modal.setLoginCallback((userId) => this.login(userId));
        Modal.setLogoutCallback(() => this.logout());
        API.setSessionExpiredCallback(() => {
            Modal.show('Your session has expired. Please log in again.');
            Storage.clearSession();
        });
    }
    /**
     * Helper to safely extract error data from the API response
     */
    handleError(error) {
        if (error instanceof Error && 'serverResponse' in error) {
            const serverResponse = error.serverResponse;
            const code = serverResponse?.error?.code;
            const message = serverResponse?.error?.message || error.message;
            return { error: message, errorCode: code };
        }
        return { error: error.message || 'An unknown error occurred' };
    }
    /**
     * Sign up new user
     * API.request now auto-unwraps response, so we get clean data
     */
    async signup(username, avatarName) {
        if (this.signupInProgress) {
            console.warn('[AUTH] Signup already in progress');
            return { success: false, error: 'Signup request already in progress' };
        }
        this.signupInProgress = true;
        try {
            console.log('[AUTH] Sending signup request:', { userName: username, avatarId: avatarName });
            //  Response is now automatically unwrapped by API.request
            const response = await API.request(CONFIG.ENDPOINTS.SIGNUP, {
                method: 'POST',
                body: {
                    userName: username,
                    avatarId: avatarName
                },
                skipAuth: true
            });
            console.log('[AUTH] Unwrapped signup response:', response);
            if (!response || !response.user || !response.user.userId) {
                console.error('[AUTH] Invalid response structure:', response);
                return {
                    success: false,
                    error: 'Invalid response from server'
                };
            }
            const mappedUser = {
                userId: response.user.userId,
                userName: response.user.userName,
                avatarId: response.user.avatarId,
            };
            console.log('[AUTH] Mapped user:', mappedUser);
            Storage.setAccessToken(response.accessToken);
            Storage.setUser(mappedUser);
            console.log('[AUTH] Signup successful');
            return {
                success: true,
                data: {
                    accessToken: response.accessToken,
                    user: mappedUser
                }
            };
        }
        catch (error) {
            console.error('[AUTH] Signup error:', error);
            const { error: msg, errorCode } = this.handleError(error);
            return { success: false, error: msg, errorCode };
        }
        finally {
            this.signupInProgress = false;
        }
    }
    /**
     * Login existing user
     */
    async login(userId) {
        try {
            const response = await API.request(CONFIG.ENDPOINTS.LOGIN, {
                method: 'POST',
                body: {
                    userId: userId
                },
                skipAuth: true
            });
            if (!response || !response.accessToken) {
                return {
                    success: false,
                    error: 'Invalid response from server'
                };
            }
            Storage.setAccessToken(response.accessToken);
            return { success: true, data: response };
        }
        catch (err) {
            const { error: msg, errorCode } = this.handleError(err);
            if (errorCode === 'ERR_03_RESOURCE_USER_NOT_FOUND') {
                return { success: false, error: 'User ID not found in the system.', errorCode };
            }
            return { success: false, error: msg, errorCode };
        }
    }
    /**
     * Logout user
     */
    async logout() {
        try {
            await API.request(CONFIG.ENDPOINTS.LOGOUT, {
                method: 'POST'
            });
        }
        catch (error) {
            console.error('Logout API call error:', error);
        }
        finally {
            Storage.clearSession();
        }
    }
    /**
     * Get current user profile by userId
     */
    async getMe() {
        try {
            const userId = Storage.getUserId();
            if (!userId) {
                return { success: false, error: 'User ID not found' };
            }
            const response = await API.request(`${CONFIG.ENDPOINTS.USER}/${userId}`, {
                method: 'GET'
            });
            console.log('[AUTH LOG] getMe() response:', response);
            if (!response || !response.userId) {
                return { success: false, error: 'Invalid response format' };
            }
            const updatedUser = {
                userId: response.userId,
                userName: response.userName,
                avatarId: response.avatarId,
                role: response.role || 'user',
                joinedAt: response.createdAt?.toString(),
                bingoCoins: response.bingoCoins || 0,
                dice: response.dice || 0,
                totalGames: response.totalGames || 0,
                totalWins: response.totalWins || 0,
                totalLosses: response.totalLosses || 0,
                totalDraws: response.totalDraws || 0,
                currentWinStreak: response.currentWinStreak || 0,
                bestWinStreak: response.bestWinStreak || 0
            };
            Storage.setUser(updatedUser);
            console.log('[AUTH LOG] User profile updated:', updatedUser);
            return {
                success: true,
                data: updatedUser
            };
        }
        catch (error) {
            const { error: msg, errorCode } = this.handleError(error);
            return { success: false, error: msg, errorCode };
        }
    }
    checkAuth() {
        const isAuthenticated = Storage.isAuthenticated();
        const userId = Storage.getUserId();
        console.log(`[AUTH LOG] checkAuth. URL: ${window.location.pathname}. Authenticated: ${isAuthenticated}. UserID: ${userId}`);
        return isAuthenticated;
    }
}
const Auth = new AuthService();

// ============================================================================
// FILE: js/utils.ts
// ============================================================================
class Utils {
    validateUsername(username) {
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
    showError(elementId, message) {
        const errorElement = document.getElementById(elementId);
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        }
    }
    hideError(elementId) {
        const errorElement = document.getElementById(elementId);
        if (errorElement) {
            errorElement.textContent = '';
            errorElement.style.display = 'none';
        }
    }
    setButtonLoading(buttonId, isLoading, originalText = 'Submit') {
        const button = document.getElementById(buttonId);
        if (button) {
            button.disabled = isLoading;
            button.textContent = isLoading ? 'Loading...' : originalText;
        }
    }
    showLoading(duration = CONFIG.LOADING_DURATION) {
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
const UIUtils = new Utils();

// ============================================================================
// FILE: js/pages/home.ts - Home Page Logic
// ============================================================================
class HomePage {
    /**
     * Initialize Home Page
     */
    async init() {
        console.log('[HOME LOG] Initializing Home Page.');
        // Show loading screen while checking authentication
        await UIUtils.showLoading();
        // Check if user is authenticated ie it has accessToken in the localStorage...
        const isAuthenticated = Auth.checkAuth();
        if (!isAuthenticated) {
            console.log('[HOME LOG] No active session found.');
            // Check if we have a cached user for re-login
            if (Storage.getUserId()) {
                console.log('[HOME LOG] Cached user found. User can re-login.');
                this.displayGuestMode();
            }
            else {
                console.log('[HOME LOG] No cached user. Showing signup option.');
                this.displayGuestMode();
            }
            this.attachGuestEventListeners();
            return;
        }
        // Validate token and fetch user data
        console.log('[HOME LOG] Active session found. Validating token via Auth.getMe().');
        const meResult = await Auth.getMe();
        if (!meResult.success) {
            console.error('[HOME LOG] Token validation failed on home page load.');
            this.displayGuestMode();
            this.attachGuestEventListeners();
            return;
        }
        console.log('[HOME LOG] Auth.getMe succeeded. Session valid. Rendering authenticated page.');
        this.displayUserInfo();
        this.displayWalletInfo();
        this.attachAuthenticatedEventListeners();
    }
    // Display wallet info at top center
    displayWalletInfo() {
        const user = Storage.getUser();
        if (!user)
            return;
        // Check if wallet display already exists
        let walletDisplay = document.getElementById('wallet-display');
        if (!walletDisplay) {
            // Create wallet display element
            walletDisplay = document.createElement('div');
            walletDisplay.id = 'wallet-display';
            walletDisplay.className = 'wallet-display';
            // Insert after body tag or at top of page
            document.body.insertBefore(walletDisplay, document.body.firstChild);
        }
        walletDisplay.innerHTML = `
        <div class="wallet-item">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="gold" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="10" fill="gold" stroke="#ff9800" stroke-width="2"/>
                <text x="12" y="16" text-anchor="middle" fill="#ff9800" font-size="14" font-weight="bold">¢</text>
            </svg>
            <span class="wallet-value">${user.bingoCoins || 0}</span>
        </div>
        <div class="wallet-divider"></div>
        <div class="wallet-item">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg">
                <rect x="4" y="4" width="16" height="16" rx="3" fill="white" stroke="#667eea" stroke-width="2"/>
                <circle cx="8" cy="8" r="1.5" fill="#667eea"/>
                <circle cx="16" cy="8" r="1.5" fill="#667eea"/>
                <circle cx="8" cy="16" r="1.5" fill="#667eea"/>
                <circle cx="16" cy="16" r="1.5" fill="#667eea"/>
                <circle cx="12" cy="12" r="1.5" fill="#667eea"/>
            </svg>
            <span class="wallet-value">${user.dice || 0}</span>
        </div>
    `;
    }
    /**
     * Display guest mode UI
     */
    displayGuestMode() {
        const profileSection = document.getElementById('profile-section');
        const showSignupBtn = document.getElementById('show-signup-btn');
        const getProfileBtn = document.getElementById('get-profile-btn');
        const showManualLoginBtn = document.getElementById('show-manual-login-btn');
        const cachedUserId = Storage.getUserId(); // Check for cached ID
        if (profileSection)
            profileSection.style.display = 'none';
        // Always show SIGN UP option for guests
        if (showSignupBtn)
            showSignupBtn.style.display = 'block';
        // Only show LOG IN WITH ID if no cached ID exists (i.e., truly first time or explicitly cleared cache)
        if (showManualLoginBtn) {
            showManualLoginBtn.style.display = cachedUserId ? 'none' : 'block';
        }
        if (getProfileBtn)
            getProfileBtn.style.display = 'none';
        // Render "Welcome back — Sign in" banner if we have a cached userId
        this.renderWelcomeBackBanner();
    }
    /**
     * Render a small banner that shows cached user identity and a Sign In button.
     * Clicking banner or button opens the re-login modal (preserves cached userId).
     */
    renderWelcomeBackBanner() {
        // show the banner exists. Choose top-level app container or body fallback.
        const appContainer = document.getElementById('app') || document.body;
        if (!appContainer)
            return;
        // Remove existing banner if any
        const existing = document.getElementById('welcome-back-banner');
        if (existing)
            existing.remove();
        const cachedUserId = Storage.getUserId();
        if (!cachedUserId) {
            // nothing to render
            return;
        }
        // get display name if full user object is stored
        const user = Storage.getUser();
        const displayName = user?.userName ? user.userName : (cachedUserId.substring(0, 8) + '...');
        // Create banner
        const banner = document.createElement('div');
        banner.id = 'welcome-back-banner';
        banner.style.cssText = [
            'display:flex',
            'align-items:center',
            'justify-content:space-between',
            'gap:12px',
            'padding:10px 14px',
            'background:#fffbe6',
            'border:1px solid #ffe58f',
            'border-radius:6px',
            'box-shadow:0 1px 2px rgba(0,0,0,0.04)',
            'margin:12px',
            'font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial'
        ].join(';');
        const left = document.createElement('div');
        left.style.display = 'flex';
        left.style.alignItems = 'center';
        left.style.gap = '10px';
        // small avatar or placeholder
        const avatar = document.createElement('div');
        avatar.style.cssText = 'width:36px;height:36px;border-radius:50%;background:#007bff;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;';
        avatar.textContent = (user?.userName ? user.userName.charAt(0).toUpperCase() : displayName.charAt(0).toUpperCase());
        const text = document.createElement('div');
        text.innerHTML = `<div style="font-size:14px;font-weight:600;color:#333">Welcome back</div>
                          <div style="font-size:13px;color:#555">Sign in as <strong>${this.escapeHtml(displayName)}</strong></div>`;
        left.appendChild(avatar);
        left.appendChild(text);
        const right = document.createElement('div');
        const signInBtn = document.createElement('button');
        signInBtn.textContent = 'Sign In';
        signInBtn.style.cssText = 'background:#007bff;color:#fff;border:none;padding:6px 10px;border-radius:4px;cursor:pointer;font-weight:600;';
        signInBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            // Open session-expired / quick re-login modal
            Modal.show('Your session has expired. Please sign in to continue.');
        });
        right.appendChild(signInBtn);
        // Make whole banner clickable as well
        banner.addEventListener('click', () => {
            Modal.show('Your session has expired. Please sign in to continue.');
        });
        banner.appendChild(left);
        banner.appendChild(right);
        // Insert banner at top of appContainer (before first child)
        if (appContainer.firstChild) {
            appContainer.insertBefore(banner, appContainer.firstChild);
        }
        else {
            appContainer.appendChild(banner);
        }
    }
    // small helper to avoid injecting raw html
    escapeHtml(s) {
        return s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
    /**
     * Display user information
     */
    displayUserInfo() {
        const user = Storage.getUser();
        if (user) {
            const usernameDisplay = document.getElementById('username-display');
            const userIdDisplay = document.getElementById('userId-display');
            const avatarDisplay = document.getElementById('avatar-display');
            const profileSection = document.getElementById('profile-section');
            const showSignupBtn = document.getElementById('show-signup-btn');
            const getProfileBtn = document.getElementById('get-profile-btn');
            if (profileSection)
                profileSection.style.display = 'flex';
            if (showSignupBtn)
                showSignupBtn.style.display = 'none';
            if (getProfileBtn)
                getProfileBtn.style.display = 'none';
            if (usernameDisplay)
                usernameDisplay.textContent = user.userName;
            if (userIdDisplay)
                userIdDisplay.textContent = user.userId.substring(0, 8) + '...';
            if (avatarDisplay) {
                const fallbackSVG = encodeURIComponent(`
                <svg xmlns="http://www.w3.org/2000/svg" width="50" height="50">
                    <circle cx="25" cy="25" r="25" fill="#007bff"/>
                    <text x="50%" y="50%" text-anchor="middle" dy=".3em" font-size="20" fill="white">${user.userName.charAt(0).toUpperCase()}</text>
                </svg>
            `.trim().replace(/\s+/g, ' '));
                avatarDisplay.innerHTML = `
                <img 
                    src="assets/avatars/${user.avatarId}.png" 
                    alt="${user.userName}"
                    style="width: 50px; height: 50px; border-radius: 50%; border: 3px solid #007bff; object-fit: cover;"
                    onerror="this.src='data:image/svg+xml;charset=UTF-8,${fallbackSVG}'"
                />
            `;
            }
        }
    }
    /**
     * Attach event listeners for guest mode
     */
    attachGuestEventListeners() {
        // Signup button
        const showSignupBtn = document.getElementById('show-signup-btn');
        showSignupBtn?.addEventListener('click', () => {
            window.location.href = '/signup.html';
        });
        // Manual Login button listener
        const showManualLoginBtn = document.getElementById('show-manual-login-btn');
        showManualLoginBtn?.addEventListener('click', () => {
            Modal.show({
                title: 'Sign In with User ID',
                message: 'Please enter your unique User ID below to sign in.',
                type: 'login-manual'
            });
        });
        // Start game button (redirect to signup for guests)
        const startGameBtn = document.getElementById('start-game-btn');
        startGameBtn?.addEventListener('click', () => {
            Modal.show({
                title: 'Sign Up Required',
                message: 'Please sign up or log in to start playing Classic Bingo!',
                type: 'confirm',
                onConfirm: () => {
                    window.location.href = '/signup.html';
                }
            });
        });
        // Settings (guest mode)
        const settingsIcon = document.getElementById('settings-icon');
        settingsIcon?.addEventListener('click', () => {
            Modal.show({
                title: 'Settings',
                message: 'Please sign up or log in to access settings.',
                type: 'alert'
            });
        });
        // Exit button
        const exitBtn = document.getElementById('exit-btn');
        exitBtn?.addEventListener('click', () => {
            Modal.show({
                title: 'Exit Game',
                message: 'Are you sure you want to exit?',
                type: 'confirm',
                onConfirm: () => {
                    window.close();
                }
            });
        });
        // Profile click (for re-login)
        const profileSection = document.getElementById('profile-section');
        profileSection?.addEventListener('click', () => {
            if (Storage.getUserId()) {
                Modal.show('Your session has expired. Please log in again.');
            }
            else {
                window.location.href = '/signup.html';
            }
        });
    }
    /**
     * Attach event listeners for authenticated mode
     */
    attachAuthenticatedEventListeners() {
        // Profile section click - show profile modal with complete data
        const profileSection = document.getElementById('profile-section');
        profileSection?.addEventListener('click', () => {
            const user = Storage.getUser();
            if (user) {
                // FIX: Use the central Modal manager
                Modal.show({
                    type: 'profile',
                    message: '',
                    profileData: user
                });
            }
        });
        // Settings icon
        const settingsIcon = document.getElementById('settings-icon');
        settingsIcon?.addEventListener('click', () => {
            Modal.show({
                type: 'settings',
                message: ''
            });
        });
        // Start Game button
        const startGameBtn = document.getElementById('start-game-btn');
        startGameBtn?.addEventListener('click', () => {
            window.location.href = '/game-mode.html';
        });
        // Exit button
        const exitBtn = document.getElementById('exit-btn');
        exitBtn?.addEventListener('click', () => {
            Modal.show({
                title: 'Exit Game',
                message: 'Are you sure you want to exit?',
                type: 'confirm',
                onConfirm: () => {
                    window.close();
                }
            });
        });
    }
}

// ============================================================================
// FILE: js/pages/signup.ts - Signup Page Logic (BEST SOLUTION)
// ============================================================================
class SignupPage {
    constructor() {
        this.selectedAvatarId = null;
        this.isSubmitting = false;
        this.formListenerAttached = false;
        this.pageInstance = Symbol('SignupPageInstance');
    }
    async init() {
        console.log('[SIGNUP LOG] Initializing Signup Page.');
        if (Storage.isAuthenticated()) {
            console.log('[SIGNUP LOG] Active session found. Redirecting to /index.html');
            window.location.href = '/index.html';
            return;
        }
        if (Storage.getUserId()) {
            console.log('[SIGNUP LOG] Cached User ID found, but no active session.');
        }
        else {
            console.log('[SIGNUP LOG] No User ID found. Rendering signup form.');
        }
        this.renderAvatars();
        this.attachFormListener();
    }
    renderAvatars() {
        const avatarGrid = document.getElementById('avatar-grid');
        if (!avatarGrid)
            return;
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
                    item.style.border = '2px solid transparent';
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
    attachFormListener() {
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
    async handleSignup(e) {
        e.preventDefault();
        e.stopPropagation();
        if (this.isSubmitting) {
            console.warn('[SIGNUP LOG] Signup in progress, ignoring duplicate');
            return;
        }
        const usernameInput = document.getElementById('username');
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
        const submitButton = document.getElementById('signup-btn');
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
            }
            else {
                if (submitButton) {
                    submitButton.disabled = false;
                }
                UIUtils.setButtonLoading('signup-btn', false, 'Continue to Bingo');
                UIUtils.showError('error-message', result.error || 'Signup failed');
                this.isSubmitting = false;
            }
        }
        catch (error) {
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

// ============================================================================
// FILE: js/pages/game-mode-select.ts - Game Mode Selection Page (Refactored)
// ============================================================================
class GameModeSelectPage {
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

// ============================================================================
// FILE: js/pages/game-config.ts - Game Configuration Page (Refactored)
// ============================================================================
class GameConfigPage {
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

// ============================================================================
// FILE: js/game/components/NumberDisplay.ts - Number Calling Component
// ============================================================================
class NumberDisplay {
    constructor(containerId) {
        this.container = null;
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.warn(`NumberDisplay: Container '${containerId}' not found`);
        }
    }
    getBallColor(number) {
        // Classic bingo color scheme based on letter columns
        if (number >= 1 && number <= 15)
            return '#E53E3E'; // B - Red
        if (number >= 16 && number <= 30)
            return '#3182CE'; // I - Blue
        if (number >= 31 && number <= 45)
            return '#38A169'; // N - Green
        if (number >= 46 && number <= 60)
            return '#D69E2E'; // G - Gold/Yellow
        if (number >= 61 && number <= 75)
            return '#805AD5'; // O - Purple
        return '#667eea'; // Fallback
    }
    getBallLetter(number) {
        if (number >= 1 && number <= 15)
            return 'B';
        if (number >= 16 && number <= 30)
            return 'I';
        if (number >= 31 && number <= 45)
            return 'N';
        if (number >= 46 && number <= 60)
            return 'G';
        if (number >= 61 && number <= 75)
            return 'O';
        return '';
    }
    render(calledNumbers) {
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
            : '<div class="number-display-empty">Waiting for first number...</div>'}
                </div>
            </div>
        `;
    }
    update(numbers) {
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

// ============================================================================
// FILE: js/game/components/BingoCard.ts - Individual Card Component
// ============================================================================
class BingoCardComponent {
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

// ============================================================================
// FILE: js/game/components/GameModals.ts - Modal Components
// ============================================================================
class GameModals {
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

// ============================================================================
// FILE: js/game/ui.ts - Main UI Renderer
// ============================================================================
class GameUI {
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

// ============================================================================
// FILE: js/game/api.ts - Game API Client
// ============================================================================
class GameAPI {
    static async startGame(config) {
        // For practice mode, send flat structure
        if (config.gameMode === 'practice') {
            return API.request('/api/v1/game/start', {
                method: 'POST',
                body: {
                    gameMode: 'practice',
                    winningPattern: config.winningPattern,
                    ballSpeed: config.ballSpeed,
                    autoDaub: config.autoDaub,
                    numberOfCards: config.userCards || config.numberOfCards[0]
                }
            });
        }
        // For solo mode, send simple structure
        if (config.gameMode === 'solo') {
            return API.request('/api/v1/game/start', {
                method: 'POST',
                body: {
                    gameMode: 'solo',
                    numberOfCards: config.numberOfCards[0]
                }
            });
        }
        // Regular modes (vs_ai, pvp, multiplayer)
        return API.request('/api/v1/game/start', {
            method: 'POST',
            body: {
                gameMode: config.gameMode,
                numberOfAIOpponents: config.numberOfAIOpponents,
                numberOfCards: config.numberOfCards
            }
        });
    }
    static async getNextNumber(sessionId) {
        return API.request(`/api/v1/game/${sessionId}/next-number`, {
            method: 'GET'
        });
    }
    /**
     * Daub a number on a bingo card
     * Uses the shared API client that handles authentication automatically
     */
    static async daubNumber(sessionId, daubedNumber, cardIndex) {
        return API.request(`/api/v1/game/${sessionId}/daubedNumber`, {
            method: 'POST',
            body: {
                daubedNumber,
                cardIndex
            }
        });
    }
    static async claimBingo(sessionId, cardIndex) {
        return API.request(`/api/v1/game/${sessionId}/bingo`, {
            method: 'POST',
            body: {
                cardIndex
            }
        });
    }
    // complete game and persist results
    static async completeGame(sessionId) {
        return API.request(`/api/v1/game/${sessionId}/complete`, {
            method: 'POST',
            body: {}
        });
    }
    // MULTIPLAYER API METHODS
    static async joinMultiplayerQueue(numberOfCards) {
        return API.request('/api/v1/multiplayer/queue', {
            method: 'POST',
            body: {
                numberOfCards
            }
        });
    }
    static async getMultiplayerStatus(sessionId) {
        return API.request(`/api/v1/multiplayer/${sessionId}/status`, {
            method: 'GET'
        });
    }
}

const GAME_SESSION_KEY = 'gameSessionData';
class GameStorage {
    static saveGameSession(data) {
        try {
            localStorage.setItem(GAME_SESSION_KEY, JSON.stringify(data));
            console.log('[GAME STORAGE] Game session saved:', data.sessionId);
        }
        catch (error) {
            console.error('[GAME STORAGE] Failed to save game session:', error);
        }
    }
    static getGameSession() {
        try {
            const data = localStorage.getItem(GAME_SESSION_KEY);
            return data ? JSON.parse(data) : null;
        }
        catch (error) {
            console.error('[GAME STORAGE] Failed to retrieve game session:', error);
            return null;
        }
    }
    static updateNumberCalled(number) {
        const session = this.getGameSession();
        if (session) {
            session.numberCalledSoFar.push(number);
            this.saveGameSession(session);
        }
    }
    static updateCardDaub(cardIndex, cellIndex) {
        const session = this.getGameSession();
        if (session && session.bingoCards[cardIndex]) {
            session.bingoCards[cardIndex].daubed[cellIndex] = 1;
            this.saveGameSession(session);
        }
    }
    static markGameOver(winners) {
        const session = this.getGameSession();
        if (session) {
            session.isGameOver = true;
            session.winners = winners;
            this.saveGameSession(session);
        }
    }
    static clearGameSession() {
        localStorage.removeItem(GAME_SESSION_KEY);
        console.log('[GAME STORAGE] Game session cleared');
    }
}

// ============================================================================
// FILE: js/game/controller.ts - UPDATED Controller
// ============================================================================
class GameController {
    constructor(containerId) {
        this.numberCallInterval = null;
        this.sessionData = null;
        this.gameUI = new GameUI(containerId);
    }
    async startGame(config) {
        try {
            console.log('[GAME] Starting game with config:', config);
            this.showLoadingMessage('Hold on, starting the game...');
            const response = await GameAPI.startGame(config);
            if (!response.success) {
                throw new Error('Failed to start game');
            }
            this.sessionData = {
                sessionId: response.data.sessionId,
                bingoCards: response.data.sessionData.bingoCards,
                callInterval: response.data.sessionData.callInterval,
                numberCalledSoFar: [],
                isGameOver: false,
                winners: []
            };
            GameStorage.saveGameSession(this.sessionData);
            this.gameUI.showCountdown(() => this.initGameScreen());
        }
        catch (error) {
            console.error('[GAME] Failed to start game:', error);
            alert('Failed to start game. Please try again.');
            window.location.href = '/index.html';
        }
    }
    //method for multiplayer
    async startMultiplayerGame(sessionId) {
        try {
            console.log('[GAME] Starting multiplayer game with sessionId:', sessionId);
            // Load session data from storage (saved during queue join)
            this.sessionData = GameStorage.getGameSession();
            if (!this.sessionData || this.sessionData.sessionId !== sessionId) {
                throw new Error('Session data not found');
            }
            // Show countdown and initialize game screen
            this.gameUI.showCountdown(() => this.initGameScreen());
        }
        catch (error) {
            console.error('[GAME] Failed to start multiplayer game:', error);
            alert('Failed to start game. Please try again.');
            window.location.href = '/index.html';
        }
    }
    showLoadingMessage(message) {
        const container = document.getElementById('game-container');
        if (container) {
            container.innerHTML = `
                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);">
                    <div style="width: 60px; height: 60px; border: 5px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    <div style="color: white; font-size: 20px; margin-top: 20px;">${message}</div>
                </div>
            `;
        }
    }
    initGameScreen() {
        if (!this.sessionData)
            return;
        this.gameUI.renderGameScreen(this.sessionData.bingoCards, this.sessionData.numberCalledSoFar);
        this.attachEventListeners();
        this.startNumberCallingLoop();
    }
    attachEventListeners() {
        // Cell click for daubing
        document.querySelectorAll('.bingo-cell').forEach(cell => {
            cell.addEventListener('click', (e) => this.handleCellClick(e));
        });
        // Individual card bingo buttons - **FIX: Use cardId**
        document.querySelectorAll('.card-bingo-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const target = e.currentTarget;
                const cardId = parseInt(target.dataset.cardId || '0');
                this.handleBingoClaim(cardId);
            });
        });
        // Exit button
        document.getElementById('exit-game-btn')?.addEventListener('click', () => {
            if (confirm('Are you sure you want to exit the game?')) {
                this.stopNumberCallingLoop();
                GameStorage.clearGameSession();
                window.location.href = '/index.html';
            }
        });
    }
    startNumberCallingLoop() {
        if (!this.sessionData)
            return;
        const interval = this.sessionData.callInterval * 1000;
        this.numberCallInterval = window.setInterval(async () => {
            await this.fetchNextNumber();
        }, interval);
        this.fetchNextNumber();
    }
    stopNumberCallingLoop() {
        if (this.numberCallInterval) {
            clearInterval(this.numberCallInterval);
            this.numberCallInterval = null;
        }
    }
    async fetchNextNumber() {
        if (!this.sessionData || this.sessionData.isGameOver) {
            this.stopNumberCallingLoop();
            return;
        }
        try {
            const response = await GameAPI.getNextNumber(this.sessionData.sessionId);
            if (response.success) {
                const newNumber = response.data.calledNumbers[0];
                this.sessionData.numberCalledSoFar.push(newNumber);
                GameStorage.updateNumberCalled(newNumber);
                this.gameUI.updateNumberDisplay(this.sessionData.numberCalledSoFar);
                // Handle auto-daubed cells (practice mode with auto-daub ON)
                if (response.data.autoDaub && Array.isArray(response.data.autoDaub)) {
                    console.log('[GAME] Processing auto-daubed cells for number:', newNumber);
                    response.data.autoDaub.forEach((daub) => {
                        // **FIX: cardIndex from server is actually cardId**
                        const serverCardId = daub.cardIndex;
                        const localIndex = this.sessionData.bingoCards.findIndex(c => c.cardId === serverCardId);
                        if (localIndex === -1)
                            return;
                        // Update session data using local index
                        if (this.sessionData && this.sessionData.bingoCards?.[localIndex]) {
                            this.sessionData.bingoCards[localIndex].daubed[daub.cellIndex] = 1;
                        }
                        // Update storage with local index
                        GameStorage.updateCardDaub(localIndex, daub.cellIndex);
                        // Update UI with server cardId
                        this.gameUI.highlightCell(serverCardId, daub.cellIndex);
                        console.log('[GAME] Auto-daubed:', {
                            number: newNumber,
                            serverCardId: serverCardId,
                            localIndex: localIndex,
                            cellIndex: daub.cellIndex
                        });
                    });
                }
                if (response.data.isGameOver) {
                    this.handleGameOver(response.data.winner);
                }
            }
        }
        catch (error) {
            console.error('[GAME] Failed to fetch next number:', error);
        }
    }
    async handleCellClick(event) {
        const cell = event.currentTarget;
        const cardId = parseInt(cell.dataset.cardId || '0');
        const cellIndex = parseInt(cell.dataset.cell || '0');
        const number = cell.dataset.number;
        if (!this.sessionData || number === 'FREE')
            return;
        const daubedNumber = parseInt(number || '0');
        // Find local index for storage update
        const localCardIndex = this.sessionData.bingoCards.findIndex(c => c.cardId === cardId);
        if (localCardIndex === -1) {
            console.error('[GAME] Card not found:', cardId);
            return;
        }
        try {
            const response = await GameAPI.daubNumber(this.sessionData.sessionId, daubedNumber, cardId // Send actual server cardId
            );
            console.log('daub:Response :: ', response);
            // The response structure from backend is: { success: true, data: {...} }
            if (response.success && response.data) {
                // Update local storage using local index
                if (this.sessionData && this.sessionData.bingoCards?.[localCardIndex]) {
                    this.sessionData.bingoCards[localCardIndex].daubed[cellIndex] = 1;
                }
                GameStorage.updateCardDaub(localCardIndex, cellIndex);
                this.gameUI.highlightCell(cardId, cellIndex); // UI uses cardId
                console.log('[GAME] Valid daub:', daubedNumber, {
                    cardId,
                    cellIndex,
                    daubedIndex: response.data.daubedIndex
                });
                return;
            }
            else {
                console.log('[GAME] Invalid daub - unexpected response format:', response);
            }
        }
        catch (error) {
            console.error('[GAME] Failed to daub number:', error);
        }
    }
    async handleBingoClaim(cardId) {
        if (!this.sessionData)
            return;
        console.log(`[GAME] Bingo claimed for card ${cardId}`);
        try {
            // const response = await GameAPI.claimBingo(this.sessionData.sessionId, cardIndex);
            // Send actual server cardId
            const response = await GameAPI.claimBingo(this.sessionData.sessionId, cardId);
            if (response.success || response.data?.claimValid) {
                const winners = response.data?.winners ||
                    response.data?.data?.winners ||
                    response.winners ||
                    [];
                this.handleGameOver(winners);
            }
            else {
                this.gameUI.showWarningModal(response.message || 'No winning pattern detected on this card', () => console.log('[GAME] Continuing game after invalid bingo'));
            }
        }
        catch (error) {
            console.error('[GAME] Failed to claim bingo:', error);
            this.gameUI.showWarningModal(error.message || 'Failed to verify bingo claim', () => console.log('[GAME] Continuing game'));
        }
    }
    async handleGameOver(winners) {
        if (!this.sessionData)
            return;
        this.stopNumberCallingLoop();
        GameStorage.markGameOver(winners);
        const currentUserId = Storage.getUser()?.userId || '';
        const isWin = winners.some(winner => winner.userId === currentUserId && winner.type === 'user');
        // Call completion API to persist results to database
        try {
            console.log('[GAME] Calling completion API for session:', this.sessionData.sessionId);
            const completionResponse = await GameAPI.completeGame(this.sessionData.sessionId);
            if (completionResponse.success) {
                console.log('[GAME] Game results saved to database successfully');
            }
            else {
                console.warn('[GAME] Failed to save game results:', completionResponse.message);
            }
        }
        catch (error) {
            console.error('[GAME] Error calling completion API:', error);
            // Continue to show result modal even if DB update fails
        }
        // Show result modal
        this.gameUI.showResultModal(isWin, winners);
    }
}

// ============================================================================
// FILE: js/pages/game.ts - Main Game Page
// ============================================================================
class GamePage {
    constructor() {
        this.gameController = new GameController('game-container');
    }
    async init() {
        const configStr = sessionStorage.getItem('gameConfig');
        if (!configStr) {
            console.error('[GAME PAGE] No game config found');
            window.location.href = '/game-mode.html';
            return;
        }
        const config = JSON.parse(configStr);
        console.log('[GAME PAGE] Starting game with config:', config);
        // Add necessary CSS animations
        this.injectStyles();
        // Start the game
        // await this.gameController.startGame(config);
        // Check if multiplayer mode with existing session
        if (config.gameMode === 'multiplayer' && config.sessionId) {
            await this.gameController.startMultiplayerGame(config.sessionId);
        }
        else {
            // Start regular game
            await this.gameController.startGame(config);
        }
    }
    injectStyles() {
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
            @keyframes pulse {
                0%, 100% { transform: scale(1); opacity: 1; }
                50% { transform: scale(1.1); opacity: 0.8; }
            }
            @keyframes slideIn {
                from { transform: translateY(-20px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            @keyframes scaleIn {
                from { transform: scale(0.8); opacity: 0; }
                to { transform: scale(1); opacity: 1; }
            }
            @keyframes checkmark {
                from { transform: scale(0); }
                to { transform: scale(1); }
            }
            .bingo-cell:hover:not([data-number="FREE"]) {
                transform: scale(1.05);
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            }
            #call-bingo-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 12px 28px rgba(240, 147, 251, 0.5);
            }
        `;
        document.head.appendChild(style);
    }
}

// ============================================================================
// FILE: js/pages/multiplayer-queue.ts - COMPLETE REWRITE
// ============================================================================
class MultiplayerQueuePage {
    constructor() {
        this.sessionId = '';
        this.statusPolling = null;
    }
    async init() {
        this.showCardSelection();
    }
    showCardSelection() {
        const container = document.getElementById('app');
        if (!container)
            return;
        container.innerHTML = `
            <div class="multiplayer-queue-container">
                <h1>Multiplayer Bingo</h1>
                <div class="card-selection">
                    <label>Select Number of Cards (1-4):</label>
                    <div class="counter-controls">
                        <button id="decrease-cards" class="counter-button">−</button>
                        <span id="card-count" class="counter-display">1</span>
                        <button id="increase-cards" class="counter-button">+</button>
                    </div>
                </div>
                <button id="join-queue-btn" class="btn-primary">Find Match</button>
                <button id="back-btn" class="back-btn">← Back</button>
            </div>
        `;
        let cardCount = 1;
        document.getElementById('decrease-cards')?.addEventListener('click', () => {
            if (cardCount > 1) {
                cardCount--;
                document.getElementById('card-count').textContent = cardCount.toString();
            }
        });
        document.getElementById('increase-cards')?.addEventListener('click', () => {
            if (cardCount < 4) {
                cardCount++;
                document.getElementById('card-count').textContent = cardCount.toString();
            }
        });
        document.getElementById('join-queue-btn')?.addEventListener('click', async () => {
            await this.joinQueue(cardCount);
        });
        document.getElementById('back-btn')?.addEventListener('click', () => {
            window.location.href = '/game-mode.html';
        });
    }
    async joinQueue(numberOfCards) {
        try {
            // Show loading state
            const btn = document.getElementById('join-queue-btn');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Finding Match...';
            }
            const response = await GameAPI.joinMultiplayerQueue(numberOfCards);
            if (response.success) {
                this.sessionId = response.data.sessionId;
                // Store only player's own cards with their actual server cardIds**
                const playerCards = response.data.sessionData.bingoCards;
                // Store session data
                const sessionData = {
                    sessionId: this.sessionId,
                    bingoCards: playerCards,
                    callInterval: response.data.sessionData.callInterval,
                    numberCalledSoFar: [],
                    isGameOver: false,
                    winners: []
                };
                GameStorage.saveGameSession(sessionData);
                console.log('[MULTIPLAYER] Joined queue, sessionId:', this.sessionId);
                this.showMatchmaking();
                this.startStatusPolling();
            }
        }
        catch (error) {
            console.error('[MULTIPLAYER] Failed to join queue:', error);
            alert(error.message || 'Failed to join matchmaking queue. Please try again.');
            // Reset button
            const btn = document.getElementById('join-queue-btn');
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Find Match';
            }
        }
    }
    showMatchmaking() {
        const container = document.getElementById('app');
        if (!container)
            return;
        container.innerHTML = `
            <div class="multiplayer-queue-container">
                <h1>Finding Match...</h1>
                <div class="matchmaking-status">
                    <div class="loading-spinner"></div>
                    <p id="status-message">Looking for available room...</p>
                    <div class="timer-display">
                        <p>Game starts in: <span id="timer">--</span>s</p>
                    </div>
                    <div class="players-waiting" id="players-list">
                        <p>Waiting for players...</p>
                    </div>
                </div>
                <button id="cancel-btn" class="btn-secondary">Cancel</button>
            </div>
        `;
        document.getElementById('cancel-btn')?.addEventListener('click', () => {
            this.stopStatusPolling();
            GameStorage.clearGameSession();
            window.location.href = '/game-mode.html';
        });
    }
    startStatusPolling() {
        this.statusPolling = window.setInterval(async () => {
            await this.pollStatus();
        }, 1000); // todo :: Poll every 2 seconds
        // Initial poll
        this.pollStatus();
    }
    stopStatusPolling() {
        if (this.statusPolling) {
            clearInterval(this.statusPolling);
            this.statusPolling = null;
        }
    }
    async pollStatus() {
        try {
            const response = await GameAPI.getMultiplayerStatus(this.sessionId);
            if (response.success) {
                const { participants, currentCount, maxPlayers, timeRemaining, isActive } = response.data;
                console.log('[MULTIPLAYER] Status update:', { currentCount, timeRemaining, isActive });
                // Update status message
                const statusMsg = document.getElementById('status-message');
                if (statusMsg) {
                    if (currentCount === 1) {
                        statusMsg.textContent = 'Waiting for players to join...';
                    }
                    else {
                        statusMsg.textContent = `${currentCount}/${maxPlayers} players ready`;
                    }
                }
                // Update timer
                const timer = document.getElementById('timer');
                if (timer) {
                    timer.textContent = timeRemaining.toString();
                }
                // Update players list
                const playersList = document.getElementById('players-list');
                if (playersList) {
                    if (participants.length === 0) {
                        playersList.innerHTML = '<p>Waiting for players...</p>';
                    }
                    else {
                        playersList.innerHTML = `
                            <p>Players in room:</p>
                            ${participants.map((p, index) => `<div class="player-item">Player ${index + 1}</div>`).join('')}
                        `;
                    }
                }
                // Check if game started
                if (isActive) {
                    console.log('[MULTIPLAYER] Game started! Redirecting to game...');
                    this.stopStatusPolling();
                    // Fetch fresh session data before redirecting
                    const freshSession = await GameAPI.getMultiplayerStatus(this.sessionId);
                    // Update stored session with latest data
                    const existingSession = GameStorage.getGameSession();
                    if (existingSession) {
                        GameStorage.saveGameSession({
                            ...existingSession,
                            isGameOver: false,
                            winners: []
                        });
                    }
                    // Store config for game page
                    sessionStorage.setItem('gameConfig', JSON.stringify({
                        gameMode: 'multiplayer',
                        sessionId: this.sessionId
                    }));
                    window.location.href = '/game.html';
                }
            }
        }
        catch (error) {
            console.error('[MULTIPLAYER] Status polling error:', error);
            // Check if session expired
            if (error.message && error.message.includes('expired')) {
                this.stopStatusPolling();
                GameStorage.clearGameSession();
                alert('Session expired - not enough players joined in time');
                window.location.href = '/game-mode.html';
            }
        }
    }
}

// ============================================================================
// FILE: js/app.ts - Application Entry Point (BEST SOLUTION)
// ============================================================================
class App {
    /**
     *  Private constructor for singleton pattern
     */
    constructor() {
        this.initialized = false;
        this.currentPage = null;
    }
    /**
     *  Get singleton instance
     */
    static getInstance() {
        if (!App.instance) {
            App.instance = new App();
        }
        return App.instance;
    }
    /**
     *  Initialize application (prevents duplicate calls)
     */
    async init() {
        //  CRITICAL: Prevent duplicate initialization
        if (this.initialized) {
            console.warn('[APP] Application already initialized, skipping duplicate init');
            return;
        }
        console.log('[APP] Initializing application...');
        this.initialized = true;
        // Initialize modal system (safe to call multiple times)
        Modal.init();
        const path = window.location.pathname;
        console.log('[APP] Current path:', path);
        try {
            // Route to appropriate page
            if (path === '/' || path === '/index.html' || path === '') {
                await this.loadHomePage();
            }
            else if (path === '/signup.html' || path.endsWith('/signup.html')) {
                await this.loadSignupPage();
            }
            else if (path === '/game-mode.html' || path.endsWith('/game-mode.html')) {
                await this.loadGameModePage();
            }
            else if (path === '/game-config.html' || path.endsWith('/game-config.html')) {
                await this.loadGameConfigPage();
            }
            else if (path === '/game.html' || path.endsWith('/game.html')) {
                await this.loadGamePage();
            }
            else if (path === '/multiplayer-queue.html' || path.endsWith('/multiplayer-queue.html')) {
                await this.loadMultiplayerQueuePage();
            }
            else {
                console.warn('[APP] Unknown route, redirecting to home');
                window.location.href = '/index.html';
            }
        }
        catch (error) {
            console.error('[APP] Initialization error:', error);
            // Optionally show error to user
            Modal.show({
                type: 'alert',
                title: 'Error',
                message: 'Failed to load page. Please refresh and try again.'
            });
        }
    }
    /**
     *  Load Home Page
     */
    async loadHomePage() {
        console.log('[APP] Loading Home Page');
        this.currentPage = new HomePage();
        await this.currentPage.init();
    }
    /**
     *  Load Signup Page
     */
    async loadSignupPage() {
        console.log('[APP] Loading Signup Page');
        this.currentPage = new SignupPage();
        await this.currentPage.init();
    }
    /**
     *  Load Game Mode Selection Page (requires auth)
     */
    async loadGameModePage() {
        if (!this.checkAuthOrRedirect())
            return;
        console.log('[APP] Loading Game Mode Page');
        this.currentPage = new GameModeSelectPage();
        await this.currentPage.init();
    }
    /**
     *  Load Game Config Page (requires auth)
     */
    async loadGameConfigPage() {
        if (!this.checkAuthOrRedirect())
            return;
        console.log('[APP] Loading Game Config Page');
        this.currentPage = new GameConfigPage();
        await this.currentPage.init();
    }
    /**
     *  Load Game Page (requires auth)
     */
    async loadGamePage() {
        if (!this.checkAuthOrRedirect())
            return;
        console.log('[APP] Loading Game Page');
        this.currentPage = new GamePage();
        await this.currentPage.init();
    }
    /**
     *  Load Multiplayer Queue Page (requires auth)
     */
    async loadMultiplayerQueuePage() {
        if (!this.checkAuthOrRedirect())
            return;
        console.log('[APP] Loading Multiplayer Queue Page');
        this.currentPage = new MultiplayerQueuePage();
        await this.currentPage.init();
    }
    /**
     *  Check authentication and redirect if not authenticated
     */
    checkAuthOrRedirect() {
        if (!Auth.checkAuth()) {
            console.warn('[APP] Authentication required, redirecting to home');
            window.location.href = '/index.html';
            return false;
        }
        return true;
    }
    /**
     *  Get current active page (for debugging)
     */
    getCurrentPage() {
        return this.currentPage;
    }
    /**
     *  Reset initialization flag (for testing/debugging only)
     */
    reset() {
        console.warn('[APP] Resetting application state');
        this.initialized = false;
        this.currentPage = null;
    }
}
App.instance = null;
//  CRITICAL: Initialize ONCE when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        console.log('[APP] DOM loaded, initializing application');
        const app = App.getInstance();
        app.init();
    });
}
else {
    // DOM already loaded (e.g., script loaded after DOMContentLoaded fired)
    console.log('[APP] DOM already loaded, initializing application immediately');
    const app = App.getInstance();
    app.init();
}
//  Export singleton instance for debugging (attach to window in dev mode)
if (typeof window !== 'undefined') {
    window.__APP__ = App.getInstance();
}

export { GameConfigPage, GameModeSelectPage, GamePage, HomePage, MultiplayerQueuePage, SignupPage };
//# sourceMappingURL=app.js.map
