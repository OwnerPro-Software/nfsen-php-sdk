<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Servico;

use Pulsar\NfseNacional\DTOs\Dps\Concerns\ValidatesExclusiveChoice;

final readonly class Servico
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public CodigoServico $cServ,
        public ?string $cLocPrestacao = null,
        public ?string $cPaisPrestacao = null,
        public ?ComercioExterior $comExt = null,
        public ?Obra $obra = null,
        public ?LocacaoSublocacao $lsadppu = null,
        public ?AtividadeEvento $atvEvento = null,
        public ?ExploracaoRodoviaria $explRod = null,
        public ?InfoComplementar $infoCompl = null,
    ) {
        self::validateChoice(
            ['cLocPrestacao' => $cLocPrestacao, 'cPaisPrestacao' => $cPaisPrestacao],
            expected: 1,
            message: 'Serviço requer exatamente um entre cLocPrestacao ou cPaisPrestacao.',
        );
    }
}
