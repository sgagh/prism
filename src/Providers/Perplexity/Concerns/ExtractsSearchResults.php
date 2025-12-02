<?php

declare(strict_types=1);

namespace Prism\Prism\Providers\Perplexity\Concerns;

use Illuminate\Support\Arr;
use Prism\Prism\Providers\Perplexity\Maps\SearchResultMapper;
use Prism\Prism\Providers\Perplexity\ValueObjects\SearchResult;

trait ExtractsSearchResults
{
    /**
     * @param  array<string,mixed>  $responseData
     * @return SearchResult[]
     */
    protected function extractSearchResults(array $responseData): ?array
    {
        $searchResults = data_get($responseData, 'search_results', []);
        if (empty($searchResults)) {
            return [];
        }

        return Arr::whereNotNull(SearchResultMapper::mapFromPerplexity($searchResults));
    }
}
