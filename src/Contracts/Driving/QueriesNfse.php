<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driving;

/**
 * @api
 */
interface QueriesNfse
{
    public function consultar(): ConsultsNfse;
}
