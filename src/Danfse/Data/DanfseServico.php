<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse\Data;

/**
 * @api
 */
final readonly class DanfseServico
{
    public function __construct(
        public string $codigoTribNacional,
        public string $descTribNacional,
        public string $codigoTribMunicipal,
        public string $descTribMunicipal,
        public string $localPrestacao,
        public string $paisPrestacao,
        public string $descricao,
        public string $codigoNbs,
    ) {}
}
