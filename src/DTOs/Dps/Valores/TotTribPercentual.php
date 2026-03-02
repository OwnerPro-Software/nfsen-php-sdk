<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Valores;

/**
 * @phpstan-type TotTribPercentualArray array{pTotTribFed: string, pTotTribEst: string, pTotTribMun: string}
 */
final readonly class TotTribPercentual
{
    public function __construct(
        public string $pTotTribFed,
        public string $pTotTribEst,
        public string $pTotTribMun,
    ) {}

    /** @phpstan-param TotTribPercentualArray $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
