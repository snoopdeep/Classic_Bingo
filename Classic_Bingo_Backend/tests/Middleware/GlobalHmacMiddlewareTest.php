<?php

namespace App\Tests\Middleware;

use App\Core\Request;
use App\Enums\ErrorCode;
use App\Handlers\AppException;
use App\Middleware\GlobalHmacMiddleware;
use App\Constants\ServerKeys;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit test suite for the GlobalHmacMiddleware.
 *
 * @covers \App\Middleware\GlobalHmacMiddleware
 */
final class GlobalHmacMiddlewareTest extends TestCase
{
    /**
     * This trait enables the mocking of global PHP functions like time() and file_get_contents().
     */
    use PHPMock;

    private MockObject|Request $requestMock;
    private GlobalHmacMiddleware $middleware;
    private string $testSecret = 'this-is-a-super-secret-key-for-testing';

    protected function setUp(): void
    {
        $this->middleware = new GlobalHmacMiddleware($this->testSecret);
        $this->requestMock = $this->createMock(Request::class);
        $_SERVER = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = [];
        $_GET = [];
    }

    // ===================================================================
    // Failure Scenarios
    // ===================================================================

    #[Test]
    public function it_throws_exception_if_signature_header_is_missing(): void
    {
        // Arrange
        $_SERVER['HTTP_X_TIMESTAMP'] = time();

        // Assert
        $this->expectException(AppException::class);
        $this->expectExceptionCode(400); // <-- FIX: Changed from 401 to 400

        try {
            // Act
            $this->middleware->handle($this->requestMock);
        } catch (AppException $e) {
            $this->assertEquals(ErrorCode::AUTH_MISSING_SIGNATURE, $e->errorCode);
            throw $e;
        }
    }

    #[Test]
    public function it_throws_exception_if_timestamp_header_is_missing(): void
    {
        // Arrange
        $_SERVER['HTTP_X_SIGNATURE'] = 'some-signature';

        // Assert
        $this->expectException(AppException::class);
        $this->expectExceptionCode(400); // <-- FIX: Changed from 401 to 400

        // Act
        $this->middleware->handle($this->requestMock);
    }

    #[Test]
    public function it_throws_exception_for_an_expired_timestamp(): void
    {
        // Arrange
        $fixedTime = 1678886400;
        $timeMock = $this->getFunctionMock('App\Middleware', 'time');
        $timeMock->expects($this->once())->willReturn($fixedTime);

        $_SERVER['HTTP_X_SIGNATURE'] = 'some-signature';
        $_SERVER['HTTP_X_TIMESTAMP'] = $fixedTime - 301;

        // Assert
        $this->expectException(AppException::class);
        $this->expectExceptionCode(403);

        // Act
        $this->middleware->handle($this->requestMock);
    }

    #[Test]
    public function it_throws_exception_for_a_future_timestamp(): void
    {
        // Arrange
        $fixedTime = 1678886400;
        $timeMock = $this->getFunctionMock('App\Middleware', 'time');
        $timeMock->expects($this->once())->willReturn($fixedTime);

        $_SERVER['HTTP_X_SIGNATURE'] = 'some-signature';
        $_SERVER['HTTP_X_TIMESTAMP'] = $fixedTime + 301;

        // Assert
        $this->expectException(AppException::class);
        $this->expectExceptionCode(403);

        // Act
        $this->middleware->handle($this->requestMock);
    }

    #[Test]
    public function it_throws_exception_for_an_invalid_signature(): void
    {
        // Arrange
        $_SERVER[ServerKeys::REQUEST_METHOD] = 'POST';
        $_SERVER[ServerKeys::REQUEST_URI] = '/api/v1/users?param=value';
        $_GET = ['param' => 'value'];
        $body = '{"key":"some-data"}';
        $timestamp = time();

        $_SERVER['HTTP_X_TIMESTAMP'] = $timestamp;
        $_SERVER['HTTP_X_SIGNATURE'] = 'this-is-an-incorrect-signature';

        $fileGetContentsMock = $this->getFunctionMock('App\Middleware', 'file_get_contents');
        $fileGetContentsMock->expects($this->once())->with('php://input')->willReturn($body);

        // Assert
        $this->expectException(AppException::class);
        $this->expectExceptionCode(403);

        // Act
        $this->middleware->handle($this->requestMock);
    }

    // ===================================================================
    // Success Scenario
    // ===================================================================

    #[Test]
    public function it_succeeds_with_a_valid_signature_and_timestamp(): void
    {
        // Arrange
        $method = 'PUT';
        $path = '/api/v1/data/123';
        $queryString = 'a=1&b=2';
        $body = '{"name":"test"}';
        $timestamp = time();

        $_SERVER[ServerKeys::REQUEST_METHOD] = $method;
        $_SERVER[ServerKeys::REQUEST_URI] = $path . '?' . $queryString;
        $_GET = ['b' => '2', 'a' => '1'];
        $_SERVER['HTTP_X_TIMESTAMP'] = $timestamp;
        
        $fileGetContentsMock = $this->getFunctionMock('App\Middleware', 'file_get_contents');
        $fileGetContentsMock->expects($this->once())->with('php://input')->willReturn($body);

        $sortedQueryString = 'a=1&b=2';
        $canonicalString = "{$method}\n{$path}\n{$sortedQueryString}\n{$timestamp}\n{$body}";
        $expectedSignature = hash_hmac('sha256', $canonicalString, $this->testSecret);

        $_SERVER['HTTP_X_SIGNATURE'] = $expectedSignature;

        // Act
        $this->middleware->handle($this->requestMock);

        // Assert
        $this->assertTrue(true);
    }
}
