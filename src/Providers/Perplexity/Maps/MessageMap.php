<?php

declare(strict_types=1);

namespace Prism\Prism\Providers\Perplexity\Maps;

use Exception;
use Prism\Prism\Contracts\Message;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

class MessageMap
{
    /** @var array<int, mixed> */
    protected array $mappedMessages = [];

    /**
     * @param  array<int, Message>  $messages
     * @param  SystemMessage[]  $systemPrompts
     */
    public function __construct(
        protected array $messages,
        protected array $systemPrompts
    ) {
        $this->messages = array_merge(
            $this->systemPrompts,
            $this->messages
        );
    }

    /**
     * @return array<int, mixed>
     */
    public function __invoke(): array
    {
        array_map(
            $this->mapMessage(...),
            $this->messages
        );

        return $this->mappedMessages;
    }

    protected function mapMessage(Message $message): void
    {
        match ($message::class) {
            UserMessage::class => $this->mapUserMessage($message),
            SystemMessage::class => $this->mapSystemMessage($message),
            AssistantMessage::class => $this->mapAssistantMessage($message),
            default => throw new Exception('Could not map message type '.$message::class),
        };
    }

    protected function mapSystemMessage(SystemMessage $message): void
    {
        $this->mappedMessages[] = [
            'role' => 'system',
            'content' => $message->content,
        ];
    }

    protected function mapUserMessage(UserMessage $message): void
    {
        $this->mappedMessages[] = [
            'role' => 'user',
            'content' => $message->text(),
            ...$message->additionalAttributes,
        ];
    }

    protected function mapAssistantMessage(AssistantMessage $message): void
    {
        $this->mappedMessages[] = [
            'role' => 'assistant',
            'content' => $message->content,
        ];
    }
}
