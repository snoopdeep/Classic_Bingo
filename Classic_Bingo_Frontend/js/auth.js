// ============================================================================
// FILE: js/auth.ts - Authentication Logic (BEST SOLUTION)
// ============================================================================
import { API } from './api';
import { CONFIG } from './config';
import { Storage } from './storage';
import { Modal } from './modal';
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
export const Auth = new AuthService();
