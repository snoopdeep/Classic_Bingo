// ============================================================================
// FILE: js/storage.ts - Auth-specific Storage Manager
// ============================================================================

export interface User {
    userId: string;
    userName: string;
    avatarId: string;
    role?: string;
    joinedAt?: string;
    // Wallet data
    bingoCoins?: number;
    dice?: number;
    // Stats data
    totalGames?: number;
    totalWins?: number;
    totalLosses?: number;
    totalDraws?: number;
    currentWinStreak?: number;
    bestWinStreak?: number;
}

export interface IStorageService {
    setAccessToken(token: string): void;
    getAccessToken(): string | null;
    setUser(user: User): void;
    getUser(): User | null;
    getUserId(): string | null;
    isAuthenticated(): boolean;
    clearSession(): void;
    clearAll(): void;
}

const ACCESS_TOKEN_KEY = 'accessToken';
const USER_KEY = 'user';

class StorageService implements IStorageService {
    // setting accessToken to localStorage with key 'accessToken'
    setAccessToken(token: string): void {
        try {
            localStorage.setItem(ACCESS_TOKEN_KEY, token);
        } catch (error) {
            console.error('Failed to store access token:', error);
        }
    }
    
    // fetching accessToken from the localStorage
    getAccessToken(): string | null {
        try {
            return localStorage.getItem(ACCESS_TOKEN_KEY);
        } catch (error) {
            console.error('Failed to retrieve access token:', error);
            return null;
        }
    }
    
    // setting user [ userId, avatarId, userName, role and joinedAt]
    setUser(user: User): void {
        try {
            localStorage.setItem(USER_KEY, JSON.stringify(user));
        } catch (error) {
            console.error('Failed to store user data:', error);
        }
    }
    
    // get the whole userData.
    getUser(): User | null {
        try {
            const userData = localStorage.getItem(USER_KEY);
            return userData ? JSON.parse(userData) as User : null;
        } catch (error) {
            console.error('Failed to retrieve user data:', error);
            return null;
        }
    }
    
    // get the userId from the localStorage
    getUserId(): string | null {
        const user = this.getUser();
        return user?.userId || null;
    }
    
    // check if user is authenticated by checking it accessToken in localStorage
    isAuthenticated(): boolean {
        return this.getAccessToken() !== null;
    }

    // Clears only session-related items (accessToken, ). PRESERVE userId.
    // used when user logout 
    clearSession(): void {
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
    clearAll(): void {
        localStorage.clear();
        // this.clearSession();
        // try {
        //     localStorage.removeItem(USER_KEY);
        // } catch (error) {
        //     console.error('Failed to clear user data:', error);
        // }
    }
}

export const Storage = new StorageService();