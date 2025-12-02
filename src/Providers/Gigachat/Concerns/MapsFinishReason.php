<?php

declare(strict_types=1);

namespace Prism\Prism\Providers\Gigachat\Concerns;

use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Providers\Gigachat\Maps\FinishReasonMap;

trait MapsFinishReason
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function mapFinishReason(array $data): FinishReason
    {
        return FinishReasonMap::map(
            data_get($data, 'choices.{last}.finish_reason', ''),
        );
    }
}
