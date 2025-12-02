<?php

declare(strict_types=1);

namespace Prism\Prism\Providers\Perplexity\Maps;

use Prism\Prism\Enums\Citations\CitationSourceType;
use Prism\Prism\ValueObjects\Citation;

class CitationsMapper
{
    /**
     * @return Citation[]
     */
    public static function mapFromPerplexity(string $content, array $citationsUrls): array
    {
        return array_map(
            self::mapCitation(...),
            $citationsUrls ?? []
        );
    }

    protected static function mapCitation(string $citationUrl): Citation
    {
        return new Citation(
            sourceType: CitationSourceType::Url,
            source: $citationUrl,
        );
    }
}
