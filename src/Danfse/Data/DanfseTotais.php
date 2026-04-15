<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse\Data;

/**
 * @api
 */
final readonly class DanfseTotais
{
    public function __construct(
        public string $valorServico,
        public string $descontoCondicionado,
        public string $descontoIncondicionado,
        public string $issqnRetido,
        public string $retencoesFederais,
        public string $pisCofins,
        public string $valorLiquido,
    ) {}
}
