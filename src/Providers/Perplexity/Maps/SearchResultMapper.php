<?php

declare(strict_types=1);

namespace Prism\Prism\Providers\Perplexity\Maps;

use Carbon\Carbon;
use Prism\Prism\Providers\Perplexity\ValueObjects\SearchResult;

class SearchResultMapper
{
    /**
     * @return SearchResult[]
     */
    public static function mapFromPerplexity(array $searchResults): array
    {
        return array_map(
            self::mapSearchResult(...),
            $searchResults ?? []
        );
    }

    /**
     * @param  string  $content
     * @param  string  $citationUrl
     * @param  int  $index
     */
    protected static function mapSearchResult(array $seachResultData): SearchResult
    {
        return new SearchResult(
            title: $seachResultData['title'] ?? '',
            url: $seachResultData['url'] ?? '',
            date: Carbon::parse($seachResultData['date'] ?? ''),
            lastUpdated: Carbon::parse($seachResultData['lastUpdated'] ?? ''),
            snippet: $seachResultData['snippet'] ?? '',
            source: $seachResultData['source'] ?? '',
        );
    }
}
