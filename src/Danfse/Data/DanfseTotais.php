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
        /**
         * `infNFSe/valores/vTotalRet` — campo "TOTAL DAS RETENÇÕES (ISSQN / FEDERAIS)".
         *
         * Um campo só, como a NT 008 o define: Σ(vRetCP + vRetIRRF + vRetCSLL + ISSQN
         * retido). O ISSQN retido tem lugar próprio no bloco de tributação municipal, e
         * o PIS/COFINS de apuração própria, no de tributação federal.
         */
        public string $totalRetencoes,
        public string $valorLiquido,
        /** NT 008: totais da reforma, somados ao líquido da NFS-e. */
        public string $totalIbsCbs,
        public string $valorLiquidoComIbsCbs,
    ) {}
}
