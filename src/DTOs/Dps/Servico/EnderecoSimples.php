<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Servico;

use Pulsar\NfseNacional\DTOs\Dps\Concerns\ValidatesExclusiveChoice;

final readonly class EnderecoSimples
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public string $xLgr,
        public string $nro,
        public string $xBairro,
        public ?string $CEP = null,
        public ?EnderecoExteriorObra $endExt = null,
        public ?string $xCpl = null,
    ) {
        self::validateChoice(
            ['CEP' => $CEP, 'endExt' => $endExt],
            expected: 1,
            message: 'EnderecoSimples requer exatamente um entre CEP ou endExt.',
        );
    }
}
