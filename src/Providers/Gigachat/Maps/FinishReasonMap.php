<?php

declare(strict_types=1);

namespace Prism\Prism\Providers\Gigachat\Maps;

use Prism\Prism\Enums\FinishReason;

class FinishReasonMap
{
    public static function map(string $finishReason): FinishReason
    {
        return match ($finishReason) {
            'incomplete' => FinishReason::Length,
            'length' => FinishReason::Length,
            'failed' => FinishReason::Error,
            'stop' => FinishReason::Stop,
            'blacklist' => FinishReason::ContentFilter,
            default => FinishReason::Unknown,
        };
    }
}
