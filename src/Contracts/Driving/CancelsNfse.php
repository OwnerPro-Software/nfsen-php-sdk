<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driving;

use OwnerPro\Nfsen\Enums\CodigoJustificativaCancelamento;
use OwnerPro\Nfsen\Responses\NfseResponse;

interface CancelsNfse
{
    public function cancelar(string $chave, CodigoJustificativaCancelamento|string $codigoMotivo, string $descricao): NfseResponse;
}
