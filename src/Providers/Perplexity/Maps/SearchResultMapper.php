<?php

declare(strict_types=1);

namespace Prism\Prism\Providers\Perplexity\Maps;

use Carbon\Carbon;
use Prism\Prism\Providers\Perplexity\ValueObjects\SearchResult;

class SearchResultMapper
{
    /**
     * @param  array<int, array<string, mixed>>  $searchResults
     * @return SearchResult[]
     */
    public static function mapFromPerplexity(array $searchResults): array
    {
        return array_map(
            self::mapSearchResult(...),
            array_values(array_filter($searchResults, is_array(...)))
        );
    }

    /**
     * @param  array<string, mixed>  $searchResultData
     */
    protected static function mapSearchResult(array $searchResultData): SearchResult
    {
        return new SearchResult(
            title: $searchResultData['title'] ?? '',
            url: $searchResultData['url'] ?? '',
            date: self::parseDate($searchResultData['date'] ?? null),
            lastUpdated: self::parseDate($searchResultData['last_updated'] ?? $searchResultData['lastUpdated'] ?? null),
            snippet: $searchResultData['snippet'] ?? '',
            source: $searchResultData['source'] ?? '',
        );
    }

    protected static function parseDate(mixed $value): Carbon
    {
        try {
            return Carbon::parse(is_string($value) && $value !== '' ? $value : 'now');
        } catch (\Throwable) {
            return Carbon::now();
        }
    }
}
