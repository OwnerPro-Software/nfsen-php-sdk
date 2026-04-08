<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driving;

use OwnerPro\Nfsen\Responses\DistribuicaoResponse;

interface DistributesNfse
{
    public function documentos(int $nsu, ?string $cnpjConsulta = null): DistribuicaoResponse;

    public function documento(int $nsu, ?string $cnpjConsulta = null): DistribuicaoResponse;

    public function eventos(string $chave): DistribuicaoResponse;
}
