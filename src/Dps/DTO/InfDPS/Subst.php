<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\InfDPS;

use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;

/**
 * @phpstan-type SubstArray array{chSubstda: string, cMotivo: string, xMotivo?: string}
 */
final readonly class Subst
{
    public function __construct(
        public string $chSubstda,
        public CodigoJustificativaSubstituicao $cMotivo,
        public ?string $xMotivo = null,
    ) {}

    /** @phpstan-param SubstArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            chSubstda: $data['chSubstda'],
            cMotivo: CodigoJustificativaSubstituicao::from($data['cMotivo']),
            xMotivo: $data['xMotivo'] ?? null,
        );
    }
}
