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
        /** Campos do bloco ISSQN que a NT 008 exige e o SDK não coletava. */
        public string $tipoImunidade,
        public string $suspensaoExigibilidade,
        public string $numeroProcessoSuspensao,
        public string $beneficioMunicipal,
        public string $calculoBM,
        public string $totalDeducoesReducoes,
        /**
         * Linhas suprimíveis do Anexo I (NT 008, item 2.4.5, nota 5): cada uma pode
         * sumir quando nenhum de seus campos tem dado no XML.
         */
        public bool $exibeRegimeEImunidade,
        public bool $exibeBeneficioEDeducoes,
        public string $bcIssqn,
        public string $aliquota,
        public string $retencaoIssqn,
        public string $issqnApurado,
    ) {}
}
