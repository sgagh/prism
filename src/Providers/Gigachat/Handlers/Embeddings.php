<?php

declare(strict_types=1);

namespace Prism\Prism\Providers\Gigachat\Handlers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Prism\Prism\Embeddings\Request;
use Prism\Prism\Embeddings\Response as EmbeddingsResponse;
use Prism\Prism\Providers\Gigachat\Concerns\ValidatesResponse;
use Prism\Prism\ValueObjects\Embedding;
use Prism\Prism\ValueObjects\EmbeddingsUsage;
use Prism\Prism\ValueObjects\Meta;

class Embeddings
{
    use ValidatesResponse;

    public function __construct(protected PendingRequest $client) {}

    public function handle(Request $request): EmbeddingsResponse
    {
        $response = $this->sendRequest($request);

        $this->validateResponse($response);

        $data = $response->json();

        return new EmbeddingsResponse(
            embeddings: array_map(fn (array $item): Embedding => Embedding::fromArray($item['embedding']), data_get($data, 'data', [])),
            usage: new EmbeddingsUsage(array_sum(array_map(fn (array $item): int => (int) data_get($item, 'usage.prompt_tokens', 0), data_get($data, 'data', [])))),
            meta: new Meta(
                id: '',
                model: data_get($data, 'model', ''),
                rateLimits: [],
            ),
            raw: $data,
        );
    }

    protected function sendRequest(Request $request): Response
    {
        return $this->client->post(
            'embeddings',
            [
                'model' => $request->model(),
                'input' => $request->inputs(),
                ...($request->providerOptions() ?? []),
            ]
        );
    }
}
