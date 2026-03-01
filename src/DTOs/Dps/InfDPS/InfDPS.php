<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\InfDPS;

use Pulsar\NfseNacional\Enums\Dps\InfDPS\MotivoEmissaoTI;
use Pulsar\NfseNacional\Enums\Dps\InfDPS\TipoEmitente;
use Pulsar\NfseNacional\Enums\NfseAmbiente;

final readonly class InfDPS
{
    public function __construct(
        public NfseAmbiente $tpAmb,
        public string $dhEmi,
        public string $verAplic,
        public string $serie,
        public int $nDPS,
        public string $dCompet,
        public TipoEmitente $tpEmit,
        public string $cLocEmi,
        public ?MotivoEmissaoTI $cMotivoEmisTI = null,
        public ?string $chNFSeRej = null,
    ) {}
}
