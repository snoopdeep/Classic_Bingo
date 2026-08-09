<?php

namespace App\Middleware;

use App\Core\Request;
use App\Handlers\AppException;
use App\Enums\ErrorCode;
use App\Constants\ServerKeys;

/**
 * Verifies the integrity and authenticity of incoming requests using HMAC-SHA256.
 */
class GlobalHmacMiddleware
{
    /**
     * The shared secret key used for HMAC calculation.
     * @var string
     */
    private string $secretKey;

    private const FILE_NAME = 'php://input';
    private const HASHING_ALGO = 'sha256';
    /**
     * Injects the shared secret key from the application's configuration.
     *
     * @param string $secretKey The shared secret key.
     */
    public function __construct(string $secretKey) {
        $this->secretKey = $secretKey;
    }

    /**
     * Handles the incoming request to validate its HMAC signature.
     *
     * @param Request $request The application's request object.
     * @return void
     * @throws AppException If the signature or timestamp is missing, expired, or invalid.
     */
    public function handle(Request $request): void {
        // 1. Extract signature and timestamp from request headers.
        $clientSignature = $_SERVER[ServerKeys::HTTP_X_SIGNATURE] ?? null;
        $clientTimestamp = (int)($_SERVER[ServerKeys::HTTP_X_TIMESTAMP] ?? 0);

        if (!$clientSignature || !$clientTimestamp) {
            throw new AppException(ErrorCode::AUTH_MISSING_SIGNATURE);
        }

        // 2. Validate the timestamp to prevent replay attacks.
        // We allow a 5-minute window (300 seconds) for clock drift and network latency.
        if (abs(time() - $clientTimestamp) > 300) {
            throw new AppException(ErrorCode::AUTH_INVALID_SIGNATURE, ['reason' => 'Timestamp expired.']);
        }

        // 3. Reconstruct the canonical string on the server using the exact same rules as the client.
        $method = $_SERVER[ServerKeys::REQUEST_METHOD];
        $path = parse_url($_SERVER[ServerKeys::REQUEST_URI], PHP_URL_PATH);
        
        $queryParams = $_GET;
        ksort($queryParams); // Sort query params alphabetically by key
        $sortedQueryString = http_build_query($queryParams);

        $body = file_get_contents(self::FILE_NAME);

        $canonicalString = "{$method}\n{$path}\n{$sortedQueryString}\n{$clientTimestamp}\n{$body}";

        // 4. Calculate the expected signature using the server's secret key.
        $expectedSignature = hash_hmac(self::HASHING_ALGO, $canonicalString, $this->secretKey);

        //  DEBUGGING: Log the server's canonical string and the signatures
        // error_log("--- HMAC DEBUG ---");
        // error_log("Server Canonical String: " . str_replace("\n", "\\n", $canonicalString));
        // error_log("Client Signature:   " . $clientSignature);
        // error_log("Expected Signature: " . $expectedSignature);
        // error_log("--------------------");

        // 5. Compare the client's signature with the expected signature in a timing-attack-safe manner.
        if (!hash_equals($expectedSignature, $clientSignature)) {
            throw new AppException(ErrorCode::AUTH_INVALID_SIGNATURE);
        }
        
        // If the signature is valid, the request is allowed to proceed.
    }
}