<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Dps\DTO\Valores;

use Pulsar\NfseNacional\Enums\Dps\Valores\TipoImunidadeISSQN;
use Pulsar\NfseNacional\Enums\Dps\Valores\TipoRetISSQN;
use Pulsar\NfseNacional\Enums\Dps\Valores\TribISSQN;

/**
 * @phpstan-import-type ExigibilidadeSuspensaArray from ExigibilidadeSuspensa
 * @phpstan-import-type BeneficioMunicipalArray from BeneficioMunicipal
 *
 * @phpstan-type TributacaoMunicipalArray array{tribISSQN: string, tpRetISSQN: string, cPaisResult?: string, tpImunidade?: string, exigSusp?: ExigibilidadeSuspensaArray, BM?: BeneficioMunicipalArray, pAliq?: string}
 */
final readonly class TributacaoMunicipal
{
    public function __construct(
        public TribISSQN $tribISSQN,
        public TipoRetISSQN $tpRetISSQN,
        public ?string $cPaisResult = null,
        public ?TipoImunidadeISSQN $tpImunidade = null,
        public ?ExigibilidadeSuspensa $exigSusp = null,
        public ?BeneficioMunicipal $BM = null,
        public ?string $pAliq = null,
    ) {}

    /** @phpstan-param TributacaoMunicipalArray $data */
    public static function fromArray(array $data): self
    {
        return new self(
            tribISSQN: TribISSQN::from($data['tribISSQN']),
            tpRetISSQN: TipoRetISSQN::from($data['tpRetISSQN']),
            cPaisResult: $data['cPaisResult'] ?? null,
            tpImunidade: isset($data['tpImunidade']) ? TipoImunidadeISSQN::from($data['tpImunidade']) : null,
            exigSusp: isset($data['exigSusp']) ? ExigibilidadeSuspensa::fromArray($data['exigSusp']) : null,
            BM: isset($data['BM']) ? BeneficioMunicipal::fromArray($data['BM']) : null,
            pAliq: $data['pAliq'] ?? null,
        );
    }
}
