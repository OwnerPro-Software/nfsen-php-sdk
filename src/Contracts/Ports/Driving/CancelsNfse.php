<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Contracts\Ports\Driving;

use Pulsar\NfseNacional\Responses\NfseResponse;
use Pulsar\NfseNacional\Enums\CodigoJustificativaCancelamento;

interface CancelsNfse
{
    public function cancelar(string $chave, CodigoJustificativaCancelamento|string $codigoMotivo, string $descricao, int $nPedRegEvento = 1): NfseResponse;
}
