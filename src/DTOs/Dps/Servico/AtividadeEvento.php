<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Servico;

use Pulsar\NfseNacional\DTOs\Dps\Concerns\ValidatesExclusiveChoice;

final readonly class AtividadeEvento
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public string $xNome,
        public string $dtIni,
        public string $dtFim,
        public ?string $idAtvEvt = null,
        public ?EnderecoSimples $end = null,
    ) {
        self::validateChoice(
            ['idAtvEvt' => $idAtvEvt, 'end' => $end],
            expected: 1,
            message: 'AtividadeEvento requer exatamente um entre idAtvEvt ou end.',
        );
    }
}
