// ============================================================================
// FILE: js/config.ts - Global Configuration
// ============================================================================
export const CONFIG = {
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
        SIGNUP: '/api/v1/auth/signup',
        LOGIN: '/api/v1/auth/login', // Adjust if a different URL is required by backend
        LOGOUT: '/api/v1/auth/logout',
        USER: '/api/v1/auth/users',
        REFRESH: '/api/v1/auth/refresh'
    },
    // Avatars available for signup
    AVATARS: [
        { id: 1, name: 'avatar_01' },
        { id: 2, name: 'avatar_02' },
        { id: 3, name: 'avatar_03' },
        { id: 4, name: 'avatar_04' },
        { id: 5, name: 'avatar_05' },
        { id: 6, name: 'avatar_06' },
        { id: 7, name: 'avatar_07' },
        { id: 8, name: 'avatar_08' }
    ]
};
