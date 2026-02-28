// ============================================================================
// FILE: js/app.ts - Application Entry Point (BEST SOLUTION)
// ============================================================================

import { Auth } from './auth';
import { Modal } from './modal';
import { HomePage } from './pages/home';
import { SignupPage } from './pages/signup';
import { GameModeSelectPage } from './pages/game-mode-select';
import { GameConfigPage } from './pages/game-config';
import { GamePage } from './pages/game';
import { MultiplayerQueuePage } from './pages/multiplayer-queue';

class App {
    private static instance: App | null = null;
    private initialized: boolean = false;
    private currentPage: any = null;

    /**
     *  Private constructor for singleton pattern
     */
    private constructor() {}

    /**
     *  Get singleton instance
     */
    public static getInstance(): App {
        if (!App.instance) {
            App.instance = new App();
        }
        return App.instance;
    }

    /**
     *  Initialize application (prevents duplicate calls)
     */
    async init(): Promise<void> {
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
        } catch (error) {
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
    private async loadHomePage(): Promise<void> {
        console.log('[APP] Loading Home Page');
        this.currentPage = new HomePage();
        await this.currentPage.init();
    }

    /**
     *  Load Signup Page
     */
    private async loadSignupPage(): Promise<void> {
        console.log('[APP] Loading Signup Page');
        this.currentPage = new SignupPage();
        await this.currentPage.init();
    }

    /**
     *  Load Game Mode Selection Page (requires auth)
     */
    private async loadGameModePage(): Promise<void> {
        if (!this.checkAuthOrRedirect()) return;
        
        console.log('[APP] Loading Game Mode Page');
        this.currentPage = new GameModeSelectPage();
        await this.currentPage.init();
    }

    /**
     *  Load Game Config Page (requires auth)
     */
    private async loadGameConfigPage(): Promise<void> {
        if (!this.checkAuthOrRedirect()) return;
        
        console.log('[APP] Loading Game Config Page');
        this.currentPage = new GameConfigPage();
        await this.currentPage.init();
    }

    /**
     *  Load Game Page (requires auth)
     */
    private async loadGamePage(): Promise<void> {
        if (!this.checkAuthOrRedirect()) return;
        
        console.log('[APP] Loading Game Page');
        this.currentPage = new GamePage();
        await this.currentPage.init();
    }

    /**
     *  Load Multiplayer Queue Page (requires auth)
     */
    private async loadMultiplayerQueuePage(): Promise<void> {
        if (!this.checkAuthOrRedirect()) return;
        
        console.log('[APP] Loading Multiplayer Queue Page');
        this.currentPage = new MultiplayerQueuePage();
        await this.currentPage.init();
    }

    /**
     *  Check authentication and redirect if not authenticated
     */
    private checkAuthOrRedirect(): boolean {
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
    public getCurrentPage(): any {
        return this.currentPage;
    }

    /**
     *  Reset initialization flag (for testing/debugging only)
     */
    public reset(): void {
        console.warn('[APP] Resetting application state');
        this.initialized = false;
        this.currentPage = null;
    }
}

//  Export page classes for external use
export { HomePage, SignupPage, GameModeSelectPage, GameConfigPage, GamePage, MultiplayerQueuePage };

//  CRITICAL: Initialize ONCE when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        console.log('[APP] DOM loaded, initializing application');
        const app = App.getInstance();
        app.init();
    });
} else {
    // DOM already loaded (e.g., script loaded after DOMContentLoaded fired)
    console.log('[APP] DOM already loaded, initializing application immediately');
    const app = App.getInstance();
    app.init();
}

//  Export singleton instance for debugging (attach to window in dev mode)
if (typeof window !== 'undefined') {
    (window as any).__APP__ = App.getInstance();
}