<?php

declare(strict_types=1);

namespace Prism\Prism\Providers\Perplexity\Concerns;

use Prism\Prism\Providers\Perplexity\Maps\CitationsMapper;
use Prism\Prism\ValueObjects\Citation;

trait ExtractsCitations
{
    /**
     * @param  array<string,mixed>  $responseData
     * @return Citation[]
     */
    protected function extractCitations(array $responseData): ?array
    {
        $content = data_get($responseData, 'choices.{last}.message.content', '');
        if (empty($content)) {
            return [];
        }
        $citationsBlock = data_get($responseData, 'citations', []);
        if (empty($citationsBlock)) {
            return [];
        }

        return CitationsMapper::mapFromPerplexity($content, $citationsBlock);
    }
}
