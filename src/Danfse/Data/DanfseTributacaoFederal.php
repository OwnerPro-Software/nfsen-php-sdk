<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse\Data;

/**
 * @api
 */
final readonly class DanfseTributacaoFederal
{
    public function __construct(
        public string $irrf,
        public string $cp,
        public string $csll,
        public string $pis,
        public string $cofins,
    ) {}
}
