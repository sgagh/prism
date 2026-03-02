<?php

declare(strict_types=1);

namespace Tests\Providers\Gigachat;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Providers\Gigachat\Maps\FinishReasonMap;

beforeEach(function (): void {
    config()->set('prism.providers.gigachat.client_id', env('GIGACHAT_CLIENT_ID', 'test-client-id'));
    config()->set('prism.providers.gigachat.client_secret', env('GIGACHAT_CLIENT_SECRET', 'test-client-secret'));
    config()->set('prism.providers.gigachat.url', env('GIGACHAT_URL', 'https://gigachat.devices.sberbank.ru/api/v1'));
    config()->set('prism.providers.gigachat.auth_url', env('GIGACHAT_AUTH_URL', 'https://ngw.devices.sberbank.ru:9443/api/v2'));
    config()->set('prism.providers.gigachat.cert_path', env('GIGACHAT_CERT_PATH', ''));
    config()->set('prism.providers.gigachat.scope', env('GIGACHAT_SCOPE', 'GIGACHAT_API_PERS'));
});

function fakeGigachatText(string $fixture): void
{
    Http::fake([
        '*oauth*' => Http::response([
            'access_token' => 'test-access-token',
            'expires_at' => now()->addHour()->getTimestampMs(),
        ]),
        '*chat/completions*' => Http::response(
            file_get_contents("tests/Fixtures/gigachat/{$fixture}.json"),
        ),
    ]);
}

it('can generate text with a prompt', function (): void {
    fakeGigachatText('generate-text-with-a-prompt-1');

    $response = Prism::text()
        ->using(Provider::Gigachat, 'GigaChat')
        ->withPrompt('Who are you?')
        ->asText();

    expect($response->usage->promptTokens)->toBe(7);
    expect($response->usage->completionTokens)->toBe(15);
    expect($response->meta->model)->toBe('GigaChat');
    expect($response->text)->toBe('I am GigaChat, a large language model created by Sber.');
    expect($response->finishReason)->toBe(FinishReason::Stop);
});

it('can generate text with a system prompt', function (): void {
    fakeGigachatText('generate-text-with-system-prompt-1');

    $response = Prism::text()
        ->using(Provider::Gigachat, 'GigaChat')
        ->withSystemPrompt('MODEL ADOPTS ROLE of [PERSONA: Nyx the Cthulhu]!')
        ->withPrompt('Who are you?')
        ->asText();

    expect($response->usage->promptTokens)->toBe(32);
    expect($response->usage->completionTokens)->toBe(18);
    expect($response->meta->model)->toBe('GigaChat');
    expect($response->text)->toContain('Nyx');
    expect($response->finishReason)->toBe(FinishReason::Stop);
});

it('sends the correct request payload', function (): void {
    fakeGigachatText('generate-text-with-a-prompt-1');

    $model = 'GigaChat';
    $prompt = 'Who are you?';

    Prism::text()
        ->using(Provider::Gigachat, $model)
        ->withPrompt($prompt)
        ->asText();

    Http::assertSent(fn (Request $request): bool => str_contains((string) $request->url(), 'chat/completions')
        && $request['model'] === $model
        && $request['messages'][0]['role'] === 'user'
        && $request['messages'][0]['content'] === $prompt);
});

it('sends system prompt in messages', function (): void {
    fakeGigachatText('generate-text-with-system-prompt-1');

    $systemPrompt = 'MODEL ADOPTS ROLE of [PERSONA: Nyx the Cthulhu]!';
    $userPrompt = 'Who are you?';

    Prism::text()
        ->using(Provider::Gigachat, 'GigaChat')
        ->withSystemPrompt($systemPrompt)
        ->withPrompt($userPrompt)
        ->asText();

    Http::assertSent(fn (Request $request): bool => str_contains((string) $request->url(), 'chat/completions')
        && $request['messages'][0]['role'] === 'system'
        && $request['messages'][0]['content'] === $systemPrompt
        && $request['messages'][1]['role'] === 'user'
        && $request['messages'][1]['content'] === $userPrompt);
});

it('sends temperature when set', function (): void {
    fakeGigachatText('generate-text-with-a-prompt-1');

    Prism::text()
        ->using(Provider::Gigachat, 'GigaChat')
        ->withPrompt('Who are you?')
        ->usingTemperature(0.5)
        ->asText();

    Http::assertSent(fn (Request $request): bool => str_contains((string) $request->url(), 'chat/completions')
        && $request['temperature'] === 0.5);
});

it('sends max_tokens when set', function (): void {
    fakeGigachatText('generate-text-with-a-prompt-1');

    Prism::text()
        ->using(Provider::Gigachat, 'GigaChat')
        ->withPrompt('Who are you?')
        ->withMaxTokens(100)
        ->asText();

    Http::assertSent(fn (Request $request): bool => str_contains((string) $request->url(), 'chat/completions')
        && $request['max_tokens'] === 100);
});

it('sends top_p when set', function (): void {
    fakeGigachatText('generate-text-with-a-prompt-1');

    Prism::text()
        ->using(Provider::Gigachat, 'GigaChat')
        ->withPrompt('Who are you?')
        ->usingTopP(0.9)
        ->asText();

    Http::assertSent(fn (Request $request): bool => str_contains((string) $request->url(), 'chat/completions')
        && $request['top_p'] === 0.9);
});

it('sends repetition_penalty provider option when set', function (): void {
    fakeGigachatText('generate-text-with-a-prompt-1');

    Prism::text()
        ->using(Provider::Gigachat, 'GigaChat')
        ->withPrompt('Who are you?')
        ->withProviderOptions(['repetition_penalty' => 1.2])
        ->asText();

    Http::assertSent(fn (Request $request): bool => str_contains((string) $request->url(), 'chat/completions')
        && $request['repetition_penalty'] === 1.2);
});

it('sends profanity_check provider option when set', function (): void {
    fakeGigachatText('generate-text-with-a-prompt-1');

    Prism::text()
        ->using(Provider::Gigachat, 'GigaChat')
        ->withPrompt('Who are you?')
        ->withProviderOptions(['profanity_check' => true])
        ->asText();

    Http::assertSent(fn (Request $request): bool => str_contains((string) $request->url(), 'chat/completions')
        && $request['profanity_check'] === true);
});

it('does not send null provider options', function (): void {
    fakeGigachatText('generate-text-with-a-prompt-1');

    Prism::text()
        ->using(Provider::Gigachat, 'GigaChat')
        ->withPrompt('Who are you?')
        ->asText();

    Http::assertSent(function (Request $request): bool {
        $data = $request->data();

        return str_contains((string) $request->url(), 'chat/completions')
            && ! array_key_exists('temperature', $data)
            && ! array_key_exists('top_p', $data)
            && ! array_key_exists('max_tokens', $data)
            && ! array_key_exists('repetition_penalty', $data)
            && ! array_key_exists('profanity_check', $data);
    });
});

it('maps blacklist finish reason to ContentFilter', function (): void {
    Http::fake([
        '*oauth*' => Http::response([
            'access_token' => 'test-access-token',
            'expires_at' => now()->addHour()->getTimestampMs(),
        ]),
        '*chat/completions*' => Http::response(json_encode([
            'choices' => [[
                'finish_reason' => 'blacklist',
                'index' => 0,
                'message' => ['content' => 'Filtered response.', 'role' => 'assistant'],
            ]],
            'created' => 1741000000,
            'model' => 'GigaChat',
            'object' => 'chat.completion',
            'usage' => ['completion_tokens' => 2, 'prompt_tokens' => 5, 'total_tokens' => 7, 'precached_prompt_tokens' => 0],
        ])),
    ]);

    $response = Prism::text()
        ->using(Provider::Gigachat, 'GigaChat')
        ->withPrompt('Say something inappropriate')
        ->asText();

    expect($response->finishReason)->toBe(FinishReason::ContentFilter);
});

it('maps length finish reason via FinishReasonMap', function (): void {
    $map = FinishReasonMap::map('length');
    expect($map)->toBe(FinishReason::Length);

    $map = FinishReasonMap::map('incomplete');
    expect($map)->toBe(FinishReason::Length);
});

it('includes created timestamp in additionalContent', function (): void {
    fakeGigachatText('generate-text-with-a-prompt-1');

    $response = Prism::text()
        ->using(Provider::Gigachat, 'GigaChat')
        ->withPrompt('Who are you?')
        ->asText();

    expect($response->steps[0]->additionalContent)->toHaveKey('created');
    expect($response->steps[0]->additionalContent['created'])->toBe(1741000000);
});

it('sets cache read tokens from precached_prompt_tokens', function (): void {
    Http::fake([
        '*oauth*' => Http::response([
            'access_token' => 'test-access-token',
            'expires_at' => now()->addHour()->getTimestampMs(),
        ]),
        '*chat/completions*' => Http::response(json_encode([
            'choices' => [[
                'finish_reason' => 'stop',
                'index' => 0,
                'message' => ['content' => 'Cached response.', 'role' => 'assistant'],
            ]],
            'created' => 1741000000,
            'model' => 'GigaChat',
            'object' => 'chat.completion',
            'usage' => ['completion_tokens' => 5, 'prompt_tokens' => 100, 'total_tokens' => 105, 'precached_prompt_tokens' => 80],
        ])),
    ]);

    $response = Prism::text()
        ->using(Provider::Gigachat, 'GigaChat')
        ->withPrompt('Who are you?')
        ->asText();

    expect($response->usage->cacheReadInputTokens)->toBe(80);
});

it('obtains an access token before making text requests', function (): void {
    Http::fake([
        '*oauth*' => Http::response([
            'access_token' => 'test-access-token',
            'expires_at' => now()->addHour()->getTimestampMs(),
        ]),
        '*chat/completions*' => Http::response(
            file_get_contents('tests/Fixtures/gigachat/generate-text-with-a-prompt-1.json'),
        ),
    ]);

    Prism::text()
        ->using(Provider::Gigachat, 'GigaChat')
        ->withPrompt('Who are you?')
        ->asText();

    Http::assertSent(fn (Request $request): bool => str_contains((string) $request->url(), 'oauth'));
    Http::assertSent(fn (Request $request): bool => str_contains((string) $request->url(), 'chat/completions')
        && $request->header('Authorization')[0] === 'Bearer test-access-token');
});
