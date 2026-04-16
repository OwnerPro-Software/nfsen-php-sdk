<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driving;

/**
 * @api
 */
interface QueriesDistribuicao
{
    public function distribuicao(): DistributesNfse;
}
