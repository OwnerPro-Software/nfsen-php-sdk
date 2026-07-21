<?php

declare(strict_types=1);

namespace OwnerPro\Nfsen\Danfse\Data;

/**
 * Bloco "TRIBUTAÇÃO IBS / CBS" do DANFSe (NT 008, item 2.1.10).
 *
 * Tributos da reforma. Diferente dos blocos de imunidade e benefício municipal,
 * o Anexo I não marca nenhuma linha deste como suprimível: uma NFS-e anterior à
 * reforma, sem o grupo IBSCBS, imprime o bloco com traços.
 *
 * @api
 */
final readonly class DanfseTributacaoIbsCbs
{
    public function __construct(
        public string $cstClassTrib,
        public string $indicadorOperacao,
        public string $exclusoesReducoes,
        public string $baseCalculo,
        public string $reducaoAliquotas,
        public string $aliquotaIbs,
        public string $aliquotaEfetivaMunicipal,
        public string $valorApuradoMunicipal,
        public string $aliquotaEfetivaEstadual,
        public string $valorApuradoEstadual,
        public string $valorTotalIbs,
        public string $aliquotaCbs,
        public string $aliquotaEfetivaCbs,
        public string $valorTotalCbs,
    ) {}
}
