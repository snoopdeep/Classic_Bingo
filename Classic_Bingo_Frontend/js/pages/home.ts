// ============================================================================
// FILE: js/pages/home.ts - Home Page Logic
// ============================================================================

import { UIUtils } from '../utils';
import { Auth } from '../auth';
// import { Storage } from '../storage';
import { Modal } from '../modal';
import { Storage, User } from '../storage';

export class HomePage {
    /**
     * Initialize Home Page
     */
    public async init(): Promise<void> {
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
            } else {
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
private displayWalletInfo(): void {
    const user = Storage.getUser();
    if (!user) return;
    
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
    private displayGuestMode(): void {
        const profileSection = document.getElementById('profile-section');
        const showSignupBtn = document.getElementById('show-signup-btn');
        const getProfileBtn = document.getElementById('get-profile-btn');
        const showManualLoginBtn = document.getElementById('show-manual-login-btn'); 

        const cachedUserId = Storage.getUserId(); // Check for cached ID
        
        if (profileSection) profileSection.style.display = 'none';
        // Always show SIGN UP option for guests
        if (showSignupBtn) showSignupBtn.style.display = 'block';
        // Only show LOG IN WITH ID if no cached ID exists (i.e., truly first time or explicitly cleared cache)
        if (showManualLoginBtn) {
            showManualLoginBtn.style.display = cachedUserId ? 'none' : 'block';
        }
        if (getProfileBtn) getProfileBtn.style.display = 'none';
        // Render "Welcome back — Sign in" banner if we have a cached userId
        this.renderWelcomeBackBanner();
    }

    /**
     * Render a small banner that shows cached user identity and a Sign In button.
     * Clicking banner or button opens the re-login modal (preserves cached userId).
     */
    private renderWelcomeBackBanner(): void {
        // show the banner exists. Choose top-level app container or body fallback.
        const appContainer = document.getElementById('app') || document.body;
        if (!appContainer) return;

        // Remove existing banner if any
        const existing = document.getElementById('welcome-back-banner');
        if (existing) existing.remove();

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
        } else {
            appContainer.appendChild(banner);
        }
    }

    // small helper to avoid injecting raw html
    private /* static */ escapeHtml(s: string): string {
        return s.replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c] as string));
    }

    
    /**
     * Display user information
     */

    private displayUserInfo(): void {
    const user = Storage.getUser();
    if (user) {
        const usernameDisplay = document.getElementById('username-display');
        const userIdDisplay = document.getElementById('userId-display');
        const avatarDisplay = document.getElementById('avatar-display');
        const profileSection = document.getElementById('profile-section');
        const showSignupBtn = document.getElementById('show-signup-btn');
        const getProfileBtn = document.getElementById('get-profile-btn');
        
        if (profileSection) profileSection.style.display = 'flex';
        if (showSignupBtn) showSignupBtn.style.display = 'none';
        if (getProfileBtn) getProfileBtn.style.display = 'none';
        
        if (usernameDisplay) usernameDisplay.textContent = user.userName;
        if (userIdDisplay) userIdDisplay.textContent = user.userId.substring(0, 8) + '...';
        
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
    private attachGuestEventListeners(): void {
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
            } else {
                window.location.href = '/signup.html';
            }
        });
    }
    
    /**
     * Attach event listeners for authenticated mode
     */

    private attachAuthenticatedEventListeners(): void {
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