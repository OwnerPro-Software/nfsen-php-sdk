<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Contracts\Driving;

interface QueriesNfse
{
    public function consultar(): ConsultsNfse;
}
