<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Servico;

use Pulsar\NfseNacional\Enums\Dps\Servico\MDIC;
use Pulsar\NfseNacional\Enums\Dps\Servico\MecAFComexP;
use Pulsar\NfseNacional\Enums\Dps\Servico\MecAFComexT;
use Pulsar\NfseNacional\Enums\Dps\Servico\ModoPrestacao;
use Pulsar\NfseNacional\Enums\Dps\Servico\MovTempBens;
use Pulsar\NfseNacional\Enums\Dps\Servico\VinculoPrestacao;

final readonly class ComercioExterior
{
    public function __construct(
        public ModoPrestacao $mdPrestacao,
        public VinculoPrestacao $vincPrest,
        public string $tpMoeda,
        public string $vServMoeda,
        public MecAFComexP $mecAFComexP,
        public MecAFComexT $mecAFComexT,
        public MovTempBens $movTempBens,
        public MDIC $mdic,
        public ?string $nDI = null,
        public ?string $nRE = null,
    ) {}
}
