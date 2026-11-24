<?php

declare(strict_types=1);

use Carbon\Carbon;
use Prism\Prism\Enums\Citations\CitationSourceType;
use Prism\Prism\Providers\Perplexity\Concerns\ExtractsAdditionalContent;
use Prism\Prism\Providers\Perplexity\ValueObjects\SearchResult;
use Prism\Prism\ValueObjects\Citation;

it('extracts the reasoning correctly from the raw response', function (string $rawResponse, ?string $expectedOutput): void {
    $testClass = new class
    {
        use ExtractsAdditionalContent;

        public function testExtractsReasoning(string $rawResponse): ?string
        {
            return $this->extractsReasoning($rawResponse);
        }
    };

    $this->assertEquals($expectedOutput, (new $testClass)->testExtractsReasoning($rawResponse));
})->with([
    'with reasoning block' => [
        'rawResponse' => <<<'EOT'
            <think>
            The model reasoning process
            </think>

            { "foo": "bar" }
        EOT,
        'expectedOutput' => 'The model reasoning process',
    ],
    'without reasoning block' => [
        'rawResponse' => <<<'EOT'
            { "foo": "bar" }
        EOT,
        'expectedOutput' => null,
    ],
]);

it('extracts additional content from the response data', function (): void {
    $testClass = new class
    {
        use ExtractsAdditionalContent;

        public function testExtractsAdditionalContent(array $data): array
        {
            return $this->extractsAdditionalContent($data);
        }
    };

    $responseData = [
        'citations' => [
            'https://example.com/one',
            'https://example.com/two',
        ],
        'search_results' => [
            [
                'title' => 'Example result',
                'url' => 'https://example.com/one',
                'date' => '2025-01-01',
                'last_updated' => '2025-10-28',
                'snippet' => 'Example snippet',
                'source' => 'web',
            ],
        ],
        'choices' => [
            [
                'message' => [
                    'content' => <<<'EOT'
                        <think>
                        The model reasoning process
                        </think>

                        { "foo": "bar" }
                    EOT,
                ],
            ],
        ],
    ];

    $expectedOutput = [
        'citations' => [
            new Citation(CitationSourceType::Url, 'https://example.com/one'),
            new Citation(CitationSourceType::Url, 'https://example.com/two'),
        ],
        'citation_urls' => [
            'https://example.com/one',
            'https://example.com/two',
        ],
        'search_results' => [
            [
                'title' => 'Example result',
                'url' => 'https://example.com/one',
                'date' => '2025-01-01',
                'last_updated' => '2025-10-28',
                'snippet' => 'Example snippet',
                'source' => 'web',
            ],
        ],
        'searchResults' => [
            new SearchResult(
                title: 'Example result',
                url: 'https://example.com/one',
                date: Carbon::parse('2025-01-01'),
                lastUpdated: Carbon::parse('2025-10-28'),
                snippet: 'Example snippet',
                source: 'web',
            ),
        ],
        'reasoning' => 'The model reasoning process',
    ];

    $this->assertEquals($expectedOutput, (new $testClass)->testExtractsAdditionalContent($responseData));
});
