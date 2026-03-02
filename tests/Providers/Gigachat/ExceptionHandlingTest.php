<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Prism\Prism\Exceptions\PrismRequestTooLargeException;
use Prism\Prism\Providers\Gigachat\Gigachat;
use Prism\Prism\Providers\Gigachat\ValueObjects\AccessToken;

beforeEach(function (): void {
    $this->provider = new Gigachat(
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret',
        url: 'https://gigachat.devices.sberbank.ru/api/v1',
        authUrl: 'https://ngw.devices.sberbank.ru:9443/api/v2',
        certPath: '',
        scope: 'GIGACHAT_API_PERS',
        accessToken: new AccessToken(
            token: 'test-token',
            expiresAt: CarbonImmutable::now()->addHour(),
        ),
    );
});

function createGigachatMockResponse(int $statusCode, array $json = [], array $headers = []): Response
{
    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('getStatusCode')->andReturn($statusCode);
    $mockResponse->shouldReceive('status')->andReturn($statusCode);
    $mockResponse->shouldReceive('json')->andReturn($json);
    $mockResponse->shouldReceive('toPsrResponse')->andReturn(new PsrResponse($statusCode));
    $mockResponse->shouldReceive('getHeaders')->andReturn($headers);
    $mockResponse->shouldReceive('header')->with('retry-after')->andReturn($headers['retry-after'] ?? '0');

    return $mockResponse;
}

it('handles rate limit errors (429)', function (): void {
    $mockResponse = createGigachatMockResponse(429, [
        'message' => 'Rate limit exceeded',
        'code' => 429,
    ]);
    $exception = new RequestException($mockResponse);

    expect(fn () => $this->provider->handleRequestException('GigaChat', $exception))
        ->toThrow(PrismRateLimitedException::class);
});

it('handles request too large errors (413)', function (): void {
    $mockResponse = createGigachatMockResponse(413, []);
    $exception = new RequestException($mockResponse);

    expect(fn () => $this->provider->handleRequestException('GigaChat', $exception))
        ->toThrow(PrismRequestTooLargeException::class);
});

it('handles 400 errors with message', function (): void {
    $mockResponse = createGigachatMockResponse(400, [
        'message' => 'Invalid request',
        'code' => 400,
        'status' => 'error',
    ]);
    $exception = new RequestException($mockResponse);

    expect(fn () => $this->provider->handleRequestException('GigaChat', $exception))
        ->toThrow(PrismException::class, 'Gigachat Error: Invalid request');
});

it('handles 401 unauthorized errors', function (): void {
    $mockResponse = createGigachatMockResponse(401, [
        'message' => 'Unauthorized',
        'code' => 401,
        'status' => 'error',
    ]);
    $exception = new RequestException($mockResponse);

    expect(fn () => $this->provider->handleRequestException('GigaChat', $exception))
        ->toThrow(PrismException::class, 'Gigachat Error: Unauthorized');
});

it('handles 402 payment required errors', function (): void {
    $mockResponse = createGigachatMockResponse(402, [
        'message' => 'Payment required',
        'code' => 402,
        'status' => 'error',
    ]);
    $exception = new RequestException($mockResponse);

    expect(fn () => $this->provider->handleRequestException('GigaChat', $exception))
        ->toThrow(PrismException::class, 'Gigachat Error: Payment required');
});

it('handles 403 forbidden errors', function (): void {
    $mockResponse = createGigachatMockResponse(403, [
        'message' => 'Forbidden',
        'code' => 403,
        'status' => 'error',
    ]);
    $exception = new RequestException($mockResponse);

    expect(fn () => $this->provider->handleRequestException('GigaChat', $exception))
        ->toThrow(PrismException::class, 'Gigachat Error: Forbidden');
});

it('handles 422 unprocessable errors', function (): void {
    $mockResponse = createGigachatMockResponse(422, [
        'message' => 'Unprocessable entity',
        'code' => 422,
        'status' => 'error',
    ]);
    $exception = new RequestException($mockResponse);

    expect(fn () => $this->provider->handleRequestException('GigaChat', $exception))
        ->toThrow(PrismException::class, 'Gigachat Error: Unprocessable entity');
});

it('handles unknown errors', function (): void {
    $mockResponse = createGigachatMockResponse(500, []);
    $exception = new RequestException($mockResponse);

    expect(fn () => $this->provider->handleRequestException('GigaChat', $exception))
        ->toThrow(PrismException::class);
});
