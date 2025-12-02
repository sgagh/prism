<?php

declare(strict_types=1);

namespace Prism\Prism\Providers\Perplexity\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string,mixed>
 */
class SearchResult implements Arrayable
{
    public function __construct(
        public readonly string $title,
        public readonly string $url,
        public readonly \DateTimeInterface $date,
        public readonly \DateTimeInterface $lastUpdated,
        public readonly string $snippet,
        public readonly string $source,
    ) {}

    /**
     * @return array<string,mixed>
     */
    #[\Override]
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'url' => $this->url,
            'date' => $this->date,
            'lastUpdated' => $this->lastUpdated,
            'snippet' => $this->snippet,
            'source' => $this->source,
        ];
    }
}
