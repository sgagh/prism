<?php

declare(strict_types=1);

namespace Tests\Providers\Gigachat;

use Illuminate\Support\Facades\Http;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Embedding;

beforeEach(function (): void {
    config()->set('prism.providers.gigachat.client_id', env('GIGACHAT_CLIENT_ID', 'test-client-id'));
    config()->set('prism.providers.gigachat.client_secret', env('GIGACHAT_CLIENT_SECRET', 'test-client-secret'));
    config()->set('prism.providers.gigachat.url', env('GIGACHAT_URL', 'https://gigachat.devices.sberbank.ru/api/v1'));
    config()->set('prism.providers.gigachat.auth_url', env('GIGACHAT_AUTH_URL', 'https://ngw.devices.sberbank.ru:9443/api/v2'));
    config()->set('prism.providers.gigachat.cert_path', env('GIGACHAT_CERT_PATH', ''));
    config()->set('prism.providers.gigachat.scope', env('GIGACHAT_SCOPE', 'GIGACHAT_API_PERS'));
});

it('returns embeddings from input', function (): void {
    Http::fake([
        '*oauth*' => Http::response([
            'access_token' => 'test-access-token',
            'expires_at' => now()->addHour()->getTimestampMs(),
        ]),
        '*embeddings*' => Http::response(
            file_get_contents('tests/Fixtures/gigachat/embeddings-input-1.json'),
        ),
    ]);

    $response = Prism::embeddings()
        ->using(Provider::Gigachat, 'Embeddings')
        ->fromInput('The food was delicious and the waiter...')
        ->asEmbeddings();

    $embeddings = json_decode(
        file_get_contents('tests/Fixtures/gigachat/embeddings-input-1.json'),
        true
    );

    $embeddings = array_map(
        fn (array $item): Embedding => Embedding::fromArray($item['embedding']),
        data_get($embeddings, 'data')
    );

    expect($response->meta->model)->toBe('Embeddings');
    expect($response->embeddings)->toBeArray();
    expect($response->embeddings[0]->embedding)->toBe($embeddings[0]->embedding);
    expect($response->usage->tokens)->toBe(8);
});

it('sends correct request payload', function (): void {
    Http::fake([
        '*oauth*' => Http::response([
            'access_token' => 'test-access-token',
            'expires_at' => now()->addHour()->getTimestampMs(),
        ]),
        '*embeddings*' => Http::response(
            file_get_contents('tests/Fixtures/gigachat/embeddings-input-1.json'),
        ),
    ]);

    $model = 'Embeddings';
    $input = 'The food was delicious and the waiter...';

    Prism::embeddings()
        ->using(Provider::Gigachat, $model)
        ->fromInput($input)
        ->asEmbeddings();

    Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'embeddings')
        && $request['model'] === $model
        && $request['input'] === [$input]);
});
