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
        /**
         * `false` reduz o bloco inteiro à frase do item 2.3.1 — "TRIBUTAÇÃO MUNICIPAL
         * (ISSQN) - OPERAÇÃO NÃO SUJEITA AO ISSQN".
         *
         * Só `tribISSQN = 4` (Não Incidência) chega aqui como `false`. Imunidade e
         * exportação de serviço também não recolhem ISSQN, mas a NT reserva campo no
         * bloco para as duas — "Tipo de Imunidade do ISSQN" para uma, e o país de
         * `cPaisResult` dentro de "Município / UF / País da Incidência" para a outra.
         * Colapsá-las apagaria do DANFSe justamente o dado que as distingue.
         */
        public bool $sujeitaAoIssqn = true,
    ) {}
}
