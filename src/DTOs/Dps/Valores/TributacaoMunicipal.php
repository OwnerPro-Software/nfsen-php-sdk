<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Valores;

use Pulsar\NfseNacional\Enums\Dps\Valores\TipoImunidadeISSQN;
use Pulsar\NfseNacional\Enums\Dps\Valores\TipoRetISSQN;
use Pulsar\NfseNacional\Enums\Dps\Valores\TribISSQN;

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
}
