<?php

declare(strict_types=1);

namespace Prism\Prism\Providers\Perplexity;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Prism\Prism\Concerns\InitializesClient;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Prism\Prism\Exceptions\PrismRequestTooLargeException;
use Prism\Prism\Providers\Perplexity\Handlers\Text;
use Prism\Prism\Providers\Provider;
use Prism\Prism\Text\Request as TextRequest;
use Prism\Prism\Text\Response as TextResponse;

class Perplexity extends Provider
{
    const PROVIDER_PERPLEXITY = 'perplexity';

    use InitializesClient;

    public function __construct(
        #[\SensitiveParameter] public readonly string $apiKey,
        public readonly string $url,
    ) {}

    #[\Override]
    public function text(TextRequest $request): TextResponse
    {
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
            413 => throw PrismRequestTooLargeException::make(self::PROVIDER_PERPLEXITY),
            400 => $this->handleResponseErrors($model, $e),
            default => throw PrismException::providerRequestError($model, $e),
        };
    }

    protected function handleResponseErrors(string $model, RequestException $e): never
    {
        $data = $e->response->json();
        if ($data && data_get($data, 'error')) {
            $message = data_get($data, 'error.message');
            $message = is_array($message) ? implode(', ', $message) : $message;

            throw PrismException::providerResponseError(vsprintf(
                'Perplexity Error: [%s] %s (param: %s, code: %s)',
                [
                    data_get($data, 'error.type', 'unknown'),
                    $message,
                    data_get($data, 'error.param', 'None'),
                    data_get($data, 'error.code', 'None'),

                ]
            ));
        }

        throw PrismException::providerRequestError($model, $e);
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<mixed>  $retry
     */
    protected function client(array $options = [], array $retry = [], ?string $baseUrl = null): PendingRequest
    {
        return $this->baseClient()
            ->when($this->apiKey, fn ($client) => $client->withToken($this->apiKey))
            ->withOptions($options)
            ->when($retry !== [], fn ($client) => $client->retry(...$retry))
            ->baseUrl($baseUrl ?? $this->url);
    }
}
