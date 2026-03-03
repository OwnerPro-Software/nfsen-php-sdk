<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\Contracts\Driving;

use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\Responses\NfseResponse;

interface SubstitutesNfse
{
    public function substituir(string $chave, string $chaveSubstituta, CodigoJustificativaSubstituicao|string $codigoMotivo, string $descricao = '', int $nPedRegEvento = 1): NfseResponse;
}
