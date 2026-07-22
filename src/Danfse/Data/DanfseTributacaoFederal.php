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
        /** Descrição de `tribFed/piscofins/tpRetPisCofins` (NT 008). */
        public string $descricaoContribuicoesRetidas,
        /**
         * NT 008, item 2.4.5, nota 6: a linha de PIS, COFINS e descrição das
         * contribuições retidas "será impressa para as NFS-e emitidas com data de
         * competência até o final do ano-calendário de 2026".
         */
        public bool $exibePisCofins = true,
    ) {}
}
