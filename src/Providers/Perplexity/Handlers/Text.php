<?php

declare(strict_types=1);

namespace Prism\Prism\Providers\Perplexity\Handlers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Arr;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Providers\Perplexity\Concerns\ExtractsCitations;
use Prism\Prism\Providers\Perplexity\Concerns\ExtractsSearchResults;
use Prism\Prism\Providers\Perplexity\Concerns\MapsFinishReason;
use Prism\Prism\Providers\Perplexity\Concerns\ValidatesResponse;
use Prism\Prism\Providers\Perplexity\Maps\MessageMap;
use Prism\Prism\Text\Request;
use Prism\Prism\Text\Response;
use Prism\Prism\Text\ResponseBuilder;
use Prism\Prism\Text\Step;
use Prism\Prism\ValueObjects\MessagePartWithCitations;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

class Text
{
    use ExtractsCitations;
    use ExtractsSearchResults;
    use MapsFinishReason;
    use ValidatesResponse;

    protected ResponseBuilder $responseBuilder;

    /** @var ?MessagePartWithCitations[] */
    protected array $citations = [];

    protected array $seachResults = [];

    public function __construct(protected PendingRequest $client)
    {
        $this->responseBuilder = new ResponseBuilder;
    }

    public function handle(Request $request): Response
    {
        $response = $this->sendRequest($request);

        $this->validateResponse($response);

        $data = $response->json();

        $this->citations = $this->extractCitations($data);

        $this->seachResults = $this->extractSearchResults($data);

        $responseMessage = new AssistantMessage(
            content: data_get($data, 'choices.{last}.message.content') ?? '',
        );

        $request->addMessage($responseMessage);

        return match ($this->mapFinishReason($data)) {
            FinishReason::Stop => $this->handleStop($data, $request),
            FinishReason::Length => throw new PrismException('Perplexity: max tokens exceeded'),
            default => throw new PrismException('Perplexity: unknown finish reason'),
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
                // https://docs.perplexity.ai/api-reference/chat-completions-post
                'search_mode' => $request->providerOptions('search_mode'),
                'reasoning_effort' => $request->providerOptions('reasoning_effort'),
                'max_tokens' => $request->maxTokens(),
                'temperature' => $request->temperature(),
                'top_p' => $request->topP(),
                'language_preference' => $request->providerOptions('language_preference'),
                'search_domain_filter' => $request->providerOptions('search_domain_filter'),
                'return_images' => $request->providerOptions('return_images'),
                'return_related_questions' => $request->providerOptions('return_related_questions'),
                'search_recency_filter' => $request->providerOptions('search_recency_filter'),
                'search_after_date_filter' => $request->providerOptions('search_after_date_filter'),
                'search_before_date_filter' => $request->providerOptions('search_before_date_filter'),
                'last_updated_after_filter' => $request->providerOptions('last_updated_after_filter'),
                'last_updated_before_filter' => $request->providerOptions('last_updated_before_filter'),
                'top_k' => $request->providerOptions('top_k'),
                'presence_penalty' => $request->providerOptions('presence_penalty'),
                'frequency_penalty' => $request->providerOptions('frequency_penalty'),
                'disable_search' => $request->providerOptions('disable_search'),
                'enable_search_classifier' => $request->providerOptions('enable_search_classifier'),
                'web_search_options' => $request->providerOptions('web_search_options'),
                'media_response' => $request->providerOptions('media_response'),
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
                completionTokens: data_get($data, 'usage.completion_tokens'),
            ),
            meta: new Meta(
                id: data_get($data, 'id'),
                model: data_get($data, 'model'),
            ),
            messages: $request->messages(),
            systemPrompts: $request->systemPrompts(),
            additionalContent: Arr::whereNotNull([
                'citations' => $this->citations,
                'searchResults' => $this->seachResults,
            ]),
        ));
    }
}
