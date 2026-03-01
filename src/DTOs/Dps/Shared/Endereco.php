<?php

declare(strict_types=1);

namespace Pulsar\NfseNacional\DTOs\Dps\Shared;

use Pulsar\NfseNacional\DTOs\Dps\Concerns\ValidatesExclusiveChoice;

final readonly class Endereco
{
    use ValidatesExclusiveChoice;

    public function __construct(
        public string $xLgr,
        public string $nro,
        public string $xBairro,
        public ?EnderecoNacional $endNac = null,
        public ?EnderecoExterior $endExt = null,
        public ?string $xCpl = null,
    ) {
        self::validateChoice(
            ['endNac' => $endNac, 'endExt' => $endExt],
            expected: 1,
            message: 'Endereço requer exatamente um entre endNac ou endExt.',
        );
    }
}
