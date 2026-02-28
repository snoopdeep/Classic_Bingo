// ============================================================================
// FILE: js/api.ts - HMAC-signed HTTP Client (BEST SOLUTION)
// ============================================================================

import { CONFIG } from './config';
import { Storage } from './storage';

interface HMACResult {
    signature: string;
    timestamp: number;
}

interface RequestOptions {
    method?: 'GET' | 'POST' | 'PUT' | 'DELETE' | 'PATCH';
    body?: Record<string, any> | null;
    skipAuth?: boolean;
}

/**
 * Interface representing the structured error response from the server.
 */
interface ServerErrorResponse {
    error: {
        code: string;
        correlationId: string;
        message?: string; 
        params?: Record<string, any>;
    };
}

/**
 * Custom Error class to wrap the structured server response.
 */
class ApiError extends Error {
    public readonly status: number;
    public readonly serverResponse: ServerErrorResponse;

    constructor(status: number, serverResponse: ServerErrorResponse, message: string) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.serverResponse = serverResponse;
    }
}

/**
 * Canonical JSON stringification for consistent HMAC generation
 */
function canonicalJsonStringify(obj: Record<string, any> | null): string {
    if (!obj) return '';
    
    const sortedKeys = Object.keys(obj).sort();
    let parts: string[] = [];
    
    for (const key of sortedKeys) {
        const value = obj[key];
        const stringifiedValue = JSON.stringify(value); 
        parts.push(`"${key}":${stringifiedValue}`);
    }
    
    return `{${parts.join(',')}}`;
}

class ApiClient {
    private isRefreshing: boolean = false;
    private refreshQueue: Array<() => void> = [];
    private onSessionExpired: (() => void) | null = null;
    private sessionExpiredScheduled: boolean = false;

    /**
     * Set callback for session expiration
     */
    public setSessionExpiredCallback(callback: () => void): void {
        this.onSessionExpired = callback;
    }
    
    /**
     * Generate HMAC signature for request
     */
    private async generateHMAC(method: string, path: string, body: Record<string, any> | null = null): Promise<HMACResult> {
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
        
        const cryptoKey = await crypto.subtle.importKey(
            'raw',
            keyData,
            { name: 'HMAC', hash: 'SHA-256' },
            false,
            ['sign']
        );
        
        const signature = await crypto.subtle.sign('HMAC', cryptoKey, messageData);
        const hashArray = Array.from(new Uint8Array(signature));
        const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
        
        return { signature: hashHex, timestamp };
    }
    
    /**
     * Make API request with HMAC signature and automatic token refresh
     * Automatically unwraps { data, status } responses
     */
    public async request<T>(endpoint: string, options: RequestOptions = {}): Promise<T> {
        const method = options.method || 'GET';
        const body = options.body || null;
        const url = CONFIG.API_BASE_URL + endpoint;
        
        const { signature, timestamp } = await this.generateHMAC(method, endpoint, body);
        
        const headers: HeadersInit = {
            'Content-Type': 'application/json',
            'X-Signature': signature,
            'X-Timestamp': timestamp.toString()
        };
        
        if (!options.skipAuth) {
            const token = Storage.getAccessToken();
            if (token) {
                (headers as Record<string, string>)['Authorization'] = `Bearer ${token}`;
            }
        }
        
        const fetchOptions: RequestInit = {
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
                const errorCode = (data && (data.error?.code || data.errorCode || data.code || data.error)) as string | undefined;

                if (response.status === 401 && endpoint !== CONFIG.ENDPOINTS.REFRESH && !options.skipAuth) {
                    const isExpired = errorCode === 'ERR_01_AUTH_ACCESS_TOKEN_EXPIRED' || errorCode === 'AUTH_ACCESS_TOKEN_EXPIRED';
                    const isInvalid = errorCode === 'ERR_01_AUTH_ACCESS_TOKEN_INVALID' || errorCode === 'AUTH_ACCESS_TOKEN_INVALID';
                    const isMissing = errorCode === 'ERR_01_AUTH_ACCESS_TOKEN_MISSING' || errorCode === 'AUTH_ACCESS_TOKEN_MISSING';

                    if (isExpired) {
                        console.warn('[API LOG] Access token EXPIRED, attempting refresh...');
                        const refreshed = await this.refreshToken(endpoint, options);
                        if (refreshed) {
                            console.log('[API LOG] Token refreshed. Retrying request.');
                            return this.request<T>(endpoint, options);
                        }
                        console.error('[API LOG] Refresh failed. Session expired.');
                    }

                    if (isInvalid || isMissing) {
                        console.warn('[API LOG] Access token INVALID or MISSING.');
                    } else if (!isExpired) {
                        console.warn('[API LOG] Unknown 401 code:', errorCode);
                    }

                    Storage.clearSession();
                    this.triggerSessionExpired();

                    const msg = errorCode
                        ? `Server Error [${errorCode}]: ${response.status} ${response.statusText}`
                        : `HTTP Error: ${response.status} ${response.statusText}`;

                    throw new ApiError(response.status, (data as ServerErrorResponse), msg);
                }

                const errorMessage = errorCode
                    ? `Server Error [${errorCode}]: ${response.status} ${response.statusText}`
                    : `HTTP Error: ${response.status} ${response.statusText}`;

                throw new ApiError(response.status, (data as ServerErrorResponse), errorMessage);
            }
            
            // Auto-unwrap { data, status } wrapper
            if (data && typeof data === 'object' && 'data' in data && 'status' in data) {
                console.log('[API LOG] Unwrapping response.data');
                return data.data as T;
            }
            
            return data as T;
        } catch (error) {
            console.error('[API CATCH] Request failed:', error);
            throw error;
        }
    }
    
    /**
     * Refresh access token using refresh token cookie
     */
    private async refreshToken(failedEndpoint: string, failedOptions: RequestOptions): Promise<boolean> {
        if (this.isRefreshing) {
            return new Promise((resolve) => {
                this.refreshQueue.push(() => resolve(true)); 
            });
        }
        
        this.isRefreshing = true;
        
        try {
            const oldToken = Storage.getAccessToken();
            
            const { signature, timestamp } = await this.generateHMAC('POST', CONFIG.ENDPOINTS.REFRESH, null);
            
            const headers: HeadersInit = {
                'Content-Type': 'application/json',
                'X-Signature': signature,
                'X-Timestamp': timestamp.toString()
            };
            
            if (oldToken) {
                (headers as Record<string, string>)['Authorization'] = `Bearer ${oldToken}`; 
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
        } catch (error) {
            console.error('Network error during token refresh:', error);
            return false;
        } finally {
            this.isRefreshing = false;
        }
    }

    private triggerSessionExpired(): void {
        if (this.sessionExpiredScheduled) return;
        this.sessionExpiredScheduled = true;
        setTimeout(() => {
            try {
                if (this.onSessionExpired) this.onSessionExpired();
            } finally {
                // Keep flag set to prevent duplicate modals
            }
        }, 0);
    }

    public resetSessionExpiredNotification(): void {
        this.sessionExpiredScheduled = false;
    }
}

export const API = new ApiClient();