<?php

declare(strict_types=1);

namespace Prism\Prism\Providers\Gigachat\Concerns;

use Illuminate\Http\Client\Response;
use Prism\Prism\Exceptions\PrismException;

trait ValidatesResponse
{
    protected function validateResponse(Response $response): void
    {
        $data = $response->json();
        if (! $data || data_get($data, 'error')) {
            throw PrismException::providerResponseError(vsprintf(
                'Gigachat Error:  %s',
                [
                    data_get($data, 'message', 'unknown'),
                ]
            ));
        }
    }
}
