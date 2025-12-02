<?php

declare(strict_types=1);

namespace Prism\Prism\Providers\Gigachat\ValueObjects;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string,mixed>
 */
class AccessToken implements Arrayable
{
    public function __construct(
        #[\SensitiveParameter] public string $token,
        public CarbonImmutable $expiresAt,
    ) {}

    public function isExpired(): bool
    {
        return $this->expiresAt->lte(Carbon::now());
    }

    /**
     * @return array<string,mixed>
     */
    #[\Override]
    public function toArray(): array
    {
        return [
            'access_token' => $this->token,
            'expires_at' => $this->expiresAt,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['access_token'],
            CarbonImmutable::createFromTimestampMsUTC($data['expires_at']),
        );
    }
}
