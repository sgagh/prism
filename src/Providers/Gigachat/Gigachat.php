<?php

declare(strict_types=1);

namespace Prism\Prism\Providers\Gigachat;

use GuzzleHttp\RequestOptions;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Prism\Prism\Concerns\InitializesClient;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Prism\Prism\Exceptions\PrismRequestTooLargeException;
use Prism\Prism\Providers\Gigachat\Handlers\Text;
use Prism\Prism\Providers\Gigachat\ValueObjects\AccessToken;
use Prism\Prism\Providers\Provider;
use Prism\Prism\Text\Request as TextRequest;
use Prism\Prism\Text\Response as TextResponse;

class Gigachat extends Provider
{
    use InitializesClient;

    public function __construct(
        #[\SensitiveParameter] protected readonly string $clientId,
        #[\SensitiveParameter] protected readonly string $clientSecret,
        protected readonly string $url,
        protected readonly string $authUrl,
        protected readonly string $certPath,
        protected readonly string $scope,
        #[\SensitiveParameter] protected ?AccessToken $accessToken = null,
    ) {}

    #[\Override]
    public function text(TextRequest $request): TextResponse
    {
        $this->validateAuthToken();
        $handler = new Text($this->client(
            $request->clientOptions(),
            $request->clientRetry()
        ));

        return $handler->handle($request);
    }

    public function handleRequestException(string $model, RequestException $e): never
    {
        match ($e->response->getStatusCode()) {
            429 => throw PrismRateLimitedException::make(
                rateLimits: $this->processRateLimits($e->response),
                retryAfter: (int) $e->response->header('retry-after')
            ),
            422 => $this->handleResponseErrors($model, $e),
            413 => throw new PrismRequestTooLargeException('Gigachat'),
            403 => $this->handleResponseErrors($model, $e),
            402 => $this->handleResponseErrors($model, $e),
            401 => $this->handleResponseErrors($model, $e),
            400 => $this->handleResponseErrors($model, $e),
            default => throw PrismException::providerRequestError($model, $e),
        };
    }

    protected function handleResponseErrors(string $model, RequestException $e): never
    {
        $data = $e->response->json();
        throw PrismException::providerResponseError(vsprintf(
            'Gigachat Error: %s (code: %s, status: %s, model: %s)',
            [
                data_get($data, 'message'),
                data_get($data, 'code', 'None'),
                data_get($data, 'status', 'None'),
                $model,
            ]
        ));
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<mixed>  $retry
     */
    protected function client(array $options = [], array $retry = [], ?string $baseUrl = null): PendingRequest
    {
        return $this->baseClient()
            ->when($this->accessToken, fn ($client) => $client->withToken($this->accessToken->token))
            ->withOptions(array_merge($this->clientDefaultOptions(), $options))
            ->when($retry !== [], fn ($client) => $client->retry(...$retry))
            ->baseUrl($baseUrl ?? $this->url);
    }

    protected function validateAuthToken(): void
    {
        if (!$this->accessToken instanceof \Prism\Prism\Providers\Gigachat\ValueObjects\AccessToken || $this->accessToken->isExpired()) {
            $this->accessToken = $this->getAuthToken();
        }
    }

    protected function getAuthToken(): AccessToken
    {
        $client = $this->baseClient()
            ->withOptions(array_merge($this->clientDefaultOptions(), []))
            ->withToken(base64_encode($this->clientId.':'.$this->clientSecret), 'Basic')
            ->asForm()
            ->withHeader('RqUID', Str::uuid()->toString())
            ->baseUrl($this->authUrl);
        $response = $client->post('oauth', ['scope' => $this->scope]);
        try {
            $response->throw();
        } catch (RequestException $e) {
            $this->handleRequestException('auth', $e);
        }

        return AccessToken::fromArray($response->json());
    }

    protected function clientDefaultOptions(): array
    {
        return [
            RequestOptions::VERIFY => $this->certPath,
        ];
    }
}
