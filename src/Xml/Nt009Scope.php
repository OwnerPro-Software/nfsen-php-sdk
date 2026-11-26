<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Xml;

use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\GReeRepRes;
use OwnerPro\Nfsen\Dps\DTO\IBSCBS\Imovel;
use OwnerPro\Nfsen\Dps\DTO\Serv\ComExt;
use OwnerPro\Nfsen\Exceptions\InvalidDpsArgument;

final readonly class Nt009Scope
{
    // NT 009, itens 2.3, 2.6 e 2.12: grupos cuja forma mudou e que o DTO ainda descreve na forma antiga.
    public function assertSupports(DpsData $data): void
    {
        $restructured = array_search(true, [
            'serv/comExt' => $data->serv->comExt instanceof ComExt,
            'valores/vDedRed/documentos' => $data->valores->vDedRed?->documentos !== null,
            'IBSCBS/imovel' => $data->IBSCBS?->imovel instanceof Imovel,
            'IBSCBS/valores/gReeRepRes' => $data->IBSCBS?->valores->gReeRepRes instanceof GReeRepRes,
        ], true);

        if ($restructured !== false) {
            throw new InvalidDpsArgument(sprintf('O grupo %s ainda não é suportado no leiaute da NT 009.', $restructured));
        }
    }
}
