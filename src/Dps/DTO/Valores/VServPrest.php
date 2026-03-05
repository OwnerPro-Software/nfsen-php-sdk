<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Valores;

/**
 * @phpstan-type VServPrestArray array{vServ: string, vReceb?: string}
 */
final readonly class VServPrest
{
    public function __construct(
        public string $vServ,
        public ?string $vReceb = null,
    ) {}

    /** @phpstan-param VServPrestArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
