<?php

declare(strict_types=1);

namespace Prism\Prism\Providers\Gigachat\Handlers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Providers\Gigachat\Concerns\MapsFinishReason;
use Prism\Prism\Providers\Gigachat\Concerns\ValidatesResponse;
use Prism\Prism\Providers\Gigachat\Maps\MessageMap;
use Prism\Prism\Text\Request;
use Prism\Prism\Text\Response;
use Prism\Prism\Text\ResponseBuilder;
use Prism\Prism\Text\Step;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

class Text
{
    use MapsFinishReason;
    use ValidatesResponse;

    protected ResponseBuilder $responseBuilder;

    public function __construct(protected PendingRequest $client)
    {
        $this->responseBuilder = new ResponseBuilder;
    }

    public function handle(Request $request): Response
    {
        $response = $this->sendRequest($request);

        $this->validateResponse($response);

        $data = $response->json();

        $responseMessage = new AssistantMessage(
            content: data_get($data, 'choices.{last}.message.content') ?? '',
        );

        $request->addMessage($responseMessage);

        return match ($this->mapFinishReason($data)) {
            FinishReason::Stop => $this->handleStop($data, $request),
            default => throw new PrismException(message: 'Gigachat: unknown finish reason'),
        };
    }

    protected function handleStop(array $data, Request $request): Response
    {
        $this->addStep($data, $request);

        return $this->responseBuilder->toResponse();
    }

    protected function shouldContinue(Request $request): bool
    {
        return $this->responseBuilder->steps->count() < $request->maxSteps();
    }

    protected function sendRequest(Request $request): ClientResponse
    {
        return $this->client->post(
            'chat/completions',
            array_merge([
                'model' => $request->model(),
                'messages' => (new MessageMap($request->messages(), $request->systemPrompts()))(),
            ], Arr::whereNotNull([
                // https://developers.sber.ru/docs/ru/gigachat/api/reference/rest/post-chat
                'temperature' => $request->temperature(),
                'top_p' => $request->topP(),
                'max_tokens' => $request->maxTokens(),
                'repetition_penalty' => $request->providerOptions('repetition_penalty'),
                'profanity_check' => $request->providerOptions('profanity_check'),
            ]))
        );
    }

    protected function addStep(
        array $data,
        Request $request,
    ): void {
        $this->responseBuilder->addStep(new Step(
            text: data_get($data, 'choices.{last}.message.content') ?? '',
            finishReason: $this->mapFinishReason($data),
            toolCalls: [],
            toolResults: [],
            providerToolCalls: [],
            usage: new Usage(
                promptTokens: data_get($data, 'usage.prompt_tokens', 0),
                completionTokens: data_get($data, 'usage.completion_tokens', 0),
                cacheReadInputTokens: data_get($data, 'usage.precached_prompt_tokens', 0),
            ),
            meta: new Meta(
                id: Str::uuid()->toString(),
                model: data_get($data, 'model'),
            ),
            messages: $request->messages(),
            systemPrompts: $request->systemPrompts(),
            additionalContent: Arr::whereNotNull([
                'created' => data_get($data, 'created'),
            ]),
        ));
    }
}
