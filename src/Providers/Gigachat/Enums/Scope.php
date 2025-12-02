<?php

declare(strict_types=1);

namespace Prism\Prism\Providers\Gigachat\Enums;

enum Scope: string
{
    case GIGACHAT_API_PERS = 'GIGACHAT_API_PERS';
    case GIGACHAT_API_B2B = 'GIGACHAT_API_B2B';
    case GIGACHAT_API_CORP = 'GIGACHAT_API_CORP';
}
