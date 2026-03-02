<?php

declare(strict_types=1);

namespace Tests\Providers\Gigachat;

use Prism\Prism\Contracts\Message;
use Prism\Prism\Providers\Gigachat\Maps\MessageMap;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

it('maps user messages', function (): void {
    $messageMap = new MessageMap(
        messages: [
            new UserMessage('Who are you?'),
        ],
        systemPrompts: []
    );

    expect($messageMap())->toBe([[
        'role' => 'user',
        'content' => 'Who are you?',
    ]]);
});

it('maps assistant messages', function (): void {
    $messageMap = new MessageMap(
        messages: [
            new AssistantMessage('I am GigaChat.'),
        ],
        systemPrompts: []
    );

    expect($messageMap())->toBe([[
        'role' => 'assistant',
        'content' => 'I am GigaChat.',
    ]]);
});

it('maps system prompts before messages', function (): void {
    $messageMap = new MessageMap(
        messages: [
            new UserMessage('Who are you?'),
        ],
        systemPrompts: [
            new SystemMessage('MODEL ADOPTS ROLE of [PERSONA: Nyx the Cthulhu]'),
        ]
    );

    expect($messageMap())->toBe([
        [
            'role' => 'system',
            'content' => 'MODEL ADOPTS ROLE of [PERSONA: Nyx the Cthulhu]',
        ],
        [
            'role' => 'user',
            'content' => 'Who are you?',
        ],
    ]);
});

it('maps multiple system prompts', function (): void {
    $messageMap = new MessageMap(
        messages: [
            new UserMessage('Who are you?'),
        ],
        systemPrompts: [
            new SystemMessage('MODEL ADOPTS ROLE of [PERSONA: Nyx the Cthulhu]'),
            new SystemMessage('But my friends call me Nyx'),
        ]
    );

    expect($messageMap())->toBe([
        [
            'role' => 'system',
            'content' => 'MODEL ADOPTS ROLE of [PERSONA: Nyx the Cthulhu]',
        ],
        [
            'role' => 'system',
            'content' => 'But my friends call me Nyx',
        ],
        [
            'role' => 'user',
            'content' => 'Who are you?',
        ],
    ]);
});

it('maps a conversation with user and assistant messages', function (): void {
    $messageMap = new MessageMap(
        messages: [
            new UserMessage('Who are you?'),
            new AssistantMessage('I am GigaChat.'),
            new UserMessage('Tell me more.'),
        ],
        systemPrompts: []
    );

    expect($messageMap())->toBe([
        [
            'role' => 'user',
            'content' => 'Who are you?',
        ],
        [
            'role' => 'assistant',
            'content' => 'I am GigaChat.',
        ],
        [
            'role' => 'user',
            'content' => 'Tell me more.',
        ],
    ]);
});

it('throws an exception for unsupported message types', function (): void {
    $unknownMessage = new class implements Message {};

    $messageMap = new MessageMap(
        messages: [$unknownMessage],
        systemPrompts: []
    );

    $messageMap();
})->throws(\Exception::class);
