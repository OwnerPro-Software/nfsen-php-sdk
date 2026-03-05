<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Valores;

use Pulsar\NfseNacional\Dps\Enums\Valores\TpSusp;

/**
 * @phpstan-type ExigibilidadeSuspensaArray array{tpSusp: string, nProcesso: string}
 */
final readonly class ExigibilidadeSuspensa
{
    public function __construct(
        public TpSusp $tpSusp,
        public string $nProcesso,
    ) {}

    /** @phpstan-param ExigibilidadeSuspensaArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            tpSusp: TpSusp::from($data['tpSusp']),
            nProcesso: $data['nProcesso'],
        );
    }
}
