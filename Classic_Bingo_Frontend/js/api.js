// ============================================================================
// FILE: js/api.ts - HMAC-signed HTTP Client (BEST SOLUTION)
// ============================================================================
import { CONFIG } from './config';
import { Storage } from './storage';
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
export const API = new ApiClient();
