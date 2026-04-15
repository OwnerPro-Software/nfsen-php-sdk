<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse\Data;

/**
 * @api
 */
final readonly class DanfseTributacaoMunicipal
{
    public function __construct(
        public string $tributacaoIssqn,
        public string $municipioIncidencia,
        public string $regimeEspecial,
        public string $valorServico,
        public string $bcIssqn,
        public string $aliquota,
        public string $retencaoIssqn,
        public string $issqnApurado,
    ) {}
}
