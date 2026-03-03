<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Valores;

/**
 * @phpstan-type TotTribValorArray array{vTotTribFed: string, vTotTribEst: string, vTotTribMun: string}
 */
final readonly class TotTribValor
{
    public function __construct(
        public string $vTotTribFed,
        public string $vTotTribEst,
        public string $vTotTribMun,
    ) {}

    /** @phpstan-param TotTribValorArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
