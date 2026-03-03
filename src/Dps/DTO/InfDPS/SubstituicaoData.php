<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\InfDPS;

use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;

/**
 * @phpstan-type SubstituicaoDataArray array{chSubstda: string, cMotivo: string, xMotivo?: string}
 */
final readonly class SubstituicaoData
{
    public function __construct(
        public string $chSubstda,
        public CodigoJustificativaSubstituicao $cMotivo,
        public ?string $xMotivo = null,
    ) {}

    /** @phpstan-param SubstituicaoDataArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            chSubstda: $data['chSubstda'],
            cMotivo: CodigoJustificativaSubstituicao::from($data['cMotivo']),
            xMotivo: $data['xMotivo'] ?? null,
        );
    }
}
